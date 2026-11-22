<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class OversellProductZeroAverageCostApprovalTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private FiscalYear $fiscalYear;

    private User $user;

    private Customer $customer;

    private ProductGroup $productGroup;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fiscalYear = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);

        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);
        $this->actingAs($this->user);

        $this->importSubjects($this->fiscalYear->id);
        $this->importConfigs($this->fiscalYear->id);

        $this->productGroup = ProductGroup::factory()->withSubjects()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
        ]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
        ]);
        $this->customer = Customer::factory()
            ->withGroup($customerGroup)
            ->withSubject()
            ->create(['fiscal_year_id' => $this->fiscalYear->id]);

        $this->warehouse = Warehouse::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'Main',
            'code' => 'MAIN',
        ]);
    }

    public function test_creating_product_defaults_average_cost_to_zero(): void
    {
        $product = app(ProductService::class)->create([
            'code' => '2',
            'name' => 'Oscilloscope',
            'group' => $this->productGroup->id,
            'quantity' => 0,
            'quantity_warning' => 0,
            'selling_price' => 166980000,
            'vat' => 0,
            'oversell' => true,
        ]);

        $this->assertNotNull($product->average_cost);
        $this->assertEquals(0.0, (float) $product->average_cost);
    }

    public function test_approving_sell_invoice_for_oversell_product_with_null_average_cost_sets_cog_after_to_zero(): void
    {
        $product = Product::factory()
            ->withGroup($this->productGroup)
            ->withSubjects()
            ->create([
                'fiscal_year_id' => $this->fiscalYear->id,
                'quantity' => 0,
                'oversell' => true,
            ]);

        $product->forceFill(['average_cost' => null])->save();
        $this->assertNull($product->fresh()->average_cost);

        $created = InvoiceService::createInvoice(
            $this->user,
            [
                'number' => 101,
                'date' => now()->toDateString(),
                'invoice_type' => InvoiceType::SELL,
                'customer_id' => $this->customer->id,
                'warehouse_id' => $this->warehouse->id,
                'subtraction' => 0,
            ],
            [
                [
                    'itemable_id' => $product->id,
                    'itemable_type' => 'product',
                    'quantity' => 1,
                    'unit' => 166980000,
                    'discount' => 0,
                    'vat' => 0,
                    'total' => 166980000,
                ],
            ],
            false
        );

        $invoice = $created['invoice'];

        (new InvoiceService)->changeInvoiceStatus($invoice->fresh(), 'approved');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertEquals(0.0, (float) $invoice->items()->firstOrFail()->cog_after);
    }
}
