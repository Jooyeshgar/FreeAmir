<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\CostOfGoodsService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Cost of Goods Sold (COGS) calculations
 * using the moving average inventory method.
 *
 * Scope:
 * - Service-level behavior (InvoiceService, CostOfGoodsService, AncillaryCostService)
 * - One controller-level validation (selling without inventory)
 */
class COGSCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        session(['active-company-id' => Company::factory()->create()->id]);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->customer = Customer::factory()->create();
    }

    /* -----------------------------------------------------------------
     | Helpers
     | -----------------------------------------------------------------
     */

    private function createProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'company_id' => session('active-company-id'),
            'quantity' => 0,
            'average_cost' => 0,
        ], $overrides));
    }

    private function createInvoice(
        InvoiceType $type,
        array $items,
        bool $approved = true,
        ?int $number = null
    ): array {
        $number ??= random_int(1000, 9999);

        return InvoiceService::createInvoice(
            $this->user,
            [
                'title' => $type === InvoiceType::BUY ? 'Buy Invoice' : 'Sell Invoice',
                'date' => now()->toDateString(),
                'invoice_type' => $type,
                'customer_id' => $this->customer->id,
                'document_number' => $number,
                'number' => $number,
            ],
            $items,
            $approved
        );
    }

    private function buy(array $items, bool $approved = true, ?int $number = null): array
    {
        return $this->createInvoice(InvoiceType::BUY, $items, $approved, $number);
    }

    private function sell(array $items, bool $approved = true, ?int $number = null): array
    {
        return $this->createInvoice(InvoiceType::SELL, $items, $approved, $number);
    }

    private function productItem(Product $product, int $qty, float $unit): array
    {
        return [
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => $qty,
            'unit' => $unit,
            'unit_discount' => 0,
            'vat' => 0,
        ];
    }

    /* -----------------------------------------------------------------
     | Tests
     | -----------------------------------------------------------------
     */

    public function test_single_purchase_sets_initial_average_cost(): void
    {
        $product = $this->createProduct();

        [, $invoice] = $this->buy([
            $this->productItem($product, 10, 100),
        ]);

        $product->refresh();

        $this->assertSame(10, $product->quantity);
        $this->assertEquals(100, round($product->average_cost, 4));

        $item = $invoice->items->first();
        $this->assertEquals(
            round($product->average_cost, 4),
            round($item->cog_after, 4)
        );
    }

    public function test_multiple_purchases_recalculate_moving_average(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 1001);
        $this->buy([$this->productItem($product, 5, 120)], true, 1002);

        $product->refresh();

        $expected = ((100 * 10) + (120 * 5)) / 15;

        $this->assertSame(15, $product->quantity);
        $this->assertEquals(round($expected, 6), round($product->average_cost, 6));
    }

    public function test_purchase_then_sale_uses_average_for_cogs(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 2001);

        [, $sale] = $this->sell([
            $this->productItem($product, 2, 250),
        ], true, 2002);

        $product->refresh();
        $this->assertSame(8, $product->quantity);

        $item = $sale->items->first();
        $this->assertEquals(100, round($item->cog_after, 4));

        $this->assertEquals(
            (250 - 100) * 2,
            CostOfGoodsService::calculateGrossProfit($item)
        );
    }

    public function test_sales_do_not_change_average_cost(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 3001);
        $this->buy([$this->productItem($product, 5, 120)], true, 3002);

        $product->refresh();
        $avg = $product->average_cost;

        $this->sell([$this->productItem($product, 8, 400)], true, 3003);

        $product->refresh();
        $this->assertSame(7, $product->quantity);
        $this->assertEquals(round($avg, 6), round($product->average_cost, 6));
    }

    public function test_ancillary_cost_updates_average_cost(): void
    {
        $product = $this->createProduct();

        [, $invoice] = $this->buy([$this->productItem($product, 10, 100)], true, 4001);

        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => session('active-company-id'),
            'date' => now()->toDateString(),
            'type' => 'transport',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 100],
            ],
        ], true);

        $product->refresh();
        $this->assertEquals(110, round($product->average_cost, 4));
    }

    public function test_sale_without_inventory_fails_validation(): void
    {
        $this->withoutMiddleware();

        $product = $this->createProduct(['quantity' => 1, 'average_cost' => 100]);

        $response = $this->post('/invoices', [
            'title' => 'Sale',
            'date' => now()->toDateString(),
            'invoice_type' => 'sell',
            'customer_id' => $this->customer->id,
            'document_number' => 11111,
            'invoice_number' => 22222,
            'approve' => 1,
            'transactions' => [[
                'item_id' => "product-{$product->id}",
                'item_type' => 'product',
                'quantity' => 5,
                'unit' => 100,
                'vat' => 0,
                'off' => 0,
                'total' => 500,
            ]],
        ]);

        $response->assertSessionHasErrors('transactions.0.quantity');
    }

    public function test_unapproved_invoice_does_not_affect_inventory(): void
    {
        $product = $this->createProduct();

        $this->buy([
            $this->productItem($product, 10, 30),
        ], false, 9101);

        $product->refresh();
        $this->assertSame(0, $product->quantity);
        $this->assertSame(0.0, (float) $product->average_cost);
    }
}
