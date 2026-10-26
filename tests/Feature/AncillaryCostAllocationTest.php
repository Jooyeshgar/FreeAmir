<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Http\Controllers\AncillaryCostController;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AncillaryCostAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_products_preserve_fractional_quantities_and_exclude_discount_and_vat(): void
    {
        $year = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $year->id]);
        $product = Product::factory()->withGroup()->create(['weight' => 1.125]);
        $unweighed = Product::factory()->withGroup()->create();
        $invoice = Invoice::create([
            'number' => 434, 'date' => now(), 'invoice_type' => InvoiceType::BUY,
            'fiscal_year_id' => $year->id, 'amount' => 0, 'vat' => 0, 'subtraction' => 0,
        ]);
        $invoice->items()->create([
            'itemable_type' => Product::class, 'itemable_id' => $product->id,
            'quantity' => 1.25, 'unit_price' => 100, 'unit_discount' => 25,
            'vat' => 10, 'amount' => 110,
        ]);
        $invoice->items()->create([
            'itemable_type' => Product::class, 'itemable_id' => $unweighed->id,
            'quantity' => 2.5, 'unit_price' => 100, 'unit_discount' => 0, 'vat' => 0, 'amount' => 250,
        ]);

        $products = app(AncillaryCostController::class)->getBuyInvoiceProducts($invoice->id)->getData(true)['products'];
        $this->assertCount(2, $products);
        $this->assertEquals(1.25, $products[0]['quantity']);
        $this->assertEquals(100, $products[0]['value']);
        $this->assertEquals(1.125, $products[0]['weight']);
        $this->assertEquals(0, $products[1]['weight']);
    }

    public function test_create_and_update_requests_accept_nonnegative_weights_with_at_most_three_decimals(): void
    {
        foreach ([new StoreProductRequest, new UpdateProductRequest] as $request) {
            foreach (['0', '0.001', '12.345'] as $weight) {
                $this->assertTrue(Validator::make(['weight' => $weight], ['weight' => $request->rules()['weight']])->passes());
            }
            foreach (['-1', 'invalid', '0.1234'] as $weight) {
                $this->assertTrue(Validator::make(['weight' => $weight], ['weight' => $request->rules()['weight']])->fails());
            }
        }
    }

    public function test_fiscal_year_product_import_preserves_weight_and_defaults_old_backups_to_zero(): void
    {
        $year = FiscalYear::factory()->create();
        config(['active-fiscal-year-id' => $year->id]);
        $group = ProductGroup::factory()->withSubjects()->create();
        $product = Product::factory()->withGroup($group)->withSubjects()->create(['weight' => 1.125]);
        $subjects = collect([$product->sales_returns_subject_id, $product->income_subject_id, $product->cogs_subject_id, $product->inventory_subject_id]);
        $subjectMapping = $subjects->mapWithKeys(fn ($id) => [$id => $id])->all();
        $method = new \ReflectionMethod(FiscalYearService::class, '_importProducts');
        $data = $product->toArray();
        $data['code'] = 'WEIGHT-COPY';
        $mapping = $method->invoke(null, [$data], $year->id, [$group->id => $group->id], $subjectMapping);
        $this->assertSame('1.125', Product::findOrFail($mapping[$product->id])->weight);

        unset($data['weight']);
        $data['code'] = 'OLD-COPY';
        $mapping = $method->invoke(null, [$data], $year->id, [$group->id => $group->id], $subjectMapping);
        $this->assertSame('0.000', Product::findOrFail($mapping[$product->id])->weight);
    }
}
