<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\CostOfGoodsService;
use App\Services\InvoiceService;
use Cookie;
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

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        Cookie::queue('active-company-id', $company->id);
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->customer = Customer::factory()->withGroup()->withSubject()->create();
    }

    /* -----------------------------------------------------------------
     | Helpers
     | -----------------------------------------------------------------
     */

    private function createProduct(array $overrides = []): Product
    {
        return Product::factory()->withGroup()->withSubjects()->create(array_merge([
            'company_id' => $this->companyId,
        ], $overrides));
    }

    private function createInvoice(InvoiceType $type, array $items, bool $approved = true, ?int $number = null, $date = null): array
    {
        $number ??= random_int(1000, 9999);

        return InvoiceService::createInvoice(
            $this->user,
            [
                'title' => $type === InvoiceType::BUY ? 'Buy Invoice' : 'Sell Invoice',
                'date' => $date ?? now()->toDateString(),
                'invoice_type' => $type,
                'customer_id' => $this->customer->id,
                'document_number' => $number,
                'number' => $number,
            ],
            $items,
            $approved
        );
    }

    private function buy(array $items, bool $approved = true, ?int $number = null, $date = null): array
    {
        return $this->createInvoice(InvoiceType::BUY, $items, $approved, $number, $date);
    }

    private function sell(array $items, bool $approved = true, ?int $number = null, $date = null): array
    {
        return $this->createInvoice(InvoiceType::SELL, $items, $approved, $number, $date);
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
     | Helpers for status/CRUD operations (to be moved to parent)
     | -----------------------------------------------------------------
     */

    /**
     * Un-approve an invoice (set status to unapproved and run related logic).
     */
    protected function unapproveInvoice(Invoice $invoice): void
    {
        $svc = new \App\Services\InvoiceService;
        $svc->changeInvoiceStatus($invoice, 'unapproved');
        $invoice->refresh();
    }

    /**
     * approve an invoice (set status to unapproved and run related logic).
     */
    protected function approveInvoice(Invoice $invoice): void
    {
        $svc = new \App\Services\InvoiceService;
        $svc->changeInvoiceStatus($invoice, 'approved');
        $invoice->refresh();
    }

    /**
     * Un-approve a given ancillary cost record.
     */
    protected function approveAncillaryCost(AncillaryCost $ancillaryCost): void
    {
        $svc = new AncillaryCostService;
        $svc->changeAncillaryCostStatus($ancillaryCost, 'approved');
        $ancillaryCost->refresh();
    }

    /**
     * Un-approve a given ancillary cost record.
     */
    protected function unapproveAncillaryCost(AncillaryCost $ancillaryCost): void
    {
        $svc = new AncillaryCostService;
        $svc->changeAncillaryCostStatus($ancillaryCost, 'unapprove');
        $ancillaryCost->refresh();
    }

    /**
     * Update an invoice using the InvoiceService helper.
     * Returns ['document' => Document|null, 'invoice' => Invoice]
     */
    protected function updateInvoice(Invoice $invoice, array $data, array $items = [], bool $approved = false): array
    {
        return InvoiceService::updateInvoice($invoice->id, $data, $items, $approved);
    }

    /**
     * Edit an invoice by supplying new items and optionally approving it.
     * Returns ['document' => Document|null, 'invoice' => Invoice]
     */
    protected function editInvoice(Invoice $invoice, array $newItems, bool $approved = true): array
    {
        $data = [
            'title' => $invoice->title,
            'date' => $invoice->date,
            'invoice_type' => $invoice->invoice_type,
            'customer_id' => $invoice->customer_id,
            'document_number' => $invoice->document_number,
            'number' => $invoice->number,
        ];

        return $this->updateInvoice($invoice, $data, $newItems, $approved);
    }

    /**
     * Delete an invoice and its related document/transactions.
     */
    protected function deleteInvoice(Invoice $invoice): void
    {
        InvoiceService::deleteInvoice($invoice->id);
    }

    /* -----------------------------------------------------------------
     | Tests
     | -----------------------------------------------------------------
     */

    public function test_single_purchase_sets_initial_average_cost(): void
    {
        $product = $this->createProduct();

        $invoice = $this->buy([$this->productItem($product, 10, 100)])['invoice'];
        $product->refresh();

        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);

        $item = $invoice->items->first();
        $this->assertEqualsWithDelta($product->average_cost, $item->cog_after, 0.0001);
    }

    public function test_multiple_purchases_recalculate_moving_average(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 1001);
        $this->buy([$this->productItem($product, 5, 120)], true, 1002);

        $product->refresh();

        $expected = round(((100 * 10) + (120 * 5)) / 15, 2);

        $this->assertEquals(15, $product->quantity);
        $this->assertEqualsWithDelta($expected, $product->average_cost, 0.01);
    }

    public function test_purchase_then_sale_uses_average_for_cogs(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 2001);

        $sale = $this->sell([$this->productItem($product, 2, 250)], true, 2002)['invoice'];

        $product->refresh();
        $this->assertEquals(8, $product->quantity);

        $item = $sale->items->first();
        $this->assertEqualsWithDelta(100, $item->cog_after, 0.01);

        $this->assertEqualsWithDelta(
            (250 - 100) * 2,
            CostOfGoodsService::calculateGrossProfit($item),
            0.0001
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
        $this->assertEquals(7, $product->quantity);
        $this->assertEqualsWithDelta($avg, $product->average_cost, 0.0001);
    }

    public function test_ancillary_cost_updates_average_cost(): void
    {
        $product = $this->createProduct();

        $invoice = $this->buy([$this->productItem($product, 10, 100)], true, 4001)['invoice'];

        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => now()->toDateString(),
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 100],
            ],
        ], true);

        $product->refresh();
        $this->assertEqualsWithDelta(110, $product->average_cost, 0.01);
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
        $this->assertEquals(0, $product->quantity);
        $this->assertEqualsWithDelta(0.0, $product->average_cost, 0.0001);
    }

    public function test_unapproving_buy_invoice_reverses_inventory_and_recalculates_average_cost(): void
    {
        $product = $this->createProduct();

        $inv1 = $this->buy([$this->productItem($product, 10, 100)], true, random_int(6000, 6999))['invoice'];
        $inv2 = $this->buy([$this->productItem($product, 10, 120)], true, random_int(7000, 7999))['invoice'];

        $product->refresh();
        $this->assertEquals(20, $product->quantity);
        $this->assertEqualsWithDelta(110, $product->average_cost, 0.01);

        // Un-approve second invoice and expect a rollback to the previous state
        $this->unapproveInvoice($inv2);
        $product->refresh();

        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);
    }

    public function test_unapproving_sell_invoice_restores_inventory_and_average_cost(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 20, 100)], true, random_int(8000, 8999));
        $sale = $this->sell([$this->productItem($product, 8, 200)], true, random_int(9000, 9999))['invoice'];

        $product->refresh();
        $this->assertEquals(12, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);

        // Un-approve the sale and expect inventory to be restored
        $this->unapproveInvoice($sale);
        $product->refresh();

        $this->assertEquals(20, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);
    }

    public function test_unapproving_ancillary_cost_reverses_average_cost_adjustment(): void
    {
        $product = $this->createProduct();

        $invoice = $this->buy([$this->productItem($product, 10, 100)], true, random_int(10000, 10999))['invoice'];

        $result = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => now()->toDateString(),
            'type' => 'Shipping',
            'amount' => 50,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 50],
            ],
        ], true);

        $ancillary = $result['ancillaryCost'];

        $product->refresh();
        $this->assertEqualsWithDelta(105, $product->average_cost, 0.01);

        // Un-approve ancillary cost and expect average to revert
        $this->unapproveAncillaryCost($ancillary);
        $product->refresh();

        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);
        $this->assertEquals(10, $product->quantity);
    }

    public function test_editing_buy_invoice_quantity_recalculates_moving_average(): void
    {
        $product = $this->createProduct();

        // Increase quantity from 10 to 15 at same unit price
        $invoice = $this->buy([$this->productItem($product, 10, 100)], true, random_int(11000, 11999))['invoice'];
        $this->unapproveInvoice($invoice);
        $this->editInvoice($invoice, [$this->productItem($product, 15, 100)], true);

        $product->refresh();
        $this->assertEquals(15, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);

        // Alternative: change price of second invoice and expect new average
        $product2 = $this->createProduct();
        $inv1 = $this->buy([$this->productItem($product2, 10, 100)], true, random_int(12000, 12999))['invoice'];
        $inv2 = $this->buy([$this->productItem($product2, 10, 120)], true, random_int(13000, 13999))['invoice'];

        $product2->refresh();
        $this->assertEqualsWithDelta(110, $product2->average_cost, 0.01);
        $this->unapproveInvoice($inv2);

        // Edit second invoice's price to 140
        $this->editInvoice($inv2, [$this->productItem($product2, 10, 140)], true);
        $product2->refresh();

        $this->assertEquals(20, $product2->quantity);
        // Expected average: ((100*10) + (140*10)) / 20 = 120
        $this->assertEqualsWithDelta(120, $product2->average_cost, 0.01);
    }

    public function test_editing_sell_invoice_maintains_cogs_integrity(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 20, 100)], true, random_int(14000, 14999));
        $sale = $this->sell([$this->productItem($product, 10, 200)], true, random_int(15000, 15999))['invoice'];

        $product->refresh();
        $this->assertEquals(10, $product->quantity);
        $this->unapproveInvoice($sale);

        // Edit sale: reduce quantity to 5
        $this->editInvoice($sale, [$this->productItem($product, 5, 200)], true);
        $product->refresh();

        $this->assertEquals(15, $product->quantity);

        $updatedSale = Invoice::find($sale->id);
        $item = $updatedSale->items->first();
        $this->assertEqualsWithDelta(100, $item->cog_after, 0.01);
    }

    public function test_buy_sell_rebuy_maintains_accurate_moving_average(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, random_int(16000, 16999));
        $sale1 = $this->sell([$this->productItem($product, 5, 200)], true, random_int(17000, 17999))['invoice'];

        $product->refresh();
        $this->assertEquals(5, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);

        $this->buy([$this->productItem($product, 10, 140)], true, random_int(18000, 18999));

        // Expected avg after rebuy: ((100*5) + (140*10)) / 15
        $expectedAvg = round(((100 * 5) + (140 * 10)) / 15, 2);

        $product->refresh();
        $this->assertEquals(15, $product->quantity);
        $this->assertEqualsWithDelta($expectedAvg, $product->average_cost, 0.01);

        $sale2 = $this->sell([$this->productItem($product, 7, 250)], true, random_int(19000, 19999))['invoice'];
        $product->refresh();
        $this->assertEquals(8, $product->quantity);

        $firstSaleItem = $sale1->items->first();
        $secondSaleItem = $sale2->items->first();

        $this->assertEqualsWithDelta(100, $firstSaleItem->cog_after, 0.01);
        $this->assertEqualsWithDelta($expectedAvg, $secondSaleItem->cog_after, 0.01);
    }

    public function test_unapproving_middle_transaction_recalculates_subsequent_averages(): void
    {
        $product = $this->createProduct();

        $inv1 = $this->buy([$this->productItem($product, 10, 100)], true, random_int(20000, 20999))['invoice'];
        $inv2 = $this->buy([$this->productItem($product, 10, 120)], true, random_int(21000, 21999), now()->addDays(1))['invoice'];
        $inv3 = $this->buy([$this->productItem($product, 10, 140)], true, random_int(22000, 22999), now()->addDays(5))['invoice'];

        $product->refresh();
        $this->assertEquals(30, $product->quantity);
        $this->assertEqualsWithDelta(120, $product->average_cost, 0.01);

        // Un-approve the middle invoice and expect recalculation for subsequent transactions
        $this->unapproveInvoice($inv3);
        $this->unapproveInvoice($inv2);
        $product->refresh();

        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);

        $inv4 = $this->buy([$this->productItem($product, 10, 400)], true, random_int(25000, 25999), now()->addDays(2))['invoice'];
        $this->unapproveInvoice($inv4);
        $this->sell([$this->productItem($product, 5, 200)], true, random_int(23000, 23999), now()->addDays(2))['invoice'];
        $this->approveInvoice($inv4);

        $product->refresh();
        $this->assertEquals(15, $product->quantity);
        $this->assertEqualsWithDelta(300, $product->average_cost, 0.01);

        $inv5 = $this->buy([$this->productItem($product, 10, 450)], true, random_int(26000, 26999), now()->addDays(3))['invoice'];
        $this->unapproveInvoice($inv5);
        $this->sell([$this->productItem($product, 5, 300)], true, random_int(24000, 24999), now()->addDays(3))['invoice'];
        $this->approveInvoice($inv5);

        $product->refresh();
        $this->assertEquals(20, $product->quantity);
        $this->assertEqualsWithDelta(375, $product->average_cost, 0.01);

        $this->buy([$this->productItem($product, 10, 600)], true, random_int(27000, 27999), now()->addDays(4))['invoice'];
        $this->sell([$this->productItem($product, 5, 650)], true, random_int(28000, 28999), now()->addDays(4))['invoice'];
        $inv6 = $this->sell([$this->productItem($product, 5, 750)], false, random_int(27000, 27999), now()->addDays(5))['invoice'];

        $product->refresh();
        $this->assertEquals(25, $product->quantity);
        $this->assertEqualsWithDelta(450, $product->average_cost, 0.01);

        $this->editInvoice($inv3, [$this->productItem($product, 5, 210)], false);
        $inv3->refresh();

        $this->approveInvoice($inv3);
        $this->approveInvoice($inv6);
        $product->refresh();

        $this->assertEquals(25, $product->quantity);
        $this->assertEqualsWithDelta(410, $product->average_cost, 0.01);
    }

    public function test_editing_invoice_with_ancillary_costs_maintains_cost_allocation(): void
    {
        $product = $this->createProduct();

        $invoice = $this->buy([$this->productItem($product, 10, 100)], true, random_int(23000, 23999))['invoice'];

        $result = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => now()->toDateString(),
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 100],
            ],
        ], true);

        $product->refresh();
        $this->assertEqualsWithDelta(110, $product->average_cost, 0.01);
        $this->unapproveInvoice($invoice);

        // Edit invoice to increase quantity to 20 — ancillary cost should be distributed proportionally
        $this->editInvoice($invoice, [$this->productItem($product, 20, 100)], true);
        $this->approveAncillaryCost($result['ancillaryCost']);

        $product->refresh();
        $this->assertEquals(20, $product->quantity);
        // Expected avg = (100*20 + 100 ancillary) / 20 = 105
        $this->assertEqualsWithDelta(105, $product->average_cost, 0.01);
    }
}
