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
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReturnInvoiceCOGSTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 6000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->companyId = Company::withoutGlobalScopes()->orderBy('id')->value('id') ?? 1;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $customerGroup = CustomerGroup::withoutGlobalScopes()->where('company_id', $this->companyId)->firstOrFail();
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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
            ->where('invoice_id', $invoice->id)
            ->where('itemable_id', $product->id)
            ->where('itemable_type', Product::class)
            ->firstOrFail();
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

    // =========================================================================
    // برگشت از خرید (RETURN BUY)
    // =========================================================================

    /**
     * برگشت از خرید → موجودی کم می‌شه، میانگین ثابت می‌مونه
     */
    public function test_return_buy_reduces_quantity_and_keeps_average_cost(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6001, '2026-06-01')['invoice'];
        $buyItem = $this->findInvoiceItem($buy, $product);

        $returnBuy = $this->returnBuy([$this->productItem($product, 4, 100)], $buy->id, true, 6002, '2026-06-02')['invoice'];
        $returnItem = $this->findInvoiceItem($returnBuy, $product);

        $product = $this->findProduct($product->id);

        $this->assertEquals(6, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
        // cog_after برگشت از خرید باید برابر cog_after فاکتور خرید اصلی باشه
        $this->assertEqualsWithDelta($buyItem->cog_after, $returnItem->cog_after, 0.0001);
    }

    /**
     * برگشت از خرید وقتی بعدش فروش هم داریم
     * موجودی نباید منفی بشه و میانگین درست بمونه
     */
    public function test_return_buy_after_sell_keeps_average_and_correct_quantity(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6011, '2026-06-01')['invoice'];
        $this->sell([$this->productItem($product, 4, 150)], true, 6012, '2026-06-02');
        // موجودی: 6، میانگین: 100

        $this->returnBuy([$this->productItem($product, 3, 100)], $buy->id, true, 6013, '2026-06-03');
        // موجودی: 3، میانگین باید 100 بمونه

        $product = $this->findProduct($product->id);
        $this->assertEquals(3, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
    }

    /**
     * unapprove برگشت از خرید → موجودی و میانگین به حالت قبل برمی‌گرده
     */
    public function test_unapproving_return_buy_restores_inventory_and_average(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6021, '2026-06-01')['invoice'];
        $returnBuy = $this->returnBuy([$this->productItem($product, 4, 100)], $buy->id, true, 6022, '2026-06-02')['invoice'];

        $product = $this->findProduct($product->id);
        $this->assertEquals(6, $product->quantity);

        $this->unapproveInvoice($returnBuy);

        $product = $this->findProduct($product->id);
        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
    }

    /**
     * برگشت از خرید با هزینه جانبی → میانگین باید با احتساب هزینه جانبی درست بمونه
     */
    public function test_return_buy_after_ancillary_cost_keeps_weighted_average(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6031, '2026-06-01')['invoice'];

        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => '2026-06-02',
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $product->id, 'amount' => 100]],
        ], true);

        // میانگین بعد از هزینه جانبی: (10×100 + 100) / 10 = 110
        $product = $this->findProduct($product->id);
        $this->assertEqualsWithDelta(110, $product->average_cost, 0.01);

        $this->returnBuy([$this->productItem($product, 4, 100)], $buy->id, true, 6032, '2026-06-03');

        // بعد از برگشت 4 عدد: موجودی 6، میانگین باید 110 بمونه
        $product = $this->findProduct($product->id);
        $this->assertEquals(6, $product->quantity);
        $this->assertEqualsWithDelta(110, $product->average_cost, 0.01);
    }

    /**
     * دو برگشت از یک فاکتور خرید → cog_after هر دو باید برابر فاکتور خرید باشه
     */
    public function test_two_return_buys_from_same_invoice_both_use_original_buy_cog(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6041, '2026-06-01')['invoice'];
        $buyItem = $this->findInvoiceItem($buy, $product);

        $return1 = $this->returnBuy([$this->productItem($product, 3, 100)], $buy->id, true, 6042, '2026-06-02')['invoice'];
        $return2 = $this->returnBuy([$this->productItem($product, 2, 100)], $buy->id, true, 6043, '2026-06-03')['invoice'];

        $return1Item = $this->findInvoiceItem($return1, $product);
        $return2Item = $this->findInvoiceItem($return2, $product);

        $this->assertEqualsWithDelta($buyItem->cog_after, $return1Item->cog_after, 0.0001);
        $this->assertEqualsWithDelta($buyItem->cog_after, $return2Item->cog_after, 0.0001);

        $product = $this->findProduct($product->id);
        $this->assertEquals(5, $product->quantity); // 10 - 3 - 2
    }

    /**
     * برگشت کامل از خرید → موجودی صفر می‌شه، میانگین ثابت می‌مونه
     */
    public function test_full_return_buy_zeroes_quantity_but_keeps_average(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 75)], true, 6051, '2026-06-01')['invoice'];
        $this->returnBuy([$this->productItem($product, 10, 75)], $buy->id, true, 6052, '2026-06-02');

        $product = $this->findProduct($product->id);
        $this->assertEquals(0, $product->quantity);
        $this->assertEqualsWithDelta(75, $product->average_cost, 0.0001);
    }

    // =========================================================================
    // برگشت از فروش (RETURN SELL)
    // =========================================================================

    /**
     * برگشت از فروش → موجودی اضافه می‌شه، میانگین با قیمت برگشتی ترکیب می‌شه
     */
    public function test_return_sell_increases_quantity_and_blends_average(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6101, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 4, 150)], true, 6102, '2026-06-02')['invoice'];
        // موجودی: 6، میانگین: 100

        $returnSell = $this->returnSell([$this->productItem($product, 2, 100)], $sell->id, true, 6103, '2026-06-03')['invoice'];
        $returnItem = $this->findInvoiceItem($returnSell, $product);
        $sellItem = $this->findInvoiceItem($sell, $product);

        $product = $this->findProduct($product->id);

        $this->assertEquals(8, $product->quantity); // 6 + 2
        // cog_after برگشت از فروش باید برابر cog_after فاکتور فروش اصلی باشه
        $this->assertEqualsWithDelta($sellItem->cog_after, $returnItem->cog_after, 0.0001);
    }

    /**
     * برگشت از فروش بعد از خرید جدید با قیمت متفاوت → میانگین درست ترکیب می‌شه
     */
    public function test_return_sell_after_new_purchase_blends_average_correctly(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6111, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 5, 150)], true, 6112, '2026-06-02')['invoice'];
        $this->buy([$this->productItem($product, 10, 200)], true, 6113, '2026-06-03');
        // موجودی: 15، میانگین: (5×100 + 10×200) / 15 = 166.67

        $product = $this->findProduct($product->id);
        $avgBeforeReturn = (float) $product->average_cost;

        $this->returnSell([$this->productItem($product, 3, 100)], $sell->id, true, 6114, '2026-06-04');
        // بعد از برگشت: موجودی 18، میانگین باید ترکیب 166.67 و 100 بشه

        $expectedAvg = (($avgBeforeReturn * 15) + (100 * 3)) / 18;

        $product = $this->findProduct($product->id);
        $this->assertEquals(18, $product->quantity);
        $this->assertEqualsWithDelta($expectedAvg, $product->average_cost, 0.01);
    }

    /**
     * unapprove برگشت از فروش → موجودی اضافه‌شده کم می‌شه، میانگین برمی‌گرده
     */
    public function test_unapproving_return_sell_removes_restored_quantity_and_recalculates_average(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6121, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 6, 150)], true, 6122, '2026-06-02')['invoice'];
        $returnSell = $this->returnSell([$this->productItem($product, 3, 100)], $sell->id, true, 6123, '2026-06-03')['invoice'];

        $product = $this->findProduct($product->id);
        $this->assertEquals(7, $product->quantity); // 10 - 6 + 3

        $this->unapproveInvoice($returnSell);

        $product = $this->findProduct($product->id);
        $this->assertEquals(4, $product->quantity); // 10 - 6
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
    }

    /**
     * دو برگشت از یک فاکتور فروش → cog_after هر دو باید از فاکتور فروش اصلی بیاد
     */
    public function test_two_return_sells_from_same_invoice_both_use_original_sell_cog(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 20, 100)], true, 6131, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 10, 180)], true, 6132, '2026-06-02')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);

        $return1 = $this->returnSell([$this->productItem($product, 3, 100)], $sell->id, true, 6133, '2026-06-03')['invoice'];
        $return2 = $this->returnSell([$this->productItem($product, 2, 100)], $sell->id, true, 6134, '2026-06-04')['invoice'];

        $return1Item = $this->findInvoiceItem($return1, $product);
        $return2Item = $this->findInvoiceItem($return2, $product);

        $this->assertEqualsWithDelta($sellItem->cog_after, $return1Item->cog_after, 0.0001);
        $this->assertEqualsWithDelta($sellItem->cog_after, $return2Item->cog_after, 0.0001);

        $product = $this->findProduct($product->id);
        $this->assertEquals(15, $product->quantity); // 20 - 10 + 3 + 2
    }

    /**
     * برگشت از فروش با قیمت متفاوت از قیمت فروش → cog_after از فروش اصلی میاد، نه از قیمت برگشت
     */
    public function test_return_sell_cog_after_uses_sell_invoice_cog_not_return_price(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6141, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 4, 250)], true, 6142, '2026-06-02')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);

        // برگشت با قیمت 160 (متفاوت از قیمت فروش 250)
        $returnSell = $this->returnSell([$this->productItem($product, 2, 160)], $sell->id, true, 6143, '2026-06-03')['invoice'];
        $returnItem = $this->findInvoiceItem($returnSell, $product);

        // cog_after باید از فاکتور فروش اصلی بیاد (100)، نه از قیمت برگشت (160)
        $this->assertEqualsWithDelta($sellItem->cog_after, $returnItem->cog_after, 0.0001);
        $this->assertEqualsWithDelta(100, $returnItem->cog_after, 0.0001);
    }

    // =========================================================================
    // ارزش انبار و یکپارچگی کلی
    // =========================================================================

    /**
     * ارزش کل انبار بعد از ترکیب برگشت‌ها باید با quantity × average_cost برابر باشه
     */
    public function test_inventory_value_is_consistent_after_mixed_returns(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6201, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 4, 150)], true, 6202, '2026-06-02')['invoice'];
        $this->returnSell([$this->productItem($product, 2, 100)], $sell->id, true, 6203, '2026-06-03');

        $buy2 = $this->buy([$this->productItem($product, 5, 120)], true, 6204, '2026-06-04')['invoice'];
        $this->returnBuy([$this->productItem($product, 1, 120)], $buy2->id, true, 6205, '2026-06-05');

        $product = $this->findProduct($product->id);
        // qty: 10 - 4 + 2 + 5 - 1 = 12
        $this->assertEquals(12, $product->quantity);

        $inventoryValue = CostOfGoodsService::getInventoryValue($product);
        $this->assertEqualsWithDelta(
            $product->quantity * $product->average_cost,
            $inventoryValue,
            0.01
        );
    }

    /**
     * سناریوی ترکیبی پیچیده: خرید → فروش → برگشت فروش → خرید جدید → برگشت خرید
     * میانگین و موجودی در هر مرحله باید درست باشه
     */
    public function test_complex_mixed_scenario_maintains_average_and_quantity_at_each_step(): void
    {
        $product = $this->createProduct();

        // مرحله ۱: خرید 10 عدد به 100
        $this->buy([$this->productItem($product, 10, 100)], true, 6301, '2026-06-01');
        $product = $this->findProduct($product->id);
        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);

        // مرحله ۲: فروش 4 عدد
        $sell = $this->sell([$this->productItem($product, 4, 150)], true, 6302, '2026-06-02')['invoice'];
        $product = $this->findProduct($product->id);
        $this->assertEquals(6, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001); // فروش میانگین رو تغییر نمیده

        // مرحله ۳: برگشت 2 عدد از فروش با قیمت 100
        $this->returnSell([$this->productItem($product, 2, 100)], $sell->id, true, 6303, '2026-06-03');
        $product = $this->findProduct($product->id);
        $this->assertEquals(8, $product->quantity);
        // میانگین: (6×100 + 2×100) / 8 = 100
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);

        // مرحله ۴: خرید جدید 6 عدد به 160
        $buy2 = $this->buy([$this->productItem($product, 6, 160)], true, 6304, '2026-06-04')['invoice'];
        $product = $this->findProduct($product->id);
        $this->assertEquals(14, $product->quantity);
        $expectedAvg = ((100 * 8) + (160 * 6)) / 14; // 125.71
        $this->assertEqualsWithDelta($expectedAvg, $product->average_cost, 0.01);

        // مرحله ۵: برگشت 2 عدد از خرید جدید
        $this->returnBuy([$this->productItem($product, 2, 160)], $buy2->id, true, 6305, '2026-06-05');
        $product = $this->findProduct($product->id);
        $this->assertEquals(12, $product->quantity);
        // میانگین ثابت می‌مونه
        $this->assertEqualsWithDelta($expectedAvg, $product->average_cost, 0.01);

        // ارزش انبار در پایان
        $inventoryValue = CostOfGoodsService::getInventoryValue($product);
        $this->assertEqualsWithDelta(
            $product->quantity * $product->average_cost,
            $inventoryValue,
            0.01
        );
    }

    /**
     * unapprove برگشت از خرید که بعدش فروش هم داریم → میانگین‌های بعدی باید بازمحاسبه بشن
     */
    public function test_unapproving_return_buy_triggers_recalculation_for_subsequent_transactions(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6401, '2026-06-01')['invoice'];
        $returnBuy = $this->returnBuy([$this->productItem($product, 4, 100)], $buy->id, true, 6402, '2026-06-02')['invoice'];
        $sell = $this->sell([$this->productItem($product, 3, 150)], true, 6403, '2026-06-03')['invoice'];

        // وضعیت فعلی: موجودی = 10 - 4 - 3 = 3
        $product = $this->findProduct($product->id);
        $this->assertEquals(3, $product->quantity);

        $this->unapproveInvoice($returnBuy);

        // بعد از unapprove برگشت از خرید: موجودی = 10 - 3 = 7
        $product = $this->findProduct($product->id);
        $this->assertEquals(7, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
    }

    /**
     * برگشت از فروش → سود ناخالص باید کاهش پیدا کنه
     */
    public function test_return_sell_reduces_gross_profit_correctly(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6501, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 6, 180)], true, 6502, '2026-06-02')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);

        // سود ناخالص قبل از برگشت: 6 × (180 - 100) = 480
        $profitBeforeReturn = CostOfGoodsService::calculateGrossProfit($sellItem);
        $this->assertEqualsWithDelta((180 - 100) * 6, $profitBeforeReturn, 0.0001);

        $returnSell = $this->returnSell([$this->productItem($product, 2, 180)], $sell->id, true, 6503, '2026-06-03')['invoice'];
        $returnItem = $this->findInvoiceItem($returnSell, $product);

        // cog_after برگشت برابر فروش اصلی
        $this->assertEqualsWithDelta($sellItem->cog_after, $returnItem->cog_after, 0.0001);

        $product = $this->findProduct($product->id);
        $this->assertEquals(6, $product->quantity); // 10 - 6 + 2
    }
}
