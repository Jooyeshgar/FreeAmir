<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SubjectType;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\AncillaryCost;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\AncillaryCostService;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BeginningInventoryTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $fiscalYear;

    private User $user;

    private Warehouse $mainWarehouse;

    private Warehouse $otherWarehouse;

    private Product $product;

    private int $inventorySubjectId;

    private int $beginningInventorySubjectId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->fiscalYear = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);
        $this->actingAs($this->user);

        foreach (['create', 'edit', 'index', 'show', 'store', 'update', 'destroy', 'approve', 'change-status', 'conflicts', 'conflicts.more'] as $ability) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.'.$ability]));
        }

        $this->inventorySubjectId = $this->subject('019001', 'Inventory');
        $this->beginningInventorySubjectId = $this->subject('067001', 'Beginning inventory');
        config(['amir.beginning_inventory' => (string) $this->beginningInventorySubjectId]);

        $this->mainWarehouse = $this->warehouse('Main');
        $this->otherWarehouse = $this->warehouse('Other');
        $this->product = $this->product('P-1', 125);
        $this->setStock($this->product, $this->mainWarehouse, 2, 175);
        $this->product->update(['quantity' => 2]);
    }

    public function test_pending_beginning_inventory_does_not_change_stock_or_cost(): void
    {
        $documentCount = Document::count();
        $transactionCount = Transaction::count();
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, false);

        $this->assertSame(InvoiceStatus::PENDING, $invoice->status);
        $this->assertNull($invoice->customer_id);
        $this->assertNull($invoice->document_id);
        $this->assertSame($documentCount, Document::count());
        $this->assertSame($transactionCount, Transaction::count());
        $this->assertQuantity($this->product, $this->mainWarehouse, 2);
        $this->assertEqualsWithDelta(125, (float) $this->product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(175, $this->stockCost($this->product, $this->mainWarehouse), 0.001);
    }

    public function test_approval_creates_a_balanced_document_with_the_configured_subject_and_unapproval_reverses_it(): void
    {
        $product = $this->product('P-2', 0);
        $this->setStock($product, $this->mainWarehouse, 0, 0);
        $invoice = $this->createBeginningInventory($product, 5, $this->mainWarehouse, 100, false);

        (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertNotNull($invoice->document_id);
        $this->assertDatabaseHas('documents', ['id' => $invoice->document_id]);
        $this->assertDatabaseHas('transactions', [
            'document_id' => $invoice->document_id,
            'subject_id' => $this->inventorySubjectId,
            'value' => -500,
        ]);
        $this->assertDatabaseHas('transactions', [
            'document_id' => $invoice->document_id,
            'subject_id' => $this->beginningInventorySubjectId,
            'value' => 500,
        ]);
        $this->assertEqualsWithDelta(0, (float) $invoice->document->transactions()->sum('value'), 0.001);
        $this->assertQuantity($product, $this->mainWarehouse, 5);
        $this->assertEqualsWithDelta(100, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(100, $this->stockCost($product, $this->mainWarehouse), 0.001);
        $this->assertEqualsWithDelta(100, (float) $invoice->items->first()->fresh()->cog_after, 0.001);

        $documentId = $invoice->document_id;
        (new InvoiceService)->changeInvoiceStatus($invoice, 'unapproved');

        $this->assertSame(InvoiceStatus::UNAPPROVED, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->document_id);
        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        $this->assertDatabaseMissing('transactions', ['document_id' => $documentId]);
        $this->assertQuantity($product, $this->mainWarehouse, 0);
        $this->assertEqualsWithDelta(0, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(0, $this->stockCost($product, $this->mainWarehouse), 0.001);
    }

    public function test_approved_beginning_inventory_updates_each_warehouse_cost_independently(): void
    {
        $product = $this->product('P-2', 0);
        $this->setStock($product, $this->mainWarehouse, 0, 0);
        $this->setStock($product, $this->otherWarehouse, 0, 0);

        $mainInventory = $this->createBeginningInventory($product, 5, $this->mainWarehouse, 100, true, 1);
        $otherInventory = $this->createBeginningInventory($product, 5, $this->otherWarehouse, 300, true, 2);

        $this->assertNotSame($mainInventory->document_id, $otherInventory->document_id);
        $this->assertSame(2, Transaction::query()->where('subject_id', $this->beginningInventorySubjectId)->count());
        $this->assertEqualsWithDelta(10, (float) $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(200, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(100, $this->stockCost($product, $this->mainWarehouse), 0.001);
        $this->assertEqualsWithDelta(300, $this->stockCost($product, $this->otherWarehouse), 0.001);
    }

    public function test_later_buy_with_equal_number_uses_approved_beginning_inventory_snapshot(): void
    {
        $this->assertLaterBuyCost(1, 1);
    }

    public function test_later_buy_with_lower_number_uses_approved_beginning_inventory_snapshot(): void
    {
        $this->assertLaterBuyCost(2, 1);
    }

    public function test_approved_beginning_inventory_cannot_be_unapproved_after_a_later_approved_buy_with_equal_number(): void
    {
        [$opening] = $this->openingThenBuy(1, 1);
        $decision = InvoiceService::getChangeStatusDecision($opening->fresh(), 'unapproved');

        $this->assertFalse($decision->canProceed);
        $this->assertTrue($decision->hasErrors());
    }

    public function test_editing_an_approved_beginning_inventory_reverses_old_values_and_approves_replacement(): void
    {
        $replacement = $this->product('P-2', 0);
        $this->setStock($replacement, $this->otherWarehouse, 0, 0);
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, true);

        InvoiceService::updateInvoice(
            $invoice->id,
            $this->invoiceData($this->otherWarehouse, 2),
            [$this->item($replacement, 3, 800)],
            true
        );

        $this->assertQuantity($this->product, $this->mainWarehouse, 2);
        $this->assertQuantity($replacement, $this->otherWarehouse, 3);
        $this->assertEqualsWithDelta(800, (float) $replacement->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(800, $this->stockCost($replacement, $this->otherWarehouse), 0.001);
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->document_id);
    }

    public function test_approval_requires_the_beginning_inventory_subject_config(): void
    {
        config(['amir.beginning_inventory' => null]);
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, false);

        try {
            (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');
            $this->fail('Approval should fail when the beginning inventory subject is not configured.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('beginning_inventory', $exception->errors());
        }

        $this->assertSame(InvoiceStatus::PENDING, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->document_id);
        $this->assertQuantity($this->product, $this->mainWarehouse, 2);
    }

    public function test_recalculation_only_includes_approved_beginning_inventory(): void
    {
        $this->createBeginningInventory($this->product, 6, $this->mainWarehouse, 900, true);
        $pendingProduct = $this->product('P-2', 0);
        $this->setStock($pendingProduct, $this->otherWarehouse, 0, 0);
        $this->createBeginningInventory($pendingProduct, 4, $this->otherWarehouse, 500, false, 2);

        $this->product->update(['quantity' => 0]);
        $pendingProduct->update(['quantity' => 0]);
        WarehouseProductStock::query()->update(['quantity' => 0]);

        $this->assertEqualsWithDelta(6, ProductService::recalculateQuantity($this->product->fresh()), 0.001);
        $this->assertEqualsWithDelta(0, ProductService::recalculateQuantity($pendingProduct->fresh()), 0.001);
    }

    public function test_only_one_beginning_inventory_record_is_allowed_per_warehouse(): void
    {
        $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, false);

        $this->expectException(ValidationException::class);
        $this->createBeginningInventory($this->product, 3, $this->mainWarehouse, 800, false, 2);
    }

    public function test_form_accepts_product_lines_without_customer_document_discount_or_vat(): void
    {
        $request = StoreInvoiceRequest::create('/invoices', 'POST', [
            'title' => 'Beginning inventory',
            'date' => convertToJalali(now(), true),
            'invoice_type' => 'beginning_inventory',
            'invoice_number' => 1,
            'warehouse_id' => $this->mainWarehouse->id,
            'transactions' => [[
                'item_id' => 'product-'.$this->product->id,
                'quantity' => 5,
                'unit' => 900,
                'total' => 4500,
            ]],
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();

        $this->assertNull($request->validated('customer_id'));
        $this->assertNull($request->validated('document_number'));
        $this->assertSame('product', $request->validated('transactions.0.item_type'));
    }

    public function test_invoice_type_tampering_is_rejected_on_update(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, false);
        $response = $this->from(route('invoices.edit', $invoice))->put(route('invoices.update', $invoice), [
            'title' => 'Tampered',
            'date' => convertToJalali(now(), true),
            'invoice_type' => 'sell',
            'invoice_id' => $invoice->id,
            'invoice_number' => 2,
            'customer_id' => Customer::create(['name' => 'Customer', 'fiscal_year_id' => $this->fiscalYear->id])->id,
            'document_number' => 20,
            'warehouse_id' => $this->otherWarehouse->id,
            'transactions' => [[
                'item_id' => 'product-'.$this->product->id,
                'quantity' => 3,
                'unit' => 100,
                'off' => 0,
                'vat' => 0,
                'total' => 300,
            ]],
        ]);

        $response->assertRedirect(route('invoices.edit', $invoice));
        $response->assertSessionHasErrors(['invoice_type']);
        $this->assertSame(InvoiceType::BEGINNING_INVENTORY, $invoice->fresh()->invoice_type);
    }

    public function test_show_page_has_status_actions_and_no_purchase_only_sections(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, false);

        $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee($this->mainWarehouse->name)
            ->assertSee(__('Approve'))
            ->assertDontSee(__('Customer Details'))
            ->assertDontSee(__('Ancillary Costs'))
            ->assertDontSee(__('Payments'));
    }

    public function test_index_page_has_beginning_inventory_status_action(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, false);

        $this->get(route('invoices.index', ['invoice_type' => 'beginning_inventory']))
            ->assertOk()
            ->assertSee(route('invoices.change-status', [$invoice, 'approved']), false)
            ->assertSee(__('Approve'));

        (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');

        $this->get(route('invoices.index', ['invoice_type' => 'beginning_inventory']))
            ->assertOk()
            ->assertSee(route('invoices.change-status', [$invoice, 'unapproved']), false)
            ->assertSee(__('Unapprove'));
    }

    public function test_create_link_stays_enabled_while_a_warehouse_has_no_beginning_inventory(): void
    {
        $this->get(route('invoices.index', ['invoice_type' => 'beginning_inventory']))
            ->assertOk()
            ->assertSee(route('invoices.create', ['invoice_type' => 'beginning_inventory']), false);

        $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, false);

        $this->get(route('invoices.index', ['invoice_type' => 'beginning_inventory']))
            ->assertOk()
            ->assertSee(route('invoices.create', ['invoice_type' => 'beginning_inventory']), false);
    }

    public function test_create_is_disabled_when_all_warehouses_have_records_even_when_the_list_is_filtered(): void
    {
        $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, false);
        $other = $this->createBeginningInventory($this->product, 3, $this->otherWarehouse, 800, false, 2);
        $other->update(['status' => InvoiceStatus::UNAPPROVED]);

        // An unused warehouse in another fiscal year must not enable creation here.
        Warehouse::create(['name' => 'Foreign', 'code' => 'FOREIGN', 'fiscal_year_id' => FiscalYear::factory()->create()->id]);

        $this->get(route('invoices.index', [
            'invoice_type' => 'beginning_inventory',
            'status' => 'approved',
            'number' => 999,
        ]))
            ->assertOk()
            ->assertDontSee(route('invoices.create', ['invoice_type' => 'beginning_inventory']), false)
            ->assertSee('<button type="button" class="btn btn-primary" disabled aria-disabled="true">', false);

        $this->delete(route('invoices.destroy', $other))->assertRedirect();

        $this->get(route('invoices.index', ['invoice_type' => 'beginning_inventory']))
            ->assertOk()
            ->assertSee(route('invoices.create', ['invoice_type' => 'beginning_inventory']), false);
    }

    public function test_create_is_disabled_without_any_warehouses(): void
    {
        WarehouseProductStock::query()->delete();
        Warehouse::query()->delete();

        $this->get(route('invoices.index', ['invoice_type' => 'beginning_inventory']))
            ->assertOk()
            ->assertDontSee(route('invoices.create', ['invoice_type' => 'beginning_inventory']), false);
    }

    public function test_direct_creation_rejects_a_duplicate_without_changing_stock_or_cost(): void
    {
        $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, false);
        $invoiceCount = Invoice::count();

        $this->post(route('invoices.store'), [
            'title' => 'Duplicate opening',
            'date' => convertToJalali(now(), true),
            'invoice_type' => 'beginning_inventory',
            'invoice_number' => 2,
            'warehouse_id' => $this->mainWarehouse->id,
            'transactions' => [[
                'item_id' => 'product-'.$this->product->id,
                'quantity' => 3,
                'unit' => 800,
                'total' => 2400,
            ]],
        ])->assertSessionHasErrors('warehouse_id');

        $this->assertSame($invoiceCount, Invoice::count());
        $this->assertQuantity($this->product, $this->mainWarehouse, 2);
        $this->assertEqualsWithDelta(125, (float) $this->product->fresh()->average_cost, 0.001);
    }

    public function test_late_opening_and_correction_rebuild_costs_documents_ancillary_costs_and_payments(): void
    {
        [$product, $customer] = $this->costingContext();
        $buy = $this->createApprovedInvoice($product, $customer, InvoiceType::BUY, $this->mainWarehouse, 10, 100, 20, '2026-01-02');
        $ancillary = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $customer->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'date' => '2026-01-02',
            'type' => 'Shipping',
            'amount' => 300,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $product->id, 'amount' => 300]],
        ], true)['ancillaryCost'];
        $this->assertSame(InvoiceStatus::APPROVED, $ancillary->status);
        $sale = $this->createApprovedInvoice($product, $customer, InvoiceType::SELL, $this->mainWarehouse, 4, 500, 1, '2026-01-02');
        $cashParent = $this->subject('010', 'Cash');
        $cash = Subject::create(['code' => '010001', 'name' => 'Settlement', 'parent_id' => $cashParent, 'type' => SubjectType::BOTH, 'fiscal_year_id' => $this->fiscalYear->id]);
        foreach ([$buy, $sale] as $invoice) {
            $decision = app(PaymentService::class)->createPayment($this->user, $invoice->fresh(), [
                'amount' => app(PaymentService::class)->payableAmount($invoice->fresh()),
                'subject_id' => $cash->id,
            ]);
            $this->assertFalse($decision->hasErrors(), $decision->toText());
            $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
        }
        $opening = $this->createBeginningInventory($product, 10, $this->mainWarehouse, 300, false, 1, '2026-01-01');
        $oldDocumentIds = [$buy->document_id, $sale->document_id, $ancillary->document_id, ...$buy->payments()->pluck('document_id'), ...$sale->payments()->pluck('document_id')];

        app(GroupActionService::class)->inactivateDependentInvoices($opening);
        // The opening was a draft; group deactivation must not invent approval history.
        $this->assertSame(InvoiceStatus::PENDING, $opening->fresh()->status);
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $buy->fresh()->status);
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $sale->fresh()->status);
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $ancillary->fresh()->status);
        $this->assertEqualsWithDelta(0, (float) $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(0, (float) $product->fresh()->average_cost, 0.001);
        $this->assertSame(0, Document::whereIn('id', $oldDocumentIds)->count());
        (new InvoiceService)->changeInvoiceStatus($opening->fresh(), 'approved');
        app(GroupActionService::class)->approveInactiveInvoices();
        $this->assertRebuiltOpeningWorkflow($product, $buy, $sale, $ancillary, 215, 16);

        // The same workflow supports correction of an already posted opening.
        app(GroupActionService::class)->inactivateDependentInvoices($opening->fresh());
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $opening->fresh()->status);
        InvoiceService::updateInvoice($opening->id, [
            ...$this->invoiceData($this->mainWarehouse, 1), 'date' => '2026-01-01',
        ], [$this->item($product, 10, 500)], true);
        app(GroupActionService::class)->approveInactiveInvoices();
        $this->assertRebuiltOpeningWorkflow($product, $buy, $sale, $ancillary, 315, 16);
    }

    private function assertRebuiltOpeningWorkflow(Product $product, Invoice $buy, Invoice $sale, AncillaryCost $ancillary, float $cost, float $quantity): void
    {
        $this->assertQuantity($product, $this->mainWarehouse, $quantity);
        $this->assertEqualsWithDelta($cost, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta($cost, $this->stockCost($product, $this->mainWarehouse), 0.001);
        $this->assertEqualsWithDelta($cost, (float) $sale->fresh('items')->items->first()->cog_after, 0.001);
        $this->assertSame(InvoiceStatus::PAID, $buy->fresh()->status);
        $this->assertSame(InvoiceStatus::PAID, $sale->fresh()->status);
        $this->assertSame(InvoiceStatus::APPROVED, $ancillary->fresh()->status);
        $this->assertDatabaseHas('transactions', ['document_id' => $sale->fresh()->document_id, 'subject_id' => $product->cogs_subject_id, 'value' => -4 * $cost]);
        foreach (Document::with('transactions')->get() as $document) {
            $this->assertEqualsWithDelta(0, (float) $document->transactions->sum('value'), 0.001);
        }
        $this->assertNotNull($buy->payments()->first()->document_id);
        $this->assertNotNull($sale->payments()->first()->document_id);
    }

    public function test_editing_an_approved_purchase_keeps_beginning_inventory_in_its_cost_inputs(): void
    {
        [, $buy, $product] = $this->openingThenBuy(1, 1);

        InvoiceService::updateInvoice($buy->id, [
            'title' => 'Replacement purchase',
            'date' => '2026-01-02',
            'invoice_type' => InvoiceType::BUY,
            'customer_id' => $buy->customer_id,
            'warehouse_id' => $this->mainWarehouse->id,
            'document_number' => 2,
            'number' => 1,
        ], [$this->item($product, 10, 300)], true);

        $item = $buy->fresh('items')->items->first();
        $this->assertEqualsWithDelta(5, (float) $item->quantity_at, 0.001);
        $this->assertEqualsWithDelta(15, (float) $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(233.33, (float) $product->fresh()->average_cost, 0.01);
        $this->assertEqualsWithDelta(233.33, $this->stockCost($product, $this->mainWarehouse), 0.01);
    }

    public function test_purchase_reversal_restores_opening_cost_after_an_ordinary_save(): void
    {
        [, $buy, $product] = $this->openingThenBuy(1, 1);
        InvoiceService::updateInvoice($buy->id, [
            'title' => $buy->title, 'date' => '2026-01-02', 'invoice_type' => InvoiceType::BUY,
            'customer_id' => $buy->customer_id, 'warehouse_id' => $buy->warehouse_id, 'number' => $buy->number,
        ], [$this->item($product, 5, 200)]);

        (new InvoiceService)->changeInvoiceStatus($buy->fresh(), 'unapproved');

        $this->assertEqualsWithDelta(100, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(5, (float) $product->fresh()->quantity, 0.001);
    }

    public function test_approved_beginning_inventory_cannot_be_deleted(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, true);

        $this->delete(route('invoices.destroy', $invoice))
            ->assertRedirect(route('invoices.index', ['invoice_type' => 'beginning_inventory']));

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertQuantity($this->product, $this->mainWarehouse, 7);
    }

    public function test_posted_movements_across_all_warehouses_and_products_block_opening_approval(): void
    {
        [$product, $customer] = $this->costingContext();
        $approvedOpening = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 100, true, 1, '2026-01-01');
        $buy = $this->createApprovedInvoice($product, $customer, InvoiceType::BUY, $this->otherWarehouse, 5, 200, 1, '2026-01-02');
        $opening = $this->createBeginningInventory($this->product, 5, $this->otherWarehouse, 100, false, 2, '2026-01-01');
        foreach (InvoiceStatus::approvedOrSettled() as $status) {
            $buy->update(['status' => $status]);
            $decision = InvoiceService::getChangeStatusDecision($opening->fresh(), 'approved');
            $this->assertTrue($decision->hasErrors());
            $this->assertTrue($decision->conflicts->contains('id', $buy->id));
            $this->assertOpeningRejected(fn () => (new InvoiceService)->changeInvoiceStatus($opening->fresh(), 'approved'));
            $this->assertOpeningRejected(fn () => (new InvoiceService)->changeInvoiceStatus($approvedOpening->fresh(), 'unapproved'));
            $this->assertOpeningRejected(fn () => InvoiceService::updateInvoice($approvedOpening->id,
                [...$this->invoiceData($this->mainWarehouse, 1), 'date' => '2026-01-01'], [$this->item($this->product, 10, 100)], true));
        }
        $this->assertSame(InvoiceStatus::PENDING, $opening->fresh()->status);
        $this->assertNull($opening->fresh()->document_id);
        $this->assertQuantity($this->product, $this->mainWarehouse, 7);
    }

    public function test_draft_opening_and_normal_drafts_can_be_saved_but_posting_is_checked_later(): void
    {
        [$product, $customer] = $this->costingContext();
        $buy = $this->createApprovedInvoice($product, $customer, InvoiceType::BUY, $this->otherWarehouse, 5, 200, 1, '2026-01-02');
        $opening = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 100, false, 1, '2026-01-01');
        InvoiceService::updateInvoice($opening->id, [...$this->invoiceData($this->mainWarehouse, 1), 'date' => '2026-01-01'], [$this->item($this->product, 6, 100)]);
        $this->assertSame(InvoiceStatus::PENDING, $opening->fresh()->status);
        (new InvoiceService)->changeInvoiceStatus($buy->fresh(), 'unapproved');
        (new InvoiceService)->changeInvoiceStatus($opening->fresh(), 'approved');
        $this->assertQuantity($this->product, $this->mainWarehouse, 8);
        $this->assertSame(InvoiceStatus::UNAPPROVED, $buy->fresh()->status);
    }

    public function test_posted_movements_block_approved_opening_edits_and_reversal_in_service_and_http(): void
    {
        [$opening, $buy, $product] = $this->openingThenBuy(1, 2);
        $documentId = $opening->document_id;
        foreach ([false, true] as $approve) {
            $this->assertOpeningRejected(fn () => InvoiceService::updateInvoice($opening->id,
                [...$this->invoiceData($this->mainWarehouse, 1), 'date' => '2026-01-01'], [$this->item($product, 20, 500)], $approve));
        }
        $this->assertOpeningRejected(fn () => (new InvoiceService)->changeInvoiceStatus($opening->fresh(), 'unapproved'));
        $this->post(route('invoices.change-status', [$opening, 'unapproved']))->assertRedirect();
        $this->assertSame(InvoiceStatus::APPROVED, $opening->fresh()->status);
        $this->assertSame($documentId, $opening->fresh()->document_id);
        $this->assertEqualsWithDelta(10, (float) $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(150, (float) $product->fresh()->average_cost, 0.001);
    }

    public function test_same_day_opening_requires_an_earlier_date_even_after_movements_are_deactivated(): void
    {
        [$product, $customer] = $this->costingContext();
        $buy = $this->createApprovedInvoice($product, $customer, InvoiceType::BUY, $this->mainWarehouse, 5, 200, 1, '2026-01-02');
        $opening = $this->createBeginningInventory($product, 5, $this->mainWarehouse, 100, false, 1, '2026-01-02');
        app(GroupActionService::class)->inactivateDependentInvoices($opening);
        $this->assertOpeningRejected(fn () => (new InvoiceService)->changeInvoiceStatus($opening->fresh(), 'approved'));
        InvoiceService::updateInvoice($opening->id, [...$this->invoiceData($this->mainWarehouse, 1), 'date' => '2026-01-01'], [$this->item($product, 5, 100)], true);
        app(GroupActionService::class)->approveInactiveInvoices();
        $this->assertSame(InvoiceStatus::APPROVED, $buy->fresh()->status);
        $this->assertEqualsWithDelta(150, (float) $product->fresh()->average_cost, 0.001);
    }

    public function test_normal_movements_cannot_be_approved_or_backdated_to_or_before_an_opening(): void
    {
        [$opening, $buy, $product] = $this->openingThenBuy(1, 2);
        foreach (['2025-12-31', '2026-01-01'] as $date) {
            foreach ([false, true] as $approve) {
                $this->assertOpeningRejected(fn () => InvoiceService::updateInvoice($buy->id, [
                    'title' => 'Backdated buy', 'date' => $date, 'invoice_type' => InvoiceType::BUY, 'customer_id' => $buy->customer_id,
                    'warehouse_id' => $buy->warehouse_id, 'number' => $buy->number,
                ], [$this->item($product, 5, 200)], $approve));
            }
        }
        $this->assertSame('2026-01-02', $buy->fresh()->date->toDateString());
        (new InvoiceService)->changeInvoiceStatus($buy->fresh(), 'unapproved');
        InvoiceService::updateInvoice($buy->id, [
            'title' => 'Backdated buy', 'date' => '2026-01-01', 'invoice_type' => InvoiceType::BUY, 'customer_id' => $buy->customer_id,
            'warehouse_id' => $buy->warehouse_id, 'number' => $buy->number,
        ], [$this->item($product, 5, 200)]);
        $this->assertTrue(InvoiceService::getChangeStatusDecision($buy->fresh(), 'approved')->hasErrors());
        $this->assertOpeningRejected(fn () => (new InvoiceService)->changeInvoiceStatus($buy->fresh(), 'approved'));
        $buy->update(['status' => InvoiceStatus::APPROVED_INACTIVE]);
        $this->expectException(ValidationException::class);
        app(GroupActionService::class)->approveInactiveInvoices();
    }

    public function test_backdated_movement_conflict_pages_render_customerless_opening(): void
    {
        [$opening, $buy, $product] = $this->openingThenBuy(1, 2);
        (new InvoiceService)->changeInvoiceStatus($buy->fresh(), 'unapproved');
        InvoiceService::updateInvoice($buy->id, [
            'title' => 'Backdated buy', 'date' => '2026-01-01', 'invoice_type' => InvoiceType::BUY,
            'customer_id' => $buy->customer_id, 'warehouse_id' => $buy->warehouse_id, 'number' => $buy->number,
        ], [$this->item($product, 5, 200)]);

        $this->assertNull($opening->customer_id);
        foreach ([
            route('invoices.conflicts', $buy),
            route('invoices.conflicts.more', [$buy, 'invoices']),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee(route('invoices.show', $opening), false)
                ->assertSee(InvoiceType::BEGINNING_INVENTORY->label());
        }
    }

    public function test_opening_conflict_pages_render_later_customerless_opening(): void
    {
        $opening = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 100, true, 1, '2026-01-01');
        $laterOpening = $this->createBeginningInventory($this->product, 5, $this->otherWarehouse, 200, true, 2, '2026-01-02');

        $this->assertNull($laterOpening->customer_id);
        foreach ([
            route('invoices.conflicts', $opening),
            route('invoices.conflicts.more', [$opening, 'invoices']),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee(route('invoices.show', $laterOpening), false)
                ->assertDontSee('/customers/', false);
        }
    }

    public function test_opening_conflict_pages_keep_customer_links_for_ordinary_movements(): void
    {
        [$opening, $buy] = $this->openingThenBuy(1, 2);

        foreach ([
            route('invoices.conflicts', $opening),
            route('invoices.conflicts.more', [$opening, 'invoices']),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee(route('invoices.show', $buy), false)
                ->assertSee(route('customers.show', $buy->customer), false)
                ->assertSee($buy->customer->name);
        }
    }

    public function test_other_fiscal_years_do_not_block_opening_inventory(): void
    {
        [$product, $customer] = $this->costingContext();
        $buy = $this->createApprovedInvoice($product, $customer, InvoiceType::BUY, $this->otherWarehouse, 5, 200, 1, '2026-01-02');
        $buy->update(['fiscal_year_id' => FiscalYear::factory()->create()->id]);
        $opening = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 100, true, 1, '2026-01-01');
        $this->assertSame(InvoiceStatus::APPROVED, $opening->status);
    }

    public function test_group_approval_blocks_an_inactive_opening_while_movements_exist(): void
    {
        [$opening, $buy] = $this->openingThenBuy(1, 2);
        $other = $this->createBeginningInventory($this->product, 5, $this->otherWarehouse, 100, false, 2, '2026-01-01');
        $other->update(['status' => InvoiceStatus::APPROVED_INACTIVE]);
        $this->expectException(ValidationException::class);
        app(GroupActionService::class)->approveInactiveInvoices();
    }

    public function test_second_warehouse_opening_requires_deactivation_and_preserves_purchase_reversal_and_ancillary_costs(): void
    {
        [$product, $customer] = $this->costingContext();
        $this->createBeginningInventory($product, 5, $this->mainWarehouse, 100, true, 1, '2026-01-01');
        $buy = $this->createApprovedInvoice($product, $customer, InvoiceType::BUY, $this->mainWarehouse, 5, 200, 1, '2026-01-02');
        $opening = $this->createBeginningInventory($product, 5, $this->otherWarehouse, 300, false, 2, '2026-01-01');
        $this->assertOpeningRejected(fn () => (new InvoiceService)->changeInvoiceStatus($opening->fresh(), 'approved'));
        app(GroupActionService::class)->inactivateDependentInvoices($opening);
        (new InvoiceService)->changeInvoiceStatus($opening->fresh(), 'approved');
        app(GroupActionService::class)->approveInactiveInvoices();
        $this->assertEqualsWithDelta(15, (float) $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(200, (float) $product->fresh()->average_cost, 0.001);
        $ancillary = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id, 'customer_id' => $customer->id, 'fiscal_year_id' => $this->fiscalYear->id,
            'date' => '2026-01-02', 'type' => 'Shipping', 'amount' => 300, 'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $product->id, 'amount' => 300]],
        ], true)['ancillaryCost'];
        $this->assertSame(InvoiceStatus::APPROVED, $ancillary->status);
        $this->assertEqualsWithDelta(220, (float) $product->fresh()->average_cost, 0.001);
        (new AncillaryCostService)->changeAncillaryCostStatus($ancillary, 'unapprove');
        $this->assertEqualsWithDelta(200, (float) $product->fresh()->average_cost, 0.001);
        (new InvoiceService)->changeInvoiceStatus($buy->fresh(), 'unapproved');
        $this->assertEqualsWithDelta(10, (float) $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(200, (float) $product->fresh()->average_cost, 0.001);
    }

    public function test_group_reactivation_of_ancillary_cost_only_does_not_post_the_purchase_twice(): void
    {
        [, $buy, $product] = $this->openingThenBuy(1, 2);
        $documentId = $buy->document_id;
        $ancillary = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id, 'customer_id' => $buy->customer_id, 'fiscal_year_id' => $this->fiscalYear->id,
            'date' => '2026-01-02', 'type' => 'Shipping', 'amount' => 300, 'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $product->id, 'amount' => 300]],
        ], true)['ancillaryCost'];
        (new AncillaryCostService)->changeAncillaryCostStatus($ancillary, 'unapprove');
        $ancillary->update(['status' => InvoiceStatus::APPROVED_INACTIVE]);
        app(GroupActionService::class)->approveInactiveInvoices();
        $this->assertEqualsWithDelta(10, (float) $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(180, (float) $product->fresh()->average_cost, 0.001);
        $this->assertSame($documentId, $buy->fresh()->document_id);
        $this->assertSame(InvoiceStatus::APPROVED, $ancillary->fresh()->status);
    }

    public function test_direct_create_with_approval_is_rejected_atomically_while_movements_are_posted(): void
    {
        [$product, $customer] = $this->costingContext();
        $this->createApprovedInvoice($product, $customer, InvoiceType::BUY, $this->otherWarehouse, 5, 200, 1, '2026-01-02');
        $invoiceCount = Invoice::count();
        $documentCount = Document::count();
        $this->assertOpeningRejected(fn () => $this->createBeginningInventory($product, 5, $this->mainWarehouse, 100, true, 1, '2026-01-01'));
        $this->assertSame($invoiceCount, Invoice::count());
        $this->assertSame($documentCount, Document::count());
        $this->assertEqualsWithDelta(5, (float) $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(200, (float) $product->fresh()->average_cost, 0.001);
    }

    private function assertOpeningRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Opening order validation should reject this action.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('beginning_inventory', $exception->errors());
        }
    }

    private function costingContext(): array
    {
        $product = $this->product('ORDER-COST', 0);
        $product->update([
            'cogs_subject_id' => $this->subject('070001', 'COGS'),
            'income_subject_id' => $this->subject('050003', 'Sales revenue'),
        ]);
        $this->setStock($product, $this->mainWarehouse, 0, 0);
        $this->setStock($product, $this->otherWarehouse, 0, 0);
        $customer = Customer::create([
            'name' => 'Counterparty', 'fiscal_year_id' => $this->fiscalYear->id,
            'subject_id' => $this->subject('012001', 'Counterparty'),
        ]);

        return [$product, $customer];
    }

    private function assertLaterBuyCost(int $openingNumber, int $buyNumber): void
    {
        [, $buy, $product] = $this->openingThenBuy($openingNumber, $buyNumber);

        $this->assertSame(InvoiceStatus::APPROVED, $buy->status);
        $this->assertEqualsWithDelta(150, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(150, $this->stockCost($product, $this->mainWarehouse), 0.001);
    }

    private function openingThenBuy(int $openingNumber, int $buyNumber): array
    {
        $product = $this->product('COST-'.$openingNumber.'-'.$buyNumber, 0);
        $this->setStock($product, $this->mainWarehouse, 0, 0);
        $opening = $this->createBeginningInventory($product, 5, $this->mainWarehouse, 100, true, $openingNumber, '2026-01-01');
        $subjectId = DB::table('subjects')->insertGetId([
            'fiscal_year_id' => $this->fiscalYear->id,
            'parent_id' => null,
            'code' => '100',
            'name' => 'Inventory test subject',
            'type' => DB::getDriverName() === 'sqlite'
                ? SubjectType::BOTH->valueName()
                : SubjectType::BOTH->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $product->update(['inventory_subject_id' => $subjectId]);
        $customer = Customer::create([
            'name' => 'Supplier',
            'fiscal_year_id' => $this->fiscalYear->id,
            'subject_id' => $subjectId,
        ]);

        $buy = InvoiceService::createInvoice($this->user, [
            'title' => 'Later buy',
            'date' => '2026-01-02',
            'invoice_type' => InvoiceType::BUY,
            'customer_id' => $customer->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'document_number' => 2,
            'number' => $buyNumber,
        ], [$this->item($product, 5, 200)], true)['invoice'];

        return [$opening, $buy, $product];
    }

    private function createBeginningInventory(Product $product, float $quantity, Warehouse $warehouse, float $unit, bool $approved, int $number = 1, ?string $date = null): Invoice
    {
        $data = $this->invoiceData($warehouse, $number);
        $data['date'] = $date ?? now()->toDateString();
        $result = InvoiceService::createInvoice($this->user, $data, [$this->item($product, $quantity, $unit)], $approved);

        return Invoice::withoutGlobalScopes()->with('items')->findOrFail($result['invoice']->id);
    }

    private function createApprovedInvoice(Product $product, Customer $customer, InvoiceType $type, Warehouse $warehouse, float $quantity, float $unit, int $number, string $date): Invoice
    {
        $invoice = InvoiceService::createInvoice($this->user, [
            'title' => $type->label(),
            'date' => $date,
            'invoice_type' => $type,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'document_number' => $number,
            'number' => $number,
        ], [$this->item($product, $quantity, $unit)], true)['invoice'];

        if ($type === InvoiceType::SELL) {
            (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');
        }

        return $invoice->fresh('items');
    }

    private function invoiceData(Warehouse $warehouse, int $number): array
    {
        return [
            'title' => 'Beginning inventory',
            'date' => now()->toDateString(),
            'invoice_type' => InvoiceType::BEGINNING_INVENTORY,
            'customer_id' => null,
            'warehouse_id' => $warehouse->id,
            'document_number' => null,
            'number' => $number,
            'description' => 'Initial stock',
        ];
    }

    private function item(Product $product, float $quantity, float $unit): array
    {
        return [
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_discount' => 0,
            'vat' => 0,
        ];
    }

    private function warehouse(string $name): Warehouse
    {
        return Warehouse::create(['fiscal_year_id' => $this->fiscalYear->id, 'name' => $name, 'code' => strtoupper($name)]);
    }

    private function product(string $code, float $averageCost): Product
    {
        return Product::create([
            'code' => $code,
            'name' => $code,
            'quantity' => 0,
            'quantity_warning' => 0,
            'oversell' => false,
            'selling_price' => 0,
            'vat' => 0,
            'average_cost' => $averageCost,
            'inventory_subject_id' => $this->inventorySubjectId,
            'fiscal_year_id' => $this->fiscalYear->id,
        ]);
    }

    private function subject(string $code, string $name): int
    {
        $existingId = DB::table('subjects')
            ->where('fiscal_year_id', $this->fiscalYear->id)
            ->where('code', $code)
            ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        return DB::table('subjects')->insertGetId([
            'fiscal_year_id' => $this->fiscalYear->id,
            'parent_id' => null,
            'code' => $code,
            'name' => $name,
            'type' => DB::getDriverName() === 'sqlite'
                ? SubjectType::BOTH->valueName()
                : SubjectType::BOTH->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function setStock(Product $product, Warehouse $warehouse, float $quantity, float $averageCost): void
    {
        WarehouseProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
            'average_cost' => $averageCost,
        ]);
    }

    private function assertQuantity(Product $product, Warehouse $warehouse, float $quantity): void
    {
        $this->assertEqualsWithDelta($quantity, (float) $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta($quantity, (float) WarehouseProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity'), 0.001);
    }

    private function stockCost(Product $product, Warehouse $warehouse): float
    {
        return (float) WarehouseProductStock::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('average_cost');
    }
}
