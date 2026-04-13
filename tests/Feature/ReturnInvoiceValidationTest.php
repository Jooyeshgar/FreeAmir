<?php

// tests/Feature/ReturnInvoiceValidationTest.php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\CostOfGoodsService;
use Cookie;
use Database\Seeders\CompanySeeder;
use Database\Seeders\CustomerGroupSeeder;
use Database\Seeders\ProductGroupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\InvoiceTestHelper;
use Tests\TestCase;

class ReturnInvoiceValidationTest extends TestCase
{
    use InvoiceTestHelper;
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 6000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CompanySeeder::class);
        $this->seed(CustomerGroupSeeder::class);
        $this->seed(ProductGroupSeeder::class);

        $this->companyId = Company::query()->orderBy('id')->value('id') ?? 1;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $customerGroup = CustomerGroup::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->firstOrFail();

        $this->customer = Customer::factory()
            ->withGroup($customerGroup)
            ->withSubject()
            ->create(['company_id' => $this->companyId]);
    }

    // -------------------------------------------------------------------------
    // RETURN BUY — quantity validation
    // -------------------------------------------------------------------------

    /**
     * برگشت از خرید نمی‌تواند بیشتر از مقدار فاکتور خرید اصلی باشد.
     */
    public function test_return_buy_cannot_exceed_original_buy_quantity(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 5, 100)], true, 6001, '2026-06-01')['invoice'];

        $this->expectException(\Exception::class);

        // تلاش برای برگشت ۶ عدد از خریدی که ۵ عدد بوده
        $this->returnBuy([$this->productItem($product, 6, 100)], $buy->id, true, 6002, '2026-06-02');
    }

    /**
     * برگشت از خرید در چند مرحله نباید از جمع مقدار اصلی تجاوز کند.
     */
    public function test_return_buy_cumulative_cannot_exceed_original_quantity(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 5, 100)], true, 6011, '2026-06-01')['invoice'];

        // برگشت ۳ عدد — باید موفق باشد
        $this->returnBuy([$this->productItem($product, 3, 100)], $buy->id, true, 6012, '2026-06-02');

        $this->expectException(\Exception::class);

        // برگشت ۳ عدد دیگر — جمعاً ۶ عدد، بیشتر از ۵ عدد اصلی
        $this->returnBuy([$this->productItem($product, 3, 100)], $buy->id, true, 6013, '2026-06-03');
    }

    /**
     * برگشت از خرید دقیقاً به اندازه مقدار اصلی باید موفق باشد.
     */
    public function test_return_buy_exact_original_quantity_is_allowed(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 5, 100)], true, 6021, '2026-06-01')['invoice'];

        $return = $this->returnBuy([$this->productItem($product, 5, 100)], $buy->id, true, 6022, '2026-06-02')['invoice'];

        //  توی انبار موجودی به مبلغ 100 نداریم چون به تعداد ۵ تا با ارزش ۱۰۰ برگشت از خرید دادیم.
        // (۵ * ۱۰۰ - ۵ * ۱۰۰) / 0 = 0 تقسیم به صفر نداریم پس صفر در نظر میگیریم
        $product = $this->findProduct($product->id);

        $this->assertEquals(0, $product->quantity);
        $this->assertEqualsWithDelta(0, $product->average_cost, 0.0001);
    }

    // -------------------------------------------------------------------------
    // RETURN SELL — quantity validation
    // -------------------------------------------------------------------------

    /**
     * برگشت از فروش نمی‌تواند بیشتر از مقدار فاکتور فروش اصلی باشد.
     */
    public function test_return_sell_cannot_exceed_original_sell_quantity(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6031, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 5, 180)], true, 6032, '2026-06-02')['invoice'];

        $this->expectException(\Exception::class);

        // تلاش برای برگشت ۶ عدد از فروشی که ۵ عدد بوده
        $this->returnSell([$this->productItem($product, 6, 100)], $sell->id, true, 6033, '2026-06-03');
    }

    /**
     * برگشت از فروش در چند مرحله نباید از جمع مقدار اصلی تجاوز کند.
     */
    public function test_return_sell_cumulative_cannot_exceed_original_quantity(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6041, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 5, 180)], true, 6042, '2026-06-02')['invoice'];

        // برگشت ۳ عدد — باید موفق باشد
        $this->returnSell([$this->productItem($product, 3, 100)], $sell->id, true, 6043, '2026-06-03');

        $this->expectException(\Exception::class);

        // برگشت ۳ عدد دیگر — جمعاً ۶ عدد، بیشتر از ۵ عدد اصلی
        $this->returnSell([$this->productItem($product, 3, 100)], $sell->id, true, 6044, '2026-06-04');
    }

    /**
     * برگشت از فروش دقیقاً به اندازه مقدار اصلی باید موفق باشد.
     */
    public function test_return_sell_exact_original_quantity_is_allowed(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6051, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 5, 180)], true, 6052, '2026-06-02')['invoice'];

        $return = $this->returnSell([$this->productItem($product, 5, 100)], $sell->id, true, 6053, '2026-06-03')['invoice'];

        $product = $this->findProduct($product->id);

        // موجودی باید به ۱۰ برگردد
        $this->assertEquals(10, $product->quantity);
    }

    // -------------------------------------------------------------------------
    // RETURN BUY — COG و میانگین
    // -------------------------------------------------------------------------

    /**
     * برگشت از خرید باید cog_after برابر با cog_after فاکتور خرید اصلی داشته باشد
     * و میانگین موزون تغییر نکند.
     */
    public function test_return_buy_cog_after_equals_original_buy_cog_and_average_unchanged(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 120)], true, 6061, '2026-06-01')['invoice'];
        $buyItem = $this->findInvoiceItem($buy, $product);

        $return = $this->returnBuy([$this->productItem($product, 4, 120)], $buy->id, true, 6062, '2026-06-02')['invoice'];

        $product = $this->findProduct($product->id);
        $returnItem = $this->findInvoiceItem($return, $product);

        $this->assertEquals(6, $product->quantity);
        // میانگین نباید تغییر کند
        $this->assertEqualsWithDelta(120, $product->average_cost, 0.0001);
        // cog_after برگشت باید برابر cog_after خرید اصلی باشد
        $this->assertEqualsWithDelta($buyItem->cog_after, $returnItem->cog_after, 0.0001);
    }

    /**
     * برگشت از خرید وقتی دو خرید با قیمت‌های مختلف داریم:
     * میانگین باید بازمحاسبه شود چون ترکیب موجودی تغییر کرده.
     */
    public function test_return_buy_after_two_purchases_recalculates_average(): void
    {
        $product = $this->createProduct();

        // خرید اول: ۱۰ عدد × ۱۰
        $buy1 = $this->buy([$this->productItem($product, 10, 100)], true, 6071, '2026-06-01')['invoice'];
        // خرید دوم: ۱۰ عدد × ۱۴۰
        $buy2 = $this->buy([$this->productItem($product, 10, 140)], true, 6072, '2026-06-02')['invoice'];

        $product = $this->findProduct($product->id);
        // میانگین = (10×100 + 10×140) / 20 = 120
        $this->assertEqualsWithDelta(120, $product->average_cost, 0.01);

        // برگشت ۵ عدد از خرید دوم (قیمت اصلی ۱۴۰)
        $buy2Item = $this->findInvoiceItem($buy2, $product);
        $return = $this->returnBuy([$this->productItem($product, 5, 140)], $buy2->id, true, 6073, '2026-06-03')['invoice'];

        $product = $this->findProduct($product->id);
        $returnItem = $this->findInvoiceItem($return, $product);

        // موجودی: ۱۵ عدد
        $this->assertEquals(15, $product->quantity);
        // میانگین جدید = (10×100 + 5×140) / 15 = 1700/15 ≈ 113.33
        $expectedAvg = (10 * 100 + 5 * 140) / 15;
        // cog_after برگشت از خرید با میانگین بازمحاسبه‌شده همگام می‌شود
        $this->assertEqualsWithDelta($expectedAvg, $returnItem->cog_after, 0.01);
        $this->assertEqualsWithDelta($expectedAvg, $product->average_cost, 0.01);
    }

    // -------------------------------------------------------------------------
    // RETURN BUY + هزینه جانبی
    // -------------------------------------------------------------------------

    /**
     * وقتی روی خرید هزینه جانبی زده شده، برگشت از خرید باید
     * با cog_after نهایی (شامل هزینه جانبی) محاسبه شود.
     */
    public function test_return_buy_after_ancillary_cost_uses_final_cog_after(): void
    {
        $product = $this->createProduct();

        // خرید: ۱۰ عدد × ۱۰۰ = ۱۰۰۰
        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6081, '2026-06-01')['invoice'];

        // هزینه جانبی ۲۰۰ تومان روی این خرید
        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => '2026-06-02',
            'type' => 'Shipping',
            'amount' => 200,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 200],
            ],
        ], true);

        // میانگین بعد از هزینه جانبی = (1000 + 200) / 10 = 120
        $product = $this->findProduct($product->id);
        $this->assertEqualsWithDelta(120, $product->average_cost, 0.01);

        $buyItem = $this->findInvoiceItem($buy, $product);

        // برگشت ۳ عدد از این خرید
        $return = $this->returnBuy([$this->productItem($product, 3, 100)], $buy->id, true, 6082, '2026-06-03')['invoice'];

        $product = $this->findProduct($product->id);
        $returnItem = $this->findInvoiceItem($return, $product);

        $this->assertEquals(7, $product->quantity);
        // cog_after برگشت باید برابر cog_after خرید (که شامل هزینه جانبی شده) باشد
        $this->assertEqualsWithDelta($buyItem->cog_after, $returnItem->cog_after, 0.01);
        // میانگین نباید تغییر کند چون با همان cog_after خارج شده
        $this->assertEqualsWithDelta(120, $product->average_cost, 0.01);
    }

    /**
     * برگشت از خرید بعد از هزینه جانبی و سپس فروش:
     * ارزش موجودی باید با quantity × average_cost برابر باشد.
     */
    public function test_return_buy_with_ancillary_cost_inventory_value_stays_consistent(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6091, '2026-06-01')['invoice'];

        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => '2026-06-02',
            'type' => 'Other',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 100],
            ],
        ], true);

        // میانگین = 110
        $this->sell([$this->productItem($product, 3, 200)], true, 6092, '2026-06-03');

        $this->returnBuy([$this->productItem($product, 2, 100)], $buy->id, true, 6093, '2026-06-04');

        $product = $this->findProduct($product->id);

        // موجودی: 10 - 3 - 2 = 5
        $this->assertEquals(5, $product->quantity);
        $this->assertEqualsWithDelta(5 * $product->average_cost, CostOfGoodsService::getInventoryValue($product), 0.01);
    }

    // -------------------------------------------------------------------------
    // RETURN SELL — COG و میانگین
    // -------------------------------------------------------------------------

    /**
     * برگشت از فروش باید cog_after برابر با cog_after فاکتور فروش اصلی داشته باشد
     * و میانگین موزون مثل یک ورودی جدید محاسبه شود.
     */
    public function test_return_sell_cog_after_equals_original_sell_cog_and_average_recalculated(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 6101, '2026-06-01');
        $sell = $this->sell([$this->productItem($product, 4, 200)], true, 6102, '2026-06-02')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);

        // موجودی: ۶ عدد، میانگین: ۱۰۰
        $product = $this->findProduct($product->id);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);

        // برگشت ۲ عدد از این فروش
        $return = $this->returnSell([$this->productItem($product, 2, 100)], $sell->id, true, 6103, '2026-06-03')['invoice'];

        $product = $this->findProduct($product->id);
        $returnItem = $this->findInvoiceItem($return, $product);

        $this->assertEquals(8, $product->quantity);
        // cog_after برگشت باید برابر cog_after فروش اصلی باشد
        $this->assertEqualsWithDelta($sellItem->cog_after, $returnItem->cog_after, 0.0001);
        // میانگین جدید = (6×100 + 2×100) / 8 = 100 (در این مثال تغییر نمی‌کند)
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);
    }

    /**
     * برگشت از فروش وقتی قیمت بازگشتی با میانگین فعلی فرق دارد،
     * میانگین باید تغییر کند.
     */
    public function test_return_sell_with_different_price_changes_average(): void
    {
        $product = $this->createProduct();

        // خرید اول: ۱۰ عدد × ۱۰۰
        $this->buy([$this->productItem($product, 10, 100)], true, 6111, '2026-06-01');
        // فروش: ۴ عدد
        $sell = $this->sell([$this->productItem($product, 4, 200)], true, 6112, '2026-06-02')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);

        // خرید دوم: ۵ عدد × ۱۵۰ → میانگین تغییر می‌کند
        $this->buy([$this->productItem($product, 5, 150)], true, 6113, '2026-06-03');

        $product = $this->findProduct($product->id);
        // موجودی: 6 + 5 = 11، میانگین = (6×100 + 5×150) / 11 ≈ 122.73
        $avgBeforeReturn = $product->average_cost;

        // برگشت ۲ عدد از فروش اولیه (cog_after فروش = ۱۰۰)
        $return = $this->returnSell([$this->productItem($product, 2, 100)], $sell->id, true, 6114, '2026-06-04')['invoice'];

        $product = $this->findProduct($product->id);
        $returnItem = $this->findInvoiceItem($return, $product);

        $this->assertEquals(13, $product->quantity);
        // cog_after برگشت = cog_after فروش اصلی
        $this->assertEqualsWithDelta($sellItem->cog_after, $returnItem->cog_after, 0.0001);
        // میانگین جدید = (11 × avgBeforeReturn + 2 × sellItem->cog_after) / 13
        $expectedAvg = (11 * $avgBeforeReturn + 2 * $sellItem->cog_after) / 13;
        $this->assertEqualsWithDelta($expectedAvg, $product->average_cost, 0.01);
    }

    // -------------------------------------------------------------------------
    // RETURN SELL + هزینه جانبی
    // -------------------------------------------------------------------------

    /**
     * وقتی روی خرید اصلی هزینه جانبی زده شده و بعد فروش و برگشت از فروش داریم،
     * cog_after برگشت از فروش باید برابر cog_after فروش اصلی باشد (که خودش از میانگین
     * شامل هزینه جانبی محاسبه شده).
     */
    public function test_return_sell_after_ancillary_cost_uses_sell_cog_after(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6121, '2026-06-01')['invoice'];

        // هزینه جانبی ۱۰۰ تومان → میانگین = 110
        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => '2026-06-02',
            'type' => 'Shipping',
            'amount' => 100,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 100],
            ],
        ], true);

        // فروش: ۴ عدد با میانگین ۱۱۰
        $sell = $this->sell([$this->productItem($product, 4, 220)], true, 6122, '2026-06-03')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);

        // cog_after فروش باید ۱۱۰ باشد
        $this->assertEqualsWithDelta(110, $sellItem->cog_after, 0.01);

        // برگشت ۲ عدد از این فروش
        $return = $this->returnSell([$this->productItem($product, 2, 110)], $sell->id, true, 6123, '2026-06-04')['invoice'];

        $product = $this->findProduct($product->id);
        $returnItem = $this->findInvoiceItem($return, $product);

        $this->assertEquals(8, $product->quantity);
        // cog_after برگشت = cog_after فروش = 110
        $this->assertEqualsWithDelta($sellItem->cog_after, $returnItem->cog_after, 0.01);
        // میانگین = (6×110 + 2×110) / 8 = 110
        $this->assertEqualsWithDelta(110, $product->average_cost, 0.01);
    }

    /**
     * برگشت از فروش بعد از هزینه جانبی: ارزش موجودی باید consistent بماند.
     */
    public function test_return_sell_with_ancillary_cost_inventory_value_stays_consistent(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 6131, '2026-06-01')['invoice'];

        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => '2026-06-02',
            'type' => 'Other',
            'amount' => 150,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 150],
            ],
        ], true);

        // میانگین = 115
        $sell = $this->sell([$this->productItem($product, 5, 230)], true, 6132, '2026-06-03')['invoice'];

        $this->returnSell([$this->productItem($product, 3, 115)], $sell->id, true, 6133, '2026-06-04');

        $product = $this->findProduct($product->id);

        // موجودی: 10 - 5 + 3 = 8
        $this->assertEquals(8, $product->quantity);
        $this->assertEqualsWithDelta(8 * 115, CostOfGoodsService::getInventoryValue($product), 0.01);
    }

    // -------------------------------------------------------------------------
    // ترکیبی: برگشت از خرید + برگشت از فروش در یک سناریو
    // -------------------------------------------------------------------------

    /**
     * سناریوی ترکیبی کامل:
     * خرید → فروش → برگشت از فروش → خرید دوم → برگشت از خرید
     * بررسی صحت میانگین و cog_after در هر مرحله
     */
    public function test_combined_return_buy_and_return_sell_scenario(): void
    {
        $product = $this->createProduct();

        // مرحله ۱: خرید ۱۰ عدد × ۱۰۰
        $buy1 = $this->buy([$this->productItem($product, 10, 100)], true, 6141, '2026-06-01')['invoice'];

        $product = $this->findProduct($product->id);
        $this->assertEquals(10, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);

        // مرحله ۲: فروش ۴ عدد
        $sell = $this->sell([$this->productItem($product, 4, 200)], true, 6142, '2026-06-02')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);
        $this->assertEqualsWithDelta(100, $sellItem->cog_after, 0.0001);

        $product = $this->findProduct($product->id);
        $this->assertEquals(6, $product->quantity);

        // مرحله ۳: برگشت از فروش ۲ عدد
        $returnSell = $this->returnSell([$this->productItem($product, 2, 100)], $sell->id, true, 6143, '2026-06-03')['invoice'];
        $returnSellItem = $this->findInvoiceItem($returnSell, $product);

        $product = $this->findProduct($product->id);
        $this->assertEquals(8, $product->quantity);
        // cog_after برگشت از فروش = cog_after فروش اصلی
        $this->assertEqualsWithDelta($sellItem->cog_after, $returnSellItem->cog_after, 0.0001);
        // میانگین = (6×100 + 2×100) / 8 = 100
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.0001);

        // مرحله ۴: خرید دوم ۶ عدد × ۱۴۰
        $buy2 = $this->buy([$this->productItem($product, 6, 140)], true, 6144, '2026-06-04')['invoice'];
        $buy2Item = $this->findInvoiceItem($buy2, $product);

        $product = $this->findProduct($product->id);
        $this->assertEquals(14, $product->quantity);
        // میانگین = (8×100 + 6×140) / 14 = (800 + 840) / 14 = 1640 / 14 ≈ 117.14
        $expectedAvgAfterBuy2 = (8 * 100 + 6 * 140) / 14;
        $this->assertEqualsWithDelta($expectedAvgAfterBuy2, $product->average_cost, 0.01);
        $this->assertEqualsWithDelta($expectedAvgAfterBuy2, $buy2Item->cog_after, 0.01);

        // مرحله ۵: برگشت از خرید دوم ۳ عدد
        $returnBuy = $this->returnBuy([$this->productItem($product, 3, 140)], $buy2->id, true, 6145, '2026-06-05')['invoice'];
        $returnBuyItem = $this->findInvoiceItem($returnBuy, $product);

        $product = $this->findProduct($product->id);
        $this->assertEquals(11, $product->quantity);
        // میانگین جدید = (8×100 + 3×140) / 11 = (800 + 420) / 11 = 1220 / 11 ≈ 110.91
        $expectedAvgAfterReturnBuy = (8 * 100 + 3 * 140) / 11;
        // cog_after برگشت از خرید با میانگین جدید همگام می‌شود
        $this->assertEqualsWithDelta($expectedAvgAfterReturnBuy, $returnBuyItem->cog_after, 0.01);
        $this->assertEqualsWithDelta($expectedAvgAfterReturnBuy, $product->average_cost, 0.01);

        // مرحله ۶: بررسی نهایی ارزش موجودی
        $this->assertEqualsWithDelta(11 * 110.91, CostOfGoodsService::getInventoryValue($product), 0.01);
    }
}
