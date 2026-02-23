<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\CostOfGoodsService;
use App\Services\InvoiceService;
use Cookie;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class COGSCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->companyId = Company::query()->orderBy('id')->value('id') ?? 1;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $customerGroup = CustomerGroup::withoutGlobalScopes()->where('company_id', $this->companyId)->firstOrFail();

        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function createProduct(array $overrides = []): Product
    {
        $group = ProductGroup::withoutGlobalScopes()->where('company_id', $this->companyId)->firstOrFail();

        return Product::factory()->withGroup($group)->withSubjects()->create(array_merge(['company_id' => $this->companyId], $overrides));
    }

    private function createInvoice(
        InvoiceType $type,
        array $items,
        bool $approved = true,
        ?int $number = null,
        ?string $date = null,
        ?int $returnedInvoiceId = null
    ): array {
        $number ??= ++$this->nextInvoiceNumber;

        $result = InvoiceService::createInvoice(
            $this->user,
            [
                'title' => strtoupper($type->value).' Invoice',
                'date' => $date ?? now()->toDateString(),
                'invoice_type' => $type,
                'customer_id' => $this->customer->id,
                'document_number' => $number,
                'number' => $number,
                'returned_invoice_id' => $returnedInvoiceId,
            ],
            $items,
            $approved
        );

        $invoice = $this->findInvoice($result['invoice']->id);

        if ($approved && ! $invoice->status->isApproved()) {
            $this->approveInvoice($invoice);
            $invoice = $this->findInvoice($invoice->id);
        }

        return [
            'document' => $result['document'],
            'invoice' => $invoice,
        ];
    }

    private function buy(array $items, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::BUY, $items, $approved, $number, $date);
    }

    private function sell(array $items, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::SELL, $items, $approved, $number, $date);
    }

    private function returnSell(array $items, int $returnedInvoiceId, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::RETURN_SELL, $items, $approved, $number, $date, $returnedInvoiceId);
    }

    private function returnBuy(array $items, int $returnedInvoiceId, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::RETURN_BUY, $items, $approved, $number, $date, $returnedInvoiceId);
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

    private function findProduct(int $productId): Product
    {
        return Product::withoutGlobalScopes()->findOrFail($productId);
    }

    private function findInvoice(int $invoiceId): Invoice
    {
        return Invoice::withoutGlobalScopes()->with('items')->findOrFail($invoiceId);
    }

    private function findInvoiceItem(Invoice $invoice, Product $product): InvoiceItem
    {
        return InvoiceItem::query()
            ->where('invoice_id', $invoice->id)->where('itemable_id', $product->id)
            ->where('itemable_type', Product::class)->firstOrFail();
    }

    private function approveInvoice(Invoice $invoice): void
    {
        $invoice = $this->findInvoice($invoice->id);
        (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');
    }

    private function unapproveInvoice(Invoice $invoice): void
    {
        $invoice = $this->findInvoice($invoice->id);
        (new InvoiceService)->changeInvoiceStatus($invoice, 'unapproved');
    }

    private function approveAncillaryCost(AncillaryCost $ancillaryCost): void
    {
        (new AncillaryCostService)->changeAncillaryCostStatus($ancillaryCost, 'approve');
        $ancillaryCost->refresh();
    }

    private function unapproveAncillaryCost(AncillaryCost $ancillaryCost): void
    {
        (new AncillaryCostService)->changeAncillaryCostStatus($ancillaryCost, 'unapprove');
        $ancillaryCost->refresh();
    }

    private function updateInvoice(Invoice $invoice, array $data, array $items, bool $approved): array
    {
        return InvoiceService::updateInvoice($invoice->id, $data, $items, $approved);
    }

    private function editInvoice(Invoice $invoice, array $newItems, bool $approved = true): array
    {
        $payload = [
            'title' => $invoice->title,
            'date' => $invoice->date instanceof Carbon ? $invoice->date->toDateString() : (string) $invoice->date,
            'invoice_type' => $invoice->invoice_type,
            'customer_id' => $invoice->customer_id,
            'document_number' => $invoice->document?->number,
            'number' => $invoice->number,
            'description' => $invoice->description,
            'subtraction' => $invoice->subtraction ?? 0,
            'permanent' => $invoice->permanent ?? 0,
        ];

        $result = $this->updateInvoice($invoice, $payload, $newItems, $approved);
        $updated = $this->findInvoice($result['invoice']->id);

        if ($approved && ! $updated->status->isApproved()) {
            $this->approveInvoice($updated);
            $updated = $this->findInvoice($updated->id);
        }

        return [
            'document' => $result['document'],
            'invoice' => $updated,
        ];
    }

    public function test_single_purchase_sets_initial_average_cost(): void
    {
        $product = $this->createProduct();

        $invoice = $this->buy([$this->productItem($product, 10, 100)], true, 901, '2026-01-10')['invoice'];
        $product = $this->findProduct($product->id);

        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
        $this->assertEqualsWithDelta(100, $this->findInvoiceItem($invoice, $product)->cog_after, 0.0001);
    }

    public function test_sales_do_not_change_average_cost(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 911, '2026-01-11');
        $this->buy([$this->productItem($product, 5, 120)], true, 912, '2026-01-12');

        $product = $this->findProduct($product->id);
        $averageBeforeSales = (float) $product->average_cost;

        $this->sell([$this->productItem($product, 8, 220)], true, 913, '2026-01-13');

        $product = $this->findProduct($product->id);

        $this->assertEquals(7, $product->quantity);
        $this->assertEqualsWithDelta($averageBeforeSales, $product->average_cost, 0.0001);
    }

    public function test_ancillary_cost_updates_average_cost(): void
    {
        $product = $this->createProduct();
        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 921, '2026-01-14')['invoice'];

        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => '2026-01-15',
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 100],
            ],
        ], true);

        $product = $this->findProduct($product->id);
        $this->assertEqualsWithDelta(110, $product->average_cost, 0.01);
    }

    public function test_sale_without_inventory_fails_validation(): void
    {
        $this->withoutMiddleware();

        $product = $this->createProduct(['quantity' => 1, 'average_cost' => 100]);

        $response = $this->post('/invoices', [
            'title' => 'Sale',
            'date' => '2026-01-16',
            'invoice_type' => 'sell',
            'customer_id' => $this->customer->id,
            'document_number' => 9911,
            'invoice_number' => 9912,
            'approve' => 1,
            'transactions' => [[
                'item_id' => "product-{$product->id}",
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

        $this->buy([$this->productItem($product, 10, 30)], false, 931, '2026-01-17');

        $product = $this->findProduct($product->id);
        $this->assertEquals(0, $product->quantity);
        $this->assertEqualsWithDelta(0, $product->average_cost, 0.0001);
    }

    public function test_unapproving_buy_invoice_reverses_inventory_and_recalculates_average_cost(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 941, '2026-01-18');
        $buy2 = $this->buy([$this->productItem($product, 10, 120)], true, 942, '2026-01-19')['invoice'];

        $product = $this->findProduct($product->id);
        $this->assertEquals(20, $product->quantity);
        $this->assertEqualsWithDelta(110, $product->average_cost, 0.01);

        $this->unapproveInvoice($buy2);

        $product = $this->findProduct($product->id);
        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);
    }

    public function test_unapproving_sell_invoice_restores_inventory_and_average_cost(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 20, 100)], true, 951, '2026-01-20');
        $sell = $this->sell([$this->productItem($product, 8, 220)], true, 952, '2026-01-21')['invoice'];

        $product = $this->findProduct($product->id);
        $this->assertEquals(12, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);

        $this->unapproveInvoice($sell);

        $product = $this->findProduct($product->id);
        $this->assertEquals(20, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
    }

    public function test_unapproving_ancillary_cost_reverses_average_cost_adjustment(): void
    {
        $product = $this->createProduct();
        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 961, '2026-01-22')['invoice'];

        $result = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => '2026-01-23',
            'type' => 'Shipping',
            'amount' => 50,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 50],
            ],
        ], true);

        $ancillaryCost = $result['ancillaryCost'];

        $product = $this->findProduct($product->id);
        $this->assertEqualsWithDelta(105, $product->average_cost, 0.01);

        $this->unapproveAncillaryCost($ancillaryCost);

        $product = $this->findProduct($product->id);
        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);
    }

    public function test_editing_buy_invoice_quantity_recalculates_average_cost(): void
    {
        $product = $this->createProduct();

        $buyInvoice = $this->buy([$this->productItem($product, 10, 100)], true, 971, '2026-01-24')['invoice'];

        $this->unapproveInvoice($buyInvoice);
        $this->editInvoice($buyInvoice, [$this->productItem($product, 15, 100)], true);

        $product = $this->findProduct($product->id);
        $this->assertEquals(15, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
    }

    public function test_editing_sell_invoice_maintains_cogs_integrity(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 20, 100)], true, 981, '2026-01-25');
        $sell = $this->sell([$this->productItem($product, 10, 220)], true, 982, '2026-01-26')['invoice'];

        $this->unapproveInvoice($sell);
        $this->editInvoice($sell, [$this->productItem($product, 5, 220)], true);

        $product = $this->findProduct($product->id);
        $updatedSell = $this->findInvoice($sell->id);
        $updatedItem = $this->findInvoiceItem($updatedSell, $product);

        $this->assertEquals(15, $product->quantity);
        $this->assertEqualsWithDelta(100, $updatedItem->cog_after, 0.0001);
    }

    public function test_buy_sell_rebuy_maintains_accurate_average_cost(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 991, '2026-01-27');
        $sell1 = $this->sell([$this->productItem($product, 5, 210)], true, 992, '2026-01-28')['invoice'];
        $this->buy([$this->productItem($product, 10, 140)], true, 993, '2026-01-29');

        $expectedAvg = ((100 * 5) + (140 * 10)) / 15;

        $product = $this->findProduct($product->id);
        $this->assertEquals(15, $product->quantity);
        $this->assertEqualsWithDelta($expectedAvg, $product->average_cost, 0.01);

        $sell2 = $this->sell([$this->productItem($product, 7, 230)], true, 994, '2026-01-30')['invoice'];
        $sell1Item = $this->findInvoiceItem($sell1, $product);
        $sell2Item = $this->findInvoiceItem($sell2, $product);

        $this->assertEqualsWithDelta(100, $sell1Item->cog_after, 0.0001);
        $this->assertEqualsWithDelta($expectedAvg, $sell2Item->cog_after, 0.01);
    }

    public function test_unapproving_middle_transaction_recalculates_subsequent_averages(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 10001, '2026-02-01');
        $buy2 = $this->buy([$this->productItem($product, 10, 200)], true, 10002, '2026-02-02')['invoice'];
        $buy3 = $this->buy([$this->productItem($product, 10, 300)], true, 10003, '2026-02-03')['invoice'];

        $product = $this->findProduct($product->id);
        $this->assertEquals(30, $product->quantity);
        $this->assertEqualsWithDelta(200, $product->average_cost, 0.01);

        $this->unapproveInvoice($buy3);
        $this->unapproveInvoice($buy2);
        $this->approveInvoice($buy3);

        $product = $this->findProduct($product->id);
        $this->assertEquals(20, $product->quantity);
        $this->assertEqualsWithDelta(200, $product->average_cost, 0.01);
    }

    public function test_editing_invoice_with_ancillary_costs_maintains_cost_allocation(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 10011, '2026-02-04')['invoice'];

        $result = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => '2026-02-05',
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 100],
            ],
        ], false);

        $this->unapproveInvoice($buy);
        $this->editInvoice($buy, [$this->productItem($product, 20, 100)], true);
        $this->approveAncillaryCost($result['ancillaryCost']);

        $product = $this->findProduct($product->id);
        $this->assertEquals(20, $product->quantity);
        $this->assertEqualsWithDelta(105, $product->average_cost, 0.01);
    }

    public function test_multiple_purchases_calculate_average_cost_and_buy_item_cog_after(): void
    {
        $product = $this->createProduct();

        $buy1 = $this->buy([$this->productItem($product, 10, 100)], true, 1001, '2026-01-01')['invoice'];
        $product = $this->findProduct($product->id);

        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
        $this->assertEqualsWithDelta(100, $this->findInvoiceItem($buy1, $product)->cog_after, 0.0001);

        $buy2 = $this->buy([$this->productItem($product, 5, 120)], true, 1002, '2026-01-02')['invoice'];
        $product = $this->findProduct($product->id);

        $expectedAverage = ((100 * 10) + (120 * 5)) / 15;

        $this->assertEquals(15, $product->quantity);
        $this->assertEqualsWithDelta($expectedAverage, $product->average_cost, 0.01);
        $this->assertEqualsWithDelta($expectedAverage, $this->findInvoiceItem($buy2, $product)->cog_after, 0.01);
    }

    public function test_multiple_sales_keep_average_cost_and_set_cog_after_from_current_average(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 20, 90)], true, 2001, '2026-02-01');

        $sell1 = $this->sell([$this->productItem($product, 5, 150)], true, 2002, '2026-02-02')['invoice'];
        $sell2 = $this->sell([$this->productItem($product, 3, 160)], true, 2003, '2026-02-03')['invoice'];

        $product = $this->findProduct($product->id);
        $sell1Item = $this->findInvoiceItem($sell1, $product);
        $sell2Item = $this->findInvoiceItem($sell2, $product);

        $this->assertEquals(12, $product->quantity);
        $this->assertEqualsWithDelta(90, $product->average_cost, 0.0001);
        $this->assertEqualsWithDelta(90, $sell1Item->cog_after, 0.0001);
        $this->assertEqualsWithDelta(90, $sell2Item->cog_after, 0.0001);
        $this->assertEqualsWithDelta((160 - 90) * 3, CostOfGoodsService::calculateGrossProfit($sell2Item), 0.0001);
    }

    public function test_sell_approval_repopulates_missing_cog_after_on_invoice_items(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 2101, '2026-02-10');

        $sell = $this->sell([$this->productItem($product, 2, 180)], false, 2102, '2026-02-11')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);

        $sellItem->update(['cog_after' => 0]);

        $this->approveInvoice($sell);

        $sell = $this->findInvoice($sell->id);
        $sellItem = $this->findInvoiceItem($sell, $product);
        $product = $this->findProduct($product->id);

        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
        $this->assertEqualsWithDelta(100, $sellItem->cog_after, 0.0001);
    }

    public function test_return_sell_partial_and_full_returns_keep_average_consistent_and_restore_quantity(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 8, 100)], true, 3001, '2026-03-01');
        $sell = $this->sell([$this->productItem($product, 6, 180)], true, 3002, '2026-03-02')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);

        $returnPartial = $this->returnSell([
            $this->productItem($product, 2, 100),
        ], $sell->id, true, 3003, '2026-03-03')['invoice'];

        $product = $this->findProduct($product->id);
        $partialItem = $this->findInvoiceItem($returnPartial, $product);

        $this->assertEquals(4, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
        $this->assertEqualsWithDelta($sellItem->cog_after, $partialItem->cog_after, 0.0001);

        $returnFull = $this->returnSell([
            $this->productItem($product, 4, 100),
        ], $sell->id, true, 3004, '2026-03-04')['invoice'];

        $product = $this->findProduct($product->id);
        $fullItem = $this->findInvoiceItem($returnFull, $product);

        $this->assertEquals(8, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
        $this->assertEqualsWithDelta($sellItem->cog_after, $fullItem->cog_after, 0.0001);
    }

    public function test_return_sell_cog_calculation_uses_same_weighted_average_logic_as_buy(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 3501, '2026-03-10');
        $sell = $this->sell([$this->productItem($product, 4, 250)], true, 3502, '2026-03-11')['invoice'];

        $product = $this->findProduct($product->id);
        $this->assertEquals(6, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);

        $returnSell = $this->returnSell([
            $this->productItem($product, 2, 160),
        ], $sell->id, true, 3503, '2026-03-12')['invoice'];

        $product = $this->findProduct($product->id);
        $returnItem = $this->findInvoiceItem($returnSell, $product);

        $expectedAverage = ((100 * 6) + (160 * 2)) / 8;

        $this->assertEquals(8, $product->quantity);
        $this->assertEqualsWithDelta($expectedAverage, $product->average_cost, 0.01);
        $this->assertEqualsWithDelta(100, $returnItem->cog_after, 0.0001);
    }

    public function test_return_buy_partial_and_full_returns_reduce_inventory_without_changing_average_cost(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 75)], true, 4001, '2026-04-01')['invoice'];
        $buyItem = $this->findInvoiceItem($buy, $product);

        $returnPartial = $this->returnBuy([$this->productItem($product, 3, 75)], $buy->id, true, 4002, '2026-04-02')['invoice'];

        $product = $this->findProduct($product->id);
        $partialItem = $this->findInvoiceItem($returnPartial, $product);

        $this->assertEquals(7, $product->quantity);
        $this->assertEqualsWithDelta(75, $product->average_cost, 0.0001);
        $this->assertEqualsWithDelta($buyItem->cog_after, $partialItem->cog_after, 0.0001);

        $returnFull = $this->returnBuy([$this->productItem($product, 7, 75)], $buy->id, true, 4003, '2026-04-03')['invoice'];

        $product = $this->findProduct($product->id);
        $fullItem = $this->findInvoiceItem($returnFull, $product);

        $this->assertEquals(0, $product->quantity);
        $this->assertEqualsWithDelta(75, $product->average_cost, 0.0001);
        $this->assertEqualsWithDelta($buyItem->cog_after, $fullItem->cog_after, 0.0001);
    }

    public function test_mixed_buy_sell_and_return_sequence_keeps_expected_average_and_item_cogs(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 5001, '2026-05-01');
        $sell1 = $this->sell([$this->productItem($product, 4, 170)], true, 5002, '2026-05-02')['invoice'];
        $buy2 = $this->buy([$this->productItem($product, 6, 160)], true, 5003, '2026-05-03')['invoice'];

        $returnSell = $this->returnSell([$this->productItem($product, 2, 100)], $sell1->id, true, 5004, '2026-05-04')['invoice'];

        $sell2 = $this->sell([$this->productItem($product, 5, 190)], true, 5005, '2026-05-05')['invoice'];
        $returnBuy = $this->returnBuy([$this->productItem($product, 1, 160)], $buy2->id, true, 5006, '2026-05-06')['invoice'];

        $product = $this->findProduct($product->id);

        $expectedAverageAfterReturnSell = 1760 / 14;

        $this->assertEquals(8, $product->quantity);
        $this->assertEqualsWithDelta($expectedAverageAfterReturnSell, $product->average_cost, 0.01);

        $sell2Item = $this->findInvoiceItem($sell2, $product);
        $returnSellItem = $this->findInvoiceItem($returnSell, $product);
        $returnBuyItem = $this->findInvoiceItem($returnBuy, $product);
        $sell1Item = $this->findInvoiceItem($sell1, $product);

        $this->assertEqualsWithDelta($expectedAverageAfterReturnSell, $sell2Item->cog_after, 0.01);
        $this->assertEqualsWithDelta($sell1Item->cog_after, $returnSellItem->cog_after, 0.0001);
        $this->assertEqualsWithDelta(130, $returnBuyItem->cog_after, 0.0001);
        $this->assertEqualsWithDelta($product->quantity * $product->average_cost, CostOfGoodsService::getInventoryValue($product), 0.0001);
    }
}
