<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductImportService;
use App\Services\WarehouseDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class WarehouseDashboardTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private Customer $customer;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-03 12:00:00', config('app.timezone')));

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId]);
        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        unset($_COOKIE['active-company-id']);

        parent::tearDown();
    }

    public function test_user_with_warehouse_dashboard_can_view_warehouse_dashboard(): void
    {
        $this->grant('warehouse.dashboard');

        $response = $this->actingAs($this->user)->get(route('warehouse.dashboard'));

        $response->assertOk();
        $response->assertViewIs('warehouse.dashboard');
        $response->assertViewHas('summary');
        $response->assertViewHas('categoryBreakdown');
        $response->assertViewHas('topSellers');
        $response->assertViewHas('belowReorderItems');
        $response->assertViewHas('stagnantItems');
    }

    public function test_user_without_warehouse_dashboard_cannot_view_warehouse_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get(route('warehouse.dashboard'));

        $response->assertForbidden();
    }

    public function test_service_computes_inventory_kpis_and_top_sellers(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);

        $bestSeller = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-001',
            'name' => 'Best Seller',
            'quantity' => 5,
            'quantity_warning' => 10,
            'average_cost' => 100,
            'selling_price' => 250,
        ]);

        $stagnant = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-002',
            'name' => 'Stagnant Item',
            'quantity' => 20,
            'quantity_warning' => 5,
            'average_cost' => 50,
            'selling_price' => 90,
        ]);
        $this->inventoryBalance($bestSeller, -500);
        $this->inventoryBalance($stagnant, -1000);
        $this->stockMovement($bestSeller, InvoiceType::BEGINNING_INVENTORY, 8, 2, jalali_to_gregorian(1405, 1, 1, '-'));
        $this->stockMovement($stagnant, InvoiceType::BEGINNING_INVENTORY, 20, 3, jalali_to_gregorian(1405, 1, 1, '-'));

        $sell = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED, 1, Carbon::now()->subDays(5)->toDateString(), 750);
        InvoiceItem::factory()->create([
            'invoice_id' => $sell->id,
            'itemable_type' => Product::class,
            'itemable_id' => $bestSeller->id,
            'quantity' => 3,
            'unit_price' => 250,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => 750,
            'cog_after' => 100,
            'quantity_at' => 8,
        ]);

        $data = app(WarehouseDashboardService::class)->dashboard();

        $this->assertEquals(2, $data['summary']['total_item_count']);
        $this->assertEquals(1500.0, $data['summary']['total_inventory_value']);
        $this->assertEquals(1, $data['summary']['below_reorder_count']);
        $this->assertCount(1, $data['topSellers']);
        $this->assertEquals('Best Seller', $data['topSellers']->first()['name']);
        $this->assertEquals(3.0, $data['topSellers']->first()['units']);
    }

    public function test_category_filter_restricts_dashboard_scope(): void
    {
        $widgets = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);
        $gadgets = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Gadgets']);

        $widgetsProduct = Product::factory()->withGroup($widgets)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'W-1',
            'quantity' => 5,
            'quantity_warning' => 10,
            'average_cost' => 100,
        ]);
        Product::factory()->withGroup($gadgets)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'G-1',
            'quantity' => 20,
            'quantity_warning' => 5,
            'average_cost' => 50,
        ]);
        $this->inventoryBalance($widgetsProduct, -500);
        $this->stockMovement($widgetsProduct, InvoiceType::BEGINNING_INVENTORY, 5, 1, jalali_to_gregorian(1405, 1, 1, '-'));

        $data = app(WarehouseDashboardService::class)->dashboard(['category_id' => $widgets->id]);

        $this->assertEquals(1, $data['summary']['total_item_count']);
        $this->assertEquals(500.0, $data['summary']['total_inventory_value']);
        $this->assertEquals(1, $data['categoryBreakdown']->count());
        $this->assertEquals('Widgets', $data['categoryBreakdown']->first()['name']);
    }

    public function test_year_period_uses_exact_fiscal_year_and_has_twelve_jalali_months(): void
    {
        $data = app(WarehouseDashboardService::class)->dashboard(['period' => 'year']);

        $this->assertSame(jalali_to_gregorian(1405, 1, 1, '-'), $data['periodRange']['from']->toDateString());
        $this->assertSame(
            Carbon::parse(jalali_to_gregorian(1406, 1, 1, '-'))->subDay()->toDateString(),
            $data['periodRange']['to']->toDateString()
        );
        $this->assertSame(
            array_map(fn (int $month) => sprintf('1405/%02d', $month), range(1, 12)),
            $data['monthlyMovement']['labels']
        );
    }

    public function test_short_periods_are_clamped_to_the_active_fiscal_year(): void
    {
        Carbon::setTestNow(Carbon::parse(jalali_to_gregorian(1405, 1, 10, '-').' 12:00:00'));

        $data = app(WarehouseDashboardService::class)->dashboard(['period' => 'quarter']);

        $this->assertSame(jalali_to_gregorian(1405, 1, 1, '-'), $data['periodRange']['from']->toDateString());
        $this->assertSame(jalali_to_gregorian(1405, 1, 10, '-'), $data['periodRange']['to']->toDateString());
    }

    public function test_inventory_value_uses_inventory_account_balance(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);
        $product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'quantity' => 20,
            'average_cost' => 50,
        ]);
        $this->inventoryBalance($product, -725);
        $this->stockMovement($product, InvoiceType::BEGINNING_INVENTORY, 20, 1, jalali_to_gregorian(1405, 1, 1, '-'));

        $data = app(WarehouseDashboardService::class)->dashboard();

        $this->assertSame(725.0, $data['summary']['total_inventory_value']);
        $this->assertSame(725.0, $data['categoryBreakdown']->first()['inventory_value']);
    }

    public function test_dashboard_uses_directly_imported_stock_when_invoice_history_is_absent(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);
        Warehouse::create([
            'company_id' => $this->companyId,
            'name' => 'Imported stock warehouse',
        ]);
        $csv = "code,name,group_name,quantity,quantity_warning,Imported stock warehouse\n"."IMP-1,Imported product,{$group->name},7,10,7\n";

        app(ProductImportService::class)->import(UploadedFile::fake()->createWithContent('products.csv', $csv), $this->companyId);

        $data = app(WarehouseDashboardService::class)->dashboard();

        $this->assertSame(1, $data['summary']['total_item_count']);
        $this->assertSame(7.0, $data['summary']['total_stock_quantity']);
        $this->assertSame(1, $data['summary']['below_reorder_count']);
        $this->assertSame(1, $data['summary']['stagnant_count']);
        $this->assertSame(7.0, $data['belowReorderItems']->first()['quantity']);
        $this->assertSame(7.0, $data['stagnantItems']->first()['quantity']);
    }

    public function test_holding_days_include_both_period_endpoints_for_every_preset(): void
    {
        $company = Company::withoutGlobalScopes()->findOrFail($this->companyId);
        [$fiscalStart, $fiscalEnd] = $company->fiscalYearRange();
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);
        $product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'quantity' => 1,
            'average_cost' => 100,
        ]);
        $this->stockMovement($product, InvoiceType::BEGINNING_INVENTORY, 2, 30, $fiscalStart->toDateString());
        $this->stockMovement($product, InvoiceType::SELL, 1, 31, Carbon::now()->toDateString(), 100);
        $this->inventoryBalance($product, -100, Carbon::now()->toDateString());

        $expectedDays = [
            'month' => 30.0,
            'quarter' => 90.0,
            'year' => (float) ($fiscalStart->copy()->startOfDay()->diffInDays($fiscalEnd->copy()->startOfDay()) + 1),
        ];

        foreach ($expectedDays as $period => $days) {
            $data = app(WarehouseDashboardService::class)->dashboard(['period' => $period]);

            $this->assertSame(1.0, $data['summary']['avg_turnover_ratio'], $period);
            $this->assertSame($days, $data['summary']['avg_holding_days'], $period);
        }
    }

    public function test_status_filter_returns_below_reorder_items(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);
        $lowStock = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-LOW',
            'quantity' => 3,
            'quantity_warning' => 10,
            'average_cost' => 100,
        ]);
        Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-OK',
            'quantity' => 100,
            'quantity_warning' => 10,
            'average_cost' => 100,
        ]);
        $this->stockMovement($lowStock, InvoiceType::BEGINNING_INVENTORY, 3, 1, jalali_to_gregorian(1405, 1, 1, '-'));
        $healthy = Product::query()->where('code', 'P-OK')->firstOrFail();
        $this->stockMovement($healthy, InvoiceType::BEGINNING_INVENTORY, 100, 2, jalali_to_gregorian(1405, 1, 1, '-'));

        $data = app(WarehouseDashboardService::class)->dashboard(['status' => 'below_reorder']);

        $this->assertCount(1, $data['statusFilteredItems']);
        $this->assertEquals($lowStock->id, $data['statusFilteredItems']->first()['id']);
    }

    public function test_snapshot_metrics_use_the_selected_period_end(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);
        $active = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'ACTIVE',
            'quantity' => 999,
            'quantity_warning' => 7,
        ]);
        $stagnant = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'STAGNANT',
            'quantity' => 999,
            'quantity_warning' => 2,
        ]);

        $fiscalStart = jalali_to_gregorian(1405, 1, 1, '-');
        $this->stockMovement($active, InvoiceType::BEGINNING_INVENTORY, 10, 10, $fiscalStart);
        $this->stockMovement($stagnant, InvoiceType::BEGINNING_INVENTORY, 5, 11, $fiscalStart);
        $this->stockMovement($active, InvoiceType::SELL, 4, 12, Carbon::now()->subDays(5)->toDateString(), 800);
        $this->stockMovement($active, InvoiceType::SELL, 2, 13, Carbon::now()->addDays(5)->toDateString(), 400);
        $this->stockMovement($stagnant, InvoiceType::SELL, 1, 14, Carbon::now()->addDays(5)->toDateString(), 100);
        $this->inventoryBalance($active, -600, Carbon::now()->subDays(5)->toDateString());
        $this->inventoryBalance($active, 200, Carbon::now()->addDays(5)->toDateString());
        $this->inventoryBalance($stagnant, -500, $fiscalStart);
        $this->inventoryBalance($stagnant, 100, Carbon::now()->addDays(5)->toDateString());

        $data = app(WarehouseDashboardService::class)->dashboard(['period' => 'month']);

        $this->assertSame(2, $data['summary']['total_item_count']);
        $this->assertSame(11.0, $data['summary']['total_stock_quantity']);
        $this->assertSame(1100.0, $data['summary']['total_inventory_value']);
        $this->assertSame(1, $data['summary']['below_reorder_count']);
        $this->assertSame(1, $data['summary']['stagnant_count']);
        $this->assertSame(2, $data['categoryBreakdown']->first()['item_count']);
        $this->assertSame(1100.0, $data['categoryBreakdown']->first()['inventory_value']);
        $this->assertSame($active->id, $data['belowReorderItems']->first()['id']);
        $this->assertSame(6.0, $data['belowReorderItems']->first()['quantity']);
        $this->assertSame($stagnant->id, $data['stagnantItems']->first()['id']);
        $this->assertSame(5.0, $data['stagnantItems']->first()['quantity']);
        $this->assertSame($active->id, $data['topSellers']->first()['id']);
        $this->assertSame(4.0, $data['topSellers']->first()['units']);
    }

    public function test_report_min_quantity_filter_is_inclusive(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);

        $atThreshold = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'R-EQ',
            'name' => 'At Threshold',
            'quantity' => 10,
            'average_cost' => 100,
            'selling_price' => 150,
        ]);
        Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'R-LT',
            'name' => 'Below Threshold',
            'quantity' => 9,
            'average_cost' => 100,
            'selling_price' => 150,
        ]);

        $rows = app(WarehouseDashboardService::class)->report(['min_quantity' => 10])['rows'];

        $this->assertCount(1, $rows);
        $this->assertEquals($atThreshold->name, $rows->first()['name']);
    }

    public function test_report_need_order_filter_returns_reorder_products(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);

        $needsOrder = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'R-LOW',
            'name' => 'Needs Order',
            'quantity' => 3,
            'quantity_warning' => 10,
            'average_cost' => 100,
            'selling_price' => 150,
        ]);

        Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'R-OK',
            'name' => 'Healthy Stock',
            'quantity' => 20,
            'quantity_warning' => 10,
            'average_cost' => 100,
            'selling_price' => 150,
        ]);

        Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'R-NO-WARNING',
            'name' => 'No Warning',
            'quantity' => 0,
            'quantity_warning' => 0,
            'average_cost' => 100,
            'selling_price' => 150,
        ]);

        $report = app(WarehouseDashboardService::class)->report(['need_order' => true]);
        $rows = $report['rows'];

        $this->assertCount(1, $rows);
        $this->assertEquals($needsOrder->name, $rows->first()['name']);
        $this->assertContains(__('Need Order'), collect($report['filterSummary'])->pluck('label')->all());
    }

    public function test_report_clamps_movements_to_the_end_of_a_closed_fiscal_year(): void
    {
        $company = Company::withoutGlobalScopes()->findOrFail($this->companyId);
        $company->update(['fiscal_year' => 1404]);
        [$fiscalStart, $fiscalEnd] = $company->fiscalYearRange();

        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);
        $product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'CLOSED-YEAR',
        ]);

        $this->stockMovement($product, InvoiceType::BUY, 2, 20, $fiscalStart->copy()->addMonth()->toDateString());
        $this->stockMovement($product, InvoiceType::BUY, 5, 21, $fiscalEnd->copy()->addDay()->toDateString());
        $this->accountTotals($product, $fiscalStart->copy()->addMonth()->toDateString(), [
            'income_subject_id' => 1000,
            'cogs_subject_id' => -600,
            'inventory_subject_id' => -400,
            'sales_returns_subject_id' => 100,
        ]);
        $this->accountTotals($product, $fiscalEnd->copy()->addDay()->toDateString(), [
            'income_subject_id' => 5000,
            'cogs_subject_id' => -5000,
            'inventory_subject_id' => -5000,
            'sales_returns_subject_id' => 5000,
        ]);

        $report = app(WarehouseDashboardService::class)->report();
        $row = $report['rows']->firstWhere('id', $product->id);
        $period = collect($report['filterSummary'])->firstWhere('label', __('Period'));

        $this->assertSame(2.0, $row['inbound']);
        $this->assertSame(1000.0, $row['revenue_account']);
        $this->assertSame(600.0, $row['cogs_account']);
        $this->assertSame(400.0, $row['inventory_account']);
        $this->assertSame(100.0, $row['sales_return_account']);
        $this->assertSame(400.0, $row['sales_profit']);
        $this->assertSame(
            localizeNumber(toEnglish(jdate('Y/m/d', $fiscalStart->timestamp))).' - '
                .localizeNumber(toEnglish(jdate('Y/m/d', $fiscalEnd->timestamp))),
            $period['value']
        );
    }

    private function grant(string ...$permissions): void
    {
        $this->user->givePermissionTo(
            collect($permissions)
                ->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission]))
                ->all()
        );
    }

    private function invoice(InvoiceType $type, InvoiceStatus $status, int $number, string $date, float $amount): Invoice
    {
        return Invoice::create([
            'number' => $number,
            'date' => $date,
            'invoice_type' => $type,
            'status' => $status,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => $amount,
            'title' => $type->label(),
        ]);
    }

    private function inventoryBalance(Product $product, float $value, ?string $date = null): void
    {
        $document = Document::factory()->create([
            'company_id' => $this->companyId,
            'date' => $date ?? jalali_to_gregorian(1405, 2, 1, '-'),
        ]);

        Transaction::create([
            'document_id' => $document->id,
            'subject_id' => $product->inventory_subject_id,
            'user_id' => $this->user->id,
            'value' => $value,
            'desc' => 'inventory balance',
        ]);
    }

    private function stockMovement(Product $product, InvoiceType $type, float $quantity, int $number, string $date, float $amount = 0): void
    {
        $invoice = $this->invoice($type, InvoiceStatus::APPROVED, $number, $date, $amount);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $quantity > 0 ? $amount / $quantity : 0,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => $amount,
            'cog_after' => 100,
            'quantity_at' => 0,
        ]);
    }

    private function accountTotals(Product $product, string $date, array $values): void
    {
        $document = Document::factory()->create([
            'company_id' => $this->companyId,
            'date' => $date,
        ]);

        foreach ($values as $subjectKey => $value) {
            Transaction::create([
                'document_id' => $document->id,
                'subject_id' => $product->{$subjectKey},
                'user_id' => $this->user->id,
                'value' => $value,
                'desc' => 'report account total',
            ]);
        }
    }
}
