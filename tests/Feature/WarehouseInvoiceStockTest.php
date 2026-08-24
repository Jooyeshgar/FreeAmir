<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class WarehouseInvoiceStockTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private Company $company;

    private User $user;

    private Customer $customer;

    private Product $product;

    private Warehouse $mainWarehouse;

    private Warehouse $emptyWarehouse;

    private int $nextInvoiceNumber = 9000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);

        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);
        $this->actingAs($this->user);

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->company->id]);

        $this->mainWarehouse = $this->warehouse('Main');
        $this->emptyWarehouse = $this->warehouse('Empty');
        $this->product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->mainWarehouse->id,
            'quantity' => 0,
            'average_cost' => 100,
        ]);

        $this->setStock($this->mainWarehouse, 0);
        $this->setStock($this->emptyWarehouse, 0);
    }

    public function test_buy_and_sell_use_selected_warehouse_and_unapproval_reverses_each_change(): void
    {
        $buy = $this->createInvoice(InvoiceType::BUY, 10, $this->mainWarehouse, true);

        $this->assertStock($this->mainWarehouse, 10);
        $this->assertStock($this->emptyWarehouse, 0);

        $sell = $this->createInvoice(InvoiceType::SELL, 4, $this->mainWarehouse, false);
        $this->approve($sell);

        $this->assertStock($this->mainWarehouse, 6);
        $this->unapprove($sell);
        $this->assertStock($this->mainWarehouse, 10);

        $this->unapprove($buy);
        $this->assertStock($this->mainWarehouse, 0);
    }

    public function test_return_invoices_apply_and_reverse_stock_in_the_selected_warehouse(): void
    {
        $buy = $this->createInvoice(InvoiceType::BUY, 10, $this->mainWarehouse, true);
        $sell = $this->createInvoice(InvoiceType::SELL, 6, $this->mainWarehouse, false);
        $this->approve($sell);

        $returnSell = $this->createInvoice(InvoiceType::RETURN_SELL, 2, $this->emptyWarehouse, true, $sell);
        $this->assertStock($this->mainWarehouse, 4);
        $this->assertStock($this->emptyWarehouse, 2);

        $this->unapprove($returnSell);
        $this->assertStock($this->emptyWarehouse, 0);

        $returnBuy = $this->createInvoice(InvoiceType::RETURN_BUY, 3, $this->mainWarehouse, true, $buy);
        $this->assertStock($this->mainWarehouse, 1);

        $this->unapprove($returnBuy);
        $this->assertStock($this->mainWarehouse, 4);
    }

    public function test_sell_approval_checks_the_selected_warehouse_instead_of_product_total(): void
    {
        $this->createInvoice(InvoiceType::BUY, 10, $this->mainWarehouse, true);
        $sell = $this->createInvoice(InvoiceType::SELL, 1, $this->emptyWarehouse, false);

        $decision = InvoiceService::getChangeStatusDecision($sell, InvoiceStatus::APPROVED);

        $this->assertFalse($decision->canProceed);
        $this->assertStock($this->mainWarehouse, 10);
        $this->assertStock($this->emptyWarehouse, 0);
    }

    public function test_approved_sell_form_validation_checks_the_selected_warehouse(): void
    {
        $this->createInvoice(InvoiceType::BUY, 10, $this->mainWarehouse, true);

        $errors = $this->validateForm($this->formPayload(InvoiceType::SELL, 1, $this->emptyWarehouse, true));

        $this->assertArrayHasKey('transactions.0.quantity', $errors);
        $this->assertStock($this->mainWarehouse, 10);
        $this->assertStock($this->emptyWarehouse, 0);
    }

    public function test_approved_sell_edit_validation_restores_only_the_original_selected_warehouse_quantity(): void
    {
        $this->createInvoice(InvoiceType::BUY, 10, $this->mainWarehouse, true);
        $sell = $this->createInvoice(InvoiceType::SELL, 4, $this->mainWarehouse, false);
        $this->approve($sell);

        $payload = $this->formPayload(InvoiceType::SELL, 4, $this->emptyWarehouse, true);
        $errors = $this->validateForm($payload, $sell);
        $this->assertArrayHasKey('transactions.0.quantity', $errors);

        $this->assertSame($this->mainWarehouse->id, $sell->fresh()->items->first()->warehouse_id);
        $this->assertStock($this->mainWarehouse, 6);
        $this->assertStock($this->emptyWarehouse, 0);
    }

    public function test_return_buy_form_validation_checks_selected_warehouse_stock(): void
    {
        $buy = $this->createInvoice(InvoiceType::BUY, 5, $this->mainWarehouse, true);

        $errors = $this->validateForm($this->formPayload(
            InvoiceType::RETURN_BUY,
            1,
            $this->emptyWarehouse,
            true,
            $buy
        ));

        $this->assertArrayHasKey('transactions.0.quantity', $errors);
        $this->assertStock($this->mainWarehouse, 5);
        $this->assertStock($this->emptyWarehouse, 0);
    }

    public function test_invoice_form_rejects_missing_and_cross_company_warehouse(): void
    {
        $foreignCompany = Company::factory()->create();
        $foreignWarehouse = Warehouse::withoutGlobalScopes()->create([
            'company_id' => $foreignCompany->id,
            'name' => 'Foreign',
            'code' => 'FOREIGN',
        ]);

        $missingPayload = $this->formPayload(InvoiceType::BUY, 1, $this->mainWarehouse, false);
        unset($missingPayload['transactions'][0]['warehouse_id']);

        $this->assertArrayHasKey('transactions.0.warehouse_id', $this->validateForm($missingPayload));
        $this->assertArrayHasKey(
            'transactions.0.warehouse_id',
            $this->validateForm($this->formPayload(InvoiceType::BUY, 1, $foreignWarehouse, false))
        );
    }

    public function test_editing_pending_invoice_changes_warehouse_without_changing_stock(): void
    {
        $this->createInvoice(InvoiceType::BUY, 10, $this->mainWarehouse, true);
        $sell = $this->createInvoice(InvoiceType::SELL, 2, $this->mainWarehouse, false);

        InvoiceService::updateInvoice($sell->id, $this->invoiceData(InvoiceType::SELL), [
            $this->item(2, $this->emptyWarehouse),
        ]);

        $this->assertSame($this->emptyWarehouse->id, $sell->fresh()->items->first()->warehouse_id);
        $this->assertStock($this->mainWarehouse, 10);
        $this->assertStock($this->emptyWarehouse, 0);
    }

    public function test_stock_mutation_rejects_an_outgoing_invoice_that_would_make_warehouse_negative(): void
    {
        $sell = $this->createInvoice(InvoiceType::SELL, 1, $this->emptyWarehouse, false);

        $this->expectException(ValidationException::class);

        (new InvoiceService)->changeInvoiceStatus($sell, 'approved');
    }

    public function test_void_restores_and_unapproval_removes_stock_from_original_sell_warehouse(): void
    {
        $this->createInvoice(InvoiceType::BUY, 10, $this->mainWarehouse, true);
        $sell = $this->createInvoice(InvoiceType::SELL, 4, $this->mainWarehouse, false);
        $this->approve($sell);

        $void = (new InvoiceService)->voidInvoice($sell, $this->user, now()->toDateString(), ++$this->nextInvoiceNumber)['invoice'];

        $this->assertSame($this->mainWarehouse->id, $void->items->first()->warehouse_id);
        $this->assertStock($this->mainWarehouse, 10);

        $this->unapprove($void);
        $this->assertStock($this->mainWarehouse, 6);
    }

    private function createInvoice(InvoiceType $type, float $quantity, Warehouse $warehouse, bool $approved, ?Invoice $returnedInvoice = null): Invoice
    {
        $result = InvoiceService::createInvoice(
            $this->user,
            $this->invoiceData($type, $returnedInvoice),
            [$this->item($quantity, $warehouse)],
            $approved
        );

        return Invoice::withoutGlobalScopes()->with('items')->findOrFail($result['invoice']->id);
    }

    private function invoiceData(InvoiceType $type, ?Invoice $returnedInvoice = null): array
    {
        $number = ++$this->nextInvoiceNumber;

        return [
            'title' => $type->valueName(),
            'date' => now()->toDateString(),
            'invoice_type' => $type,
            'customer_id' => $this->customer->id,
            'document_number' => $number,
            'number' => $number,
            'returned_invoice_id' => $returnedInvoice?->id,
        ];
    }

    private function item(float $quantity, Warehouse $warehouse): array
    {
        return [
            'itemable_type' => 'product',
            'itemable_id' => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
            'unit' => 100,
            'unit_discount' => 0,
            'vat' => 0,
        ];
    }

    private function formPayload(
        InvoiceType $type,
        float $quantity,
        Warehouse $warehouse,
        bool $approve,
        ?Invoice $returnedInvoice = null
    ): array {
        $number = ++$this->nextInvoiceNumber;

        return [
            'title' => $type->valueName(),
            'description' => null,
            'date' => now()->toDateString(),
            'invoice_type' => $type->valueName(),
            'customer_id' => $this->customer->id,
            'document_number' => $number,
            'invoice_number' => $number,
            'returned_invoice_id' => $returnedInvoice?->id,
            ...($approve ? ['approve' => '1'] : []),
            'transactions' => [[
                'item_id' => 'product-'.$this->product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => $quantity,
                'unit' => 100,
                'off' => 0,
                'vat' => 0,
                'total' => $quantity * 100,
            ]],
        ];
    }

    private function validateForm(array $payload, ?Invoice $invoice = null): array
    {
        $request = StoreInvoiceRequest::create('/invoices', $invoice ? 'PUT' : 'POST', $payload);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        if ($invoice) {
            $request->setRouteResolver(fn () => new class($invoice)
            {
                public function __construct(private readonly Invoice $invoice) {}

                public function parameter(string $key): ?Invoice
                {
                    return $key === 'invoice' ? $this->invoice : null;
                }
            });
        }

        try {
            $request->validateResolved();
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        return [];
    }

    private function approve(Invoice $invoice): void
    {
        (new InvoiceService)->changeInvoiceStatus($invoice->fresh(), 'approved');
    }

    private function unapprove(Invoice $invoice): void
    {
        (new InvoiceService)->changeInvoiceStatus($invoice->fresh(), 'unapproved');
    }

    private function warehouse(string $name): Warehouse
    {
        return Warehouse::create([
            'company_id' => $this->company->id,
            'name' => $name,
            'code' => strtoupper($name),
        ]);
    }

    private function setStock(Warehouse $warehouse, float $quantity): void
    {
        WarehouseProductStock::updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'product_id' => $this->product->id],
            ['quantity' => $quantity, 'average_cost' => 100]
        );
    }

    private function assertStock(Warehouse $warehouse, float $quantity): void
    {
        $actual = WarehouseProductStock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $this->product->id)
            ->value('quantity');

        $this->assertEqualsWithDelta($quantity, (float) $actual, 0.001);
    }
}
