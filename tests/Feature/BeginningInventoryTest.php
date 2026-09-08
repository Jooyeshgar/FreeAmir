<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
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
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'invoices.create']),
            Permission::firstOrCreate(['name' => 'invoices.edit']),
            Permission::firstOrCreate(['name' => 'invoices.index']),
            Permission::firstOrCreate(['name' => 'invoices.store']),
            Permission::firstOrCreate(['name' => 'invoices.update']),
            Permission::firstOrCreate(['name' => 'invoices.destroy']),
            Permission::firstOrCreate(['name' => 'products.show']),
        ]);

        $this->mainWarehouse = $this->warehouse('Main');
        $this->otherWarehouse = $this->warehouse('Other');
        $this->product = $this->product('P-1', 125);
        $this->setStock($this->product, $this->mainWarehouse, 2, 175);
        $this->product->update(['quantity' => 2]);
    }

    public function test_create_changes_only_quantities_and_records_a_status_free_invoice(): void
    {
        $documentCount = Document::count();
        $transactionCount = Transaction::count();

        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);

        $this->assertSame(InvoiceType::BEGINNING_INVENTORY, $invoice->invoice_type);
        $this->assertNull($invoice->status);
        $this->assertNull($invoice->customer_id);
        $this->assertNull($invoice->document_id);
        $this->assertSame($documentCount, Document::count());
        $this->assertSame($transactionCount, Transaction::count());
        $this->assertQuantity($this->product, $this->mainWarehouse, 7);
        $this->assertEqualsWithDelta(125, (float) $this->product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(175, $this->stockCost($this->product, $this->mainWarehouse), 0.001);
        $this->assertEqualsWithDelta(900, (float) $invoice->items->first()->unit_price, 0.001);
    }

    public function test_create_records_the_preexisting_quantity_for_product_history(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);

        $this->assertEqualsWithDelta(2, (float) $invoice->items->first()->quantity_at, 0.001);

        $this->get(route('products.show', $this->product))->assertOk()->assertSee(formatNumber(7), false);
    }

    public function test_approved_buy_includes_beginning_inventory_at_the_existing_average_cost(): void
    {
        $product = $this->product('P-2', 100);
        $this->setStock($product, $this->mainWarehouse, 0, 100);
        $this->createBeginningInventory($product, 5, $this->mainWarehouse, 900);

        $subjectId = DB::table('subjects')->insertGetId([
            'company_id' => $this->company->id,
            'parent_id' => null,
            'code' => '100',
            'name' => 'Inventory test subject',
            'type' => 'both',
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
            'date' => now()->toDateString(),
            'invoice_type' => InvoiceType::BUY,
            'customer_id' => $customer->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'document_number' => 2,
            'number' => 2,
        ], [$this->item($product, 5, 200)], true)['invoice'];

        $this->assertEqualsWithDelta(150, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(150, $this->stockCost($product, $this->mainWarehouse), 0.001);

        (new InvoiceService)->changeInvoiceStatus($buy->fresh(), 'unapproved');

        $this->assertEqualsWithDelta(100, (float) $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(100, $this->stockCost($product, $this->mainWarehouse), 0.001);
    }

    public function test_edit_reverses_old_stock_and_applies_replacement_without_changing_costs(): void
    {
        $replacement = $this->product('P-2', 240);
        $this->setStock($replacement, $this->otherWarehouse, 0, 260);
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);

        InvoiceService::updateInvoice(
            $invoice->id,
            $this->invoiceData($this->otherWarehouse, 2),
            [$this->item($replacement, 3, 800)],
            true
        );

        $this->assertQuantity($this->product, $this->mainWarehouse, 2);
        $this->assertQuantity($replacement, $this->otherWarehouse, 3);
        $this->assertEqualsWithDelta(125, (float) $this->product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(240, (float) $replacement->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(260, $this->stockCost($replacement, $this->otherWarehouse), 0.001);
        $this->assertNull($invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->document_id);
    }

    public function test_multiple_beginning_inventory_records_can_initialize_different_warehouses(): void
    {
        $first = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);
        $second = InvoiceService::createInvoice(
            $this->user,
            $this->invoiceData($this->otherWarehouse, 2),
            [$this->item($this->product, 3, 800)],
            true
        )['invoice'];

        $this->assertNotSame($first->id, $second->id);
        $this->assertEqualsWithDelta(10, (float) $this->product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(7, (float) WarehouseProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->mainWarehouse->id)
            ->value('quantity'), 0.001);
        $this->assertEqualsWithDelta(3, (float) WarehouseProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->otherWarehouse->id)
            ->value('quantity'), 0.001);

        $this->get(route('invoices.create', ['invoice_type' => 'beginning_inventory']))->assertOk();
    }

    public function test_only_one_beginning_inventory_record_is_allowed_per_warehouse(): void
    {
        $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);

        try {
            InvoiceService::createInvoice(
                $this->user,
                $this->invoiceData($this->mainWarehouse, 2),
                [$this->item($this->product, 3, 800)],
                true
            );
            $this->fail('A second beginning inventory was created for the same warehouse.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('A beginning inventory already exists for the selected warehouse.'),
                $exception->errors()['warehouse_id'][0]
            );
        }
    }

    public function test_edit_applies_net_delta_after_later_stock_consumes_opening_quantity(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);
        ProductService::addProductsQuantities([
            [...$this->item($this->product, 7, 900), 'warehouse_id' => $this->mainWarehouse->id],
        ], InvoiceType::SELL);

        InvoiceService::updateInvoice(
            $invoice->id,
            $this->invoiceData($this->mainWarehouse, 1),
            [$this->item($this->product, 5, 950)],
            true
        );

        $this->assertQuantity($this->product, $this->mainWarehouse, 0);
        $this->assertEqualsWithDelta(950, (float) $invoice->fresh()->items->first()->unit_price, 0.001);
    }

    public function test_delete_removes_its_contribution_after_later_stock_consumes_opening_quantity(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);
        ProductService::addProductsQuantities([
            [...$this->item($this->product, 7, 900), 'warehouse_id' => $this->mainWarehouse->id],
        ], InvoiceType::SELL);

        InvoiceService::deleteInvoice($invoice->id);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertQuantity($this->product, $this->mainWarehouse, -5);
    }

    public function test_recalculation_includes_beginning_inventory_and_delete_reverses_it(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 6, $this->mainWarehouse, 900);
        $this->product->update(['quantity' => 0]);
        WarehouseProductStock::query()->update(['quantity' => 0]);

        $this->assertEqualsWithDelta(6, ProductService::recalculateQuantity($this->product->fresh()), 0.001);
        $this->assertQuantity($this->product, $this->mainWarehouse, 6);

        InvoiceService::deleteInvoice($invoice->id);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertQuantity($this->product, $this->mainWarehouse, 0);
        $this->assertEqualsWithDelta(125, (float) $this->product->fresh()->average_cost, 0.001);
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

        try {
            $request->validateResolved();
        } catch (ValidationException $exception) {
            $this->fail(json_encode($exception->errors(), JSON_UNESCAPED_UNICODE));
        }

        $this->assertNull($request->validated('customer_id'));
        $this->assertNull($request->validated('document_number'));
        $this->assertSame('product', $request->validated('transactions.0.item_type'));
    }

    public function test_normal_invoice_update_rejects_tampered_beginning_inventory_type(): void
    {
        $customer = Customer::create([
            'name' => 'Customer',
            'company_id' => $this->company->id,
        ]);
        $invoice = Invoice::create([
            'number' => 10,
            'date' => now()->toDateString(),
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::PENDING,
            'customer_id' => $customer->id,
            'creator_id' => $this->user->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 500,
            'title' => 'Sell invoice',
        ]);

        $response = $this->from(route('invoices.edit', $invoice))
            ->put(route('invoices.update', $invoice), [
                'title' => 'Tampered invoice',
                'date' => convertToJalali(now(), true),
                'invoice_type' => 'beginning_inventory',
                'invoice_id' => $invoice->id,
                'invoice_number' => 11,
                'warehouse_id' => $this->otherWarehouse->id,
                'transactions' => [[
                    'item_id' => 'product-'.$this->product->id,
                    'quantity' => 5,
                    'unit' => 100,
                    'total' => 500,
                ]],
            ]);

        $response->assertRedirect(route('invoices.edit', $invoice));
        $response->assertSessionHasErrors(['invoice_type', 'customer_id', 'document_number']);
        $invoice->refresh();
        $this->assertSame(InvoiceType::SELL, $invoice->invoice_type);
        $this->assertSame(InvoiceStatus::PENDING, $invoice->status);
        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertSame($this->mainWarehouse->id, $invoice->warehouse_id);
        $this->assertQuantity($this->product, $this->mainWarehouse, 2);
        $this->assertServiceRejectsInvoiceTypeChange($invoice, InvoiceType::BEGINNING_INVENTORY);
    }

    public function test_beginning_inventory_update_rejects_tampered_normal_type(): void
    {
        $customer = Customer::create([
            'name' => 'Customer',
            'company_id' => $this->company->id,
        ]);
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);

        $response = $this->from(route('invoices.edit', $invoice))
            ->put(route('invoices.update', $invoice), [
                'title' => 'Tampered beginning inventory',
                'date' => convertToJalali(now(), true),
                'invoice_type' => 'sell',
                'invoice_id' => $invoice->id,
                'invoice_number' => 2,
                'customer_id' => $customer->id,
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
        $invoice->refresh();
        $this->assertSame(InvoiceType::BEGINNING_INVENTORY, $invoice->invoice_type);
        $this->assertNull($invoice->status);
        $this->assertNull($invoice->customer_id);
        $this->assertSame($this->mainWarehouse->id, $invoice->warehouse_id);
        $this->assertQuantity($this->product, $this->mainWarehouse, 7);
        $this->assertServiceRejectsInvoiceTypeChange($invoice, InvoiceType::SELL);
    }

    public function test_store_redirects_beginning_inventory_to_index(): void
    {
        $response = $this->post(route('invoices.store'), [
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

        $response->assertRedirect(route('invoices.index', ['invoice_type' => 'beginning_inventory']));
    }

    public function test_index_lists_beginning_inventories_with_edit_and_delete_actions(): void
    {
        $first = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);
        $second = InvoiceService::createInvoice(
            $this->user,
            $this->invoiceData($this->otherWarehouse, 2),
            [$this->item($this->product, 3, 800)],
            true
        )['invoice'];

        $response = $this->get(route('invoices.index', ['invoice_type' => 'beginning_inventory']));

        $response->assertOk();
        $response->assertSee($this->mainWarehouse->name);
        $response->assertSee($this->otherWarehouse->name);
        $response->assertSee(route('invoices.edit', $first), false);
        $response->assertSee(route('invoices.edit', $second), false);
        $response->assertSee('id="delete-beginning-inventory-'.$first->id.'"', false);
        $response->assertSee('id="delete-beginning-inventory-'.$second->id.'"', false);
        $response->assertSee(__('Are you sure?'));
    }

    public function test_edit_form_shows_permission_gated_delete_action_with_confirmation(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);

        $response = $this->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertSee('id="delete-beginning-inventory-form"', false);
        $response->assertSee(__('Are you sure?'));
        $response->assertSee(__('Delete'));

        $this->user->revokePermissionTo('invoices.destroy');

        $this->get(route('invoices.edit', $invoice))
            ->assertOk()
            ->assertDontSee('id="delete-beginning-inventory-form"', false);
    }

    public function test_product_page_renders_beginning_inventory_without_a_status_or_customer(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);

        $response = $this->get(route('products.show', $this->product));

        $response->assertOk();
        $response->assertSee(route('invoices.show', $invoice), false);
        $response->assertSee(formatNumber(5), false);
        $response->assertSee('bg-success/10 hover:bg-success/20', false);
        $response->assertSee('badge badge-success gap-2', false);
    }

    public function test_status_change_decisions_reject_beginning_inventory_without_throwing(): void
    {
        $invoice = $this->createBeginningInventory($this->product, 5, $this->mainWarehouse, 900);

        $automaticDecision = InvoiceService::getChangeStatusValidation($invoice);
        $explicitDecision = InvoiceService::getChangeStatusDecision($invoice, 'approved');

        $this->assertFalse($automaticDecision->canProceed);
        $this->assertTrue($automaticDecision->hasErrors());
        $this->assertFalse($explicitDecision->canProceed);
        $this->assertTrue($explicitDecision->hasErrors());
        $this->assertSame(
            __('Beginning inventory has no status workflow.'),
            $automaticDecision->messages->first()->text
        );
    }

    private function createBeginningInventory(Product $product, float $quantity, Warehouse $warehouse, float $unit): Invoice
    {
        $result = InvoiceService::createInvoice(
            $this->user,
            $this->invoiceData($warehouse, 1),
            [$this->item($product, $quantity, $unit)],
            true
        );

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

    private function assertServiceRejectsInvoiceTypeChange(Invoice $invoice, InvoiceType $invoiceType): void
    {
        try {
            InvoiceService::updateInvoice($invoice->id, ['invoice_type' => $invoiceType]);
            $this->fail('The invoice type was changed through InvoiceService.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('The invoice type cannot be changed.'),
                $exception->errors()['invoice_type'][0]
            );
        }
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
        return Warehouse::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'code' => strtoupper($name),
        ]);
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
