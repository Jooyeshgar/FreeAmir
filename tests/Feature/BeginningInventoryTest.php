<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SubjectType;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\InvoiceService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BeginningInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Warehouse $mainWarehouse;

    private Warehouse $otherWarehouse;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);
        $this->actingAs($this->user);

        foreach (['create', 'edit', 'index', 'show', 'store', 'update', 'destroy', 'approve'] as $ability) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'invoices.'.$ability]));
        }

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

    public function test_approval_and_unapproval_apply_and_reverse_quantity_and_average_cost_without_a_document(): void
    {
        $product = $this->product('P-2', 0);
        $this->setStock($product, $this->mainWarehouse, 0, 0);
        $invoice = $this->createBeginningInventory($product, 5, $this->mainWarehouse, 100, false);

        (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertNull($invoice->document_id);
        $this->assertQuantity($product, $this->mainWarehouse, 5);
        $this->assertEqualsWithDelta(100, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(100, $this->stockCost($product, $this->mainWarehouse), 0.001);
        $this->assertEqualsWithDelta(100, (float) $invoice->items->first()->fresh()->cog_after, 0.001);

        (new InvoiceService)->changeInvoiceStatus($invoice, 'unapproved');

        $this->assertSame(InvoiceStatus::UNAPPROVED, $invoice->fresh()->status);
        $this->assertQuantity($product, $this->mainWarehouse, 0);
        $this->assertEqualsWithDelta(0, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(0, $this->stockCost($product, $this->mainWarehouse), 0.001);
    }

    public function test_approved_beginning_inventory_updates_each_warehouse_cost_independently(): void
    {
        $product = $this->product('P-2', 0);
        $this->setStock($product, $this->mainWarehouse, 0, 0);
        $this->setStock($product, $this->otherWarehouse, 0, 0);

        $this->createBeginningInventory($product, 5, $this->mainWarehouse, 100, true, 1);
        $this->createBeginningInventory($product, 5, $this->otherWarehouse, 300, true, 2);

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
        $this->assertNull($invoice->fresh()->document_id);
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
            'customer_id' => Customer::create(['name' => 'Customer', 'company_id' => $this->company->id])->id,
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

    public function test_approved_beginning_inventory_cannot_be_deleted(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900, true);

        $this->delete(route('invoices.destroy', $invoice))
            ->assertRedirect(route('invoices.index', ['invoice_type' => 'beginning_inventory']));

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertQuantity($this->product, $this->mainWarehouse, 7);
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
            'company_id' => $this->company->id,
            'parent_id' => null,
            'code' => '100',
            'name' => 'Inventory test subject',
            'type' => SubjectType::BOTH->valueName(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $product->update(['inventory_subject_id' => $subjectId]);
        $customer = Customer::create([
            'name' => 'Supplier',
            'company_id' => $this->company->id,
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
        return Warehouse::create(['company_id' => $this->company->id, 'name' => $name, 'code' => strtoupper($name)]);
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
            'company_id' => $this->company->id,
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
