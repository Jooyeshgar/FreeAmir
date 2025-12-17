<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Product;
use App\Services\AncillaryCostService;
use App\Services\CostOfGoodsService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for Cost of Goods Sold (COGS) calculations using moving average method.
 *
 * These tests operate mostly at the service level (InvoiceService, AncillaryCostService,
 * CostOfGoodsService) to assert the core accounting rules. A small set of controller
 * validation behavior is covered (sale without enough inventory).
 */
class COGSCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure company/session context exists for factories and services
        session(['active-company-id' => 1]);

        // Common test data
        $this->user = \App\Models\User::factory()->create();
        $this->actingAs($this->user);

        $this->customer = \App\Models\Customer::factory()->create();
    }

    private function createProduct(array $attrs = []): Product
    {
        $data = array_merge([
            'company_id' => session('active-company-id'),
            'code' => 'P-'.rand(1000, 9999),
            'name' => 'Test product '.rand(1000, 9999),
            'quantity' => 0,
            'average_cost' => 0,
        ], $attrs);

        return Product::create($data);
    }

    private function createBuyInvoice(array $items, bool $approved = true, ?int $number = null)
    {
        $invoiceData = [
            'title' => 'Buy Invoice',
            'date' => now()->toDateString(),
            'invoice_type' => InvoiceType::BUY,
            'customer_id' => $this->customer->id,
            'document_number' => $number ?? rand(1000, 9999),
            'number' => $number ?? rand(1000, 9999),
        ];

        return InvoiceService::createInvoice($this->user, $invoiceData, $items, $approved);
    }

    private function createSellInvoice(array $items, bool $approved = true, ?int $number = null)
    {
        $invoiceData = [
            'title' => 'Sell Invoice',
            'date' => now()->toDateString(),
            'invoice_type' => InvoiceType::SELL,
            'customer_id' => $this->customer->id,
            'document_number' => $number ?? rand(1000, 9999),
            'number' => $number ?? rand(1000, 9999),
        ];

        return InvoiceService::createInvoice($this->user, $invoiceData, $items, $approved);
    }

    /**
     * 1. Single purchase → verify initial average cost
     */
    public function test_single_purchase_sets_initial_average_cost()
    {
        $product = $this->createProduct();

        // Buy 10 units at 100 each (no VAT/discount)
        [$doc, $invoice] = $this->createBuyInvoice([
            [
                'itemable_type' => 'product',
                'itemable_id' => $product->id,
                'quantity' => 10,
                'unit' => 100,
                'unit_discount' => 0,
                'vat' => 0,
            ],
        ], true);

        $product->refresh();

        // avg should be (100*10)/10 = 100
        $this->assertEquals(10, $product->quantity, 'Product quantity should be increased by approved purchase');
        $this->assertEquals(100.0, round($product->average_cost, 4), 'Average cost should equal the per-unit cost for the initial purchase');

        // invoice item COG after should be same as product average
        $invoice->refresh();
        $item = $invoice->items->first();
        $this->assertEquals(round($product->average_cost, 4), round($item->cog_after, 4));
    }

    /**
     * 2. Multiple purchases → verify moving average recalculation
     */
    public function test_multiple_purchases_recalculate_moving_average()
    {
        $product = $this->createProduct();

        // First purchase 10 @ 100 -> avg = 100
        $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 10, 'unit' => 100, 'unit_discount' => 0, 'vat' => 0],
        ], true, 1001);

        // Second purchase 5 @ 120 -> new avg = (100*10 + 120*5) / 15 = 106.66666...
        $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 5, 'unit' => 120, 'unit_discount' => 0, 'vat' => 0],
        ], true, 1002);

        $product->refresh();

        $expected = ((100 * 10) + (120 * 5)) / 15; // 106.666...

        $this->assertEquals(15, $product->quantity);
        $this->assertEquals(round($expected, 6), round($product->average_cost, 6));
    }

    /**
     * 3. Purchase then sale → verify COGS calculation at current average
     */
    public function test_purchase_then_sale_uses_moving_average_for_cogs()
    {
        $product = $this->createProduct();

        // Buy 10 @ 100
        $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 10, 'unit' => 100, 'unit_discount' => 0, 'vat' => 0],
        ], true, 2001);

        $product->refresh();
        $this->assertEquals(10, $product->quantity);
        $this->assertEquals(100, round($product->average_cost, 4));

        // Sell 2 @ 250 -> COGS should be 2 * 100
        [$doc, $sellInvoice] = $this->createSellInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 2, 'unit' => 250, 'unit_discount' => 0, 'vat' => 0],
        ], true, 2002);

        $product->refresh();
        $this->assertEquals(8, $product->quantity, 'Quantity should reduce after approved sale');

        $sellItem = $sellInvoice->items()->first();
        $this->assertEquals(round(100, 4), round($sellItem->cog_after, 4), 'Sale COG per unit must equal the moving average at time of sale');

        // Gross profit check: (250 - 100) * 2
        $gp = CostOfGoodsService::calculateGrossProfit($sellItem);
        $this->assertEquals((250 - 100) * 2, $gp);
    }

    /**
     * 4. Multiple purchases and sales → moving average remains correct through sales
     */
    public function test_multiple_purchases_and_sales_keep_average_consistent()
    {
        $product = $this->createProduct();

        // Buy 10@100
        $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 10, 'unit' => 100, 'unit_discount' => 0, 'vat' => 0],
        ], true, 3001);

        // Buy 5@120 -> avg ~106.6666
        $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 5, 'unit' => 120, 'unit_discount' => 0, 'vat' => 0],
        ], true, 3002);

        $product->refresh();
        $expectedAvg = ((100 * 10) + (120 * 5)) / 15;
        $this->assertEquals(round($expectedAvg, 6), round($product->average_cost, 6));

        // Sell 8 units (should use same avg for COGS and reduce qty to 7)
        [$d, $sellInvoice] = $this->createSellInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 8, 'unit' => 400, 'unit_discount' => 0, 'vat' => 0],
        ], true, 3003);

        $product->refresh();
        $this->assertEquals(7, $product->quantity);

        // Average cost should remain unchanged by sales
        $this->assertEquals(round($expectedAvg, 6), round($product->average_cost, 6));
    }

    /**
     * 5. Ancillary cost allocation updates product average cost
     */
    public function test_ancillary_cost_allocation_updates_average_cost()
    {
        $product = $this->createProduct();

        // Buy 10 @ 100 -> base avg 100
        [$d1, $inv1] = $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 10, 'unit' => 100, 'unit_discount' => 0, 'vat' => 0],
        ], true, 4001);

        $product->refresh();
        $this->assertEquals(100, round($product->average_cost, 4));

        // Create an ancillary cost 100 allocated to this product and approve it
        $ancillaryData = [
            'invoice_id' => $inv1->id,
            'customer_id' => $this->customer->id,
            'company_id' => session('active-company-id'),
            'date' => now()->toDateString(),
            'type' => 'transport', // one of enum values
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 100],
            ],
        ];

        $result = AncillaryCostService::createAncillaryCost($this->user, $ancillaryData, true);

        $product->refresh();

        // Expected average = (baseCost 1000 + ancillary 100) / 10 = 110
        $this->assertEquals(110, round($product->average_cost, 4));

        // Invoice item cog_after (synchronized after ancillary approval) should update too
        $inv1->refresh();
        $item = $inv1->items()->first();
        $this->assertEquals(round($product->average_cost, 4), round($item->cog_after, 4));
    }

    /**
     * 6. Purchase return: delete a buy invoice -> inventory and average recalc
     *
     * We simulate a 'purchase return' by deleting the later buy invoice and invoking
     * the service which refreshes COG after deletions.
     */
    public function test_purchase_return_reduces_inventory_and_resets_average()
    {
        $product = $this->createProduct();

        // Buy #1: 10@100
        [$d1, $inv1] = $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 10, 'unit' => 100, 'unit_discount' => 0, 'vat' => 0],
        ], true, 5001);

        // Buy #2: 5@120
        [$d2, $inv2] = $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 5, 'unit' => 120, 'unit_discount' => 0, 'vat' => 0],
        ], true, 5002);

        $product->refresh();
        $this->assertEquals(15, $product->quantity);

        // Now delete the second invoice (simulate return from purchase)
        InvoiceService::deleteInvoice($inv2->id);

        $product->refresh();

        // Quantity should be reduced back to 10 and average reset to original 100
        $this->assertEquals(10, $product->quantity);
        $this->assertEquals(100, round($product->average_cost, 4));
    }

    /**
     * 7. Sales return: add inventory back at original sale COGS
     *
     * We simulate a sales return by creating a compensating BUY (return) at sale's COG.
     * This test asserts the rule that returned goods re-enter inventory at their original COG.
     */
    public function test_sales_return_adds_inventory_at_original_cogs()
    {
        $product = $this->createProduct();

        // Buy 10@100
        [$d1, $b] = $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 10, 'unit' => 100, 'unit_discount' => 0, 'vat' => 0],
        ], true, 6001);

        // Sell 4 @ 200 (COG per unit = 100)
        [$d2, $s] = $this->createSellInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 4, 'unit' => 200, 'unit_discount' => 0, 'vat' => 0],
        ], true, 6002);

        $product->refresh();
        $this->assertEquals(6, $product->quantity);

        $saleItem = $s->items()->first();
        $this->assertEquals(100, round($saleItem->cog_after, 4));

        // Now simulate a sales return: create a buy (return) adding 2 units at the original sale COG (100)
        [$d3, $returnBuy] = $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 2, 'unit' => $saleItem->cog_after, 'unit_discount' => 0, 'vat' => 0],
        ], true, 6003);

        $product->refresh();
        $this->assertEquals(8, $product->quantity, 'Inventory should increase by returned quantity');

        // Average cost should be updated using moving average formula with the return priced at sale COG
        $expectedInventoryValue = (6 * 100) + (2 * 100); // remaining inventory previous value + returned value
        $expectedQty = 6 + 2;
        $expectedAvg = $expectedInventoryValue / $expectedQty;
        $this->assertEquals(round($expectedAvg, 6), round($product->average_cost, 6));
    }

    /**
     * 8. Complex scenario: mix of purchases, sales, returns, and ancillary costs
     */
    public function test_complex_scenario_mixed_transactions()
    {
        $p = $this->createProduct();

        // Buy A: 10@100
        $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $p->id, 'quantity' => 10, 'unit' => 100, 'unit_discount' => 0, 'vat' => 0],
        ], true, 7001);

        // Buy B: 10@120
        $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $p->id, 'quantity' => 10, 'unit' => 120, 'unit_discount' => 0, 'vat' => 0],
        ], true, 7002);

        // Ancillary cost applied to Buy B: 200
        $lastBuy = \App\Models\Invoice::where('number', 7002)->first();
        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $lastBuy->id,
            'customer_id' => $this->customer->id,
            'company_id' => session('active-company-id'),
            'date' => now()->toDateString(),
            'type' => 'transport',
            'amount' => 200,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $p->id, 'amount' => 200]],
        ], true);

        $p->refresh();

        // Check that average cost accounts for both buys and the ancillary allocation
        $expected = ((100 * 10) + (120 * 10) + 200) / 20;
        $this->assertEquals(round($expected, 6), round($p->average_cost, 6));

        // Sell 5 units
        $this->createSellInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $p->id, 'quantity' => 5, 'unit' => 300, 'unit_discount' => 0, 'vat' => 0],
        ], true, 7003);

        $p->refresh();
        $this->assertEquals(15, $p->quantity);

        // Delete Buy B to simulate a purchase return (affects avg and qty)
        InvoiceService::deleteInvoice($lastBuy->id);

        $p->refresh();

        // After deletion, only Buy A and ancillary should remain (but ancillary was linked to deleted invoice -> its document deleted as well)
        // Expect quantity 5 and avg reverts to Buy A cost (100)
        $this->assertEquals(5, $p->quantity);
        $this->assertEquals(100, round($p->average_cost, 4));
    }

    /**
     * 9. Zero inventory then purchase → verify average resets properly
     */
    public function test_zero_inventory_then_purchase_resets_average()
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 50]);

        // Buying should reset average to the new purchase if there's no available quantity
        $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 5, 'unit' => 200, 'unit_discount' => 0, 'vat' => 0],
        ], true, 9001);

        $product->refresh();
        $this->assertEquals(5, $product->quantity);
        $this->assertEquals(200, round($product->average_cost, 4));
    }

    /**
     * 10. Edge case: Sale without sufficient inventory (should fail/validation)
     */
    public function test_sale_without_sufficient_inventory_fails_validation()
    {
        $this->withoutMiddleware(); // bypass permission middleware

        $product = $this->createProduct(['quantity' => 1, 'average_cost' => 100]);

        // Construct POST payload that tries to create an approved sell for 5 units
        $payload = [
            'title' => 'Sale',
            'date' => now()->toDateString(),
            'invoice_type' => 'sell',
            'customer_id' => $this->customer->id,
            'document_number' => 11111,
            'invoice_number' => 22222,
            'approve' => 1,
            'transactions' => [
                [
                    'item_id' => "product-{$product->id}",
                    'item_type' => 'product',
                    'quantity' => 5,
                    'unit' => 100,
                    'vat' => 0,
                    'desc' => 'test',
                    'off' => 0,
                    'total' => 500,
                ],
            ],
        ];

        $response = $this->post('/invoices', $payload);

        // Controller validation should redirect back with errors in the session
        $response->assertSessionHasErrors('transactions.0.quantity');
    }

    /**
     * Ensure unapproved invoices do not change inventory
     */
    public function test_unapproved_invoice_does_not_affect_inventory()
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);

        // Create unapproved buy (approved = false)
        $result = $this->createBuyInvoice([
            ['itemable_type' => 'product', 'itemable_id' => $product->id, 'quantity' => 10, 'unit' => 30, 'unit_discount' => 0, 'vat' => 0],
        ], false, 9101);

        $product->refresh();
        $this->assertEquals(0, $product->quantity, 'Unapproved buy must NOT affect inventory');
        $this->assertEquals(0, round($product->average_cost, 4), 'Unapproved buy must NOT affect average cost');
    }
}
