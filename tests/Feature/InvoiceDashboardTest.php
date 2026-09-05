<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\InvoiceDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceDashboardTest extends TestCase
{
    use RefreshDatabase;

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
        config([
            'active-company-id' => $this->companyId,
            'active-company-fiscal-year' => 1405,
        ]);

        $group = CustomerGroup::factory()->create(['company_id' => $this->companyId]);
        $this->customer = Customer::create([
            'company_id' => $this->companyId,
            'group_id' => $group->id,
            'name' => 'Dashboard Customer',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        unset($_COOKIE['active-company-id']);

        parent::tearDown();
    }

    public function test_authorized_user_can_view_invoice_dashboard(): void
    {
        $this->grant('invoices.dashboard');

        $response = $this->actingAs($this->user)->get(route('invoices.dashboard'));

        $response->assertOk();
        $response->assertViewIs('invoices.dashboard');
        $response->assertViewHasAll([
            'summary',
            'productTrend',
            'serviceTrend',
            'productSalesBreakdown',
            'serviceSalesBreakdown',
            'topSales',
            'recentInvoices',
        ]);
        $response->assertSee('data-invoice-dashboard', false);
        $response->assertViewHas('filters', [
            'start_date' => jalali_to_gregorian(1405, 1, 1, '-'),
            'end_date' => Carbon::parse(jalali_to_gregorian(1406, 1, 1, '-'))->subDay()->toDateString(),
        ]);
    }

    public function test_dates_outside_active_fiscal_year_are_rejected(): void
    {
        $this->grant('invoices.dashboard');

        $this->actingAs($this->user)
            ->get(route('invoices.dashboard', [
                'start_date' => '1404/12/29',
                'end_date' => '1405/12/29',
            ]))
            ->assertSessionHasErrors('start_date');

        $this->actingAs($this->user)
            ->get(route('invoices.dashboard', [
                'start_date' => '1405/01/01',
                'end_date' => '1406/01/01',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        $this->grant('invoices.dashboard');

        $this->actingAs($this->user)
            ->get(route('invoices.dashboard', [
                'start_date' => '1405/03/01',
                'end_date' => '1405/02/01',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_dashboard_requires_its_permission(): void
    {
        $this->actingAs($this->user)
            ->get(route('invoices.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_calculates_net_product_and_service_activity(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->companyId,
            'name' => 'Dashboard Product',
        ]);
        $service = Service::factory()->create([
            'company_id' => $this->companyId,
            'name' => 'Dashboard Service',
        ]);

        $sell = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED, 1, '2026-08-10', 1800);
        $this->item($sell, $product, 2, 500, 1000);
        $this->item($sell, $service, 1, 800, 800);

        $returnSell = $this->invoice(InvoiceType::RETURN_SELL, InvoiceStatus::APPROVED, 2, '2026-08-14', 300);
        $this->item($returnSell, $product, 1, 300, 300);

        $buy = $this->invoice(InvoiceType::BUY, InvoiceStatus::APPROVED, 3, '2026-08-18', 700);
        $this->item($buy, $product, 1, 400, 400);
        $this->item($buy, $service, 1, 300, 300);

        $returnBuy = $this->invoice(InvoiceType::RETURN_BUY, InvoiceStatus::APPROVED, 4, '2026-08-20', 100);
        $this->item($returnBuy, $service, 1, 100, 100);

        $ignored = $this->invoice(InvoiceType::SELL, InvoiceStatus::UNAPPROVED, 5, '2026-08-22', 10000);
        $this->item($ignored, $product, 1, 10000, 10000);

        $data = app(InvoiceDashboardService::class)->dashboard();

        $this->assertSame(1500.0, $data['summary']['net_sales']);
        $this->assertSame(600.0, $data['summary']['net_purchases']);
        $this->assertSame(900.0, $data['summary']['trade_balance']);
        $this->assertSame(1, $data['summary']['sales_count']);
        $this->assertSame(1, $data['summary']['purchase_count']);
        $this->assertSame(1800.0, $data['summary']['average_sale']);
        $this->assertSame(700.0, $data['summary']['average_purchase']);
        $this->assertSame(300.0, $data['summary']['sales_returns']);
        $this->assertSame(100.0, $data['summary']['purchase_returns']);

        $this->assertSame(700.0, array_sum($data['productTrend']['sell']));
        $this->assertSame(400.0, array_sum($data['productTrend']['buy']));
        $this->assertSame(800.0, array_sum($data['serviceTrend']['sell']));
        $this->assertSame(200.0, array_sum($data['serviceTrend']['buy']));
        $this->assertSame([
            ['id' => $product->id, 'name' => 'Dashboard Product', 'amount' => 700.0],
        ], $data['productSalesBreakdown']->all());
        $this->assertSame([
            ['id' => $service->id, 'name' => 'Dashboard Service', 'amount' => 800.0],
        ], $data['serviceSalesBreakdown']->all());
        $this->assertSame('Dashboard Service', $data['topSales']->first()['name']);
    }

    public function test_summary_uses_invoice_totals_including_invoice_level_adjustments(): void
    {
        $product = Product::factory()->create(['company_id' => $this->companyId]);

        $sell = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED, 1, '2026-08-10', 1200);
        $this->item($sell, $product, 1, 1000, 1000);

        $buy = $this->invoice(InvoiceType::BUY, InvoiceStatus::APPROVED, 2, '2026-08-11', 850);
        $this->item($buy, $product, 1, 800, 800);

        $returnSell = $this->invoice(InvoiceType::RETURN_SELL, InvoiceStatus::APPROVED, 3, '2026-08-12', 250);
        $this->item($returnSell, $product, 1, 200, 200);

        $data = app(InvoiceDashboardService::class)->dashboard();

        $this->assertSame(950.0, $data['summary']['net_sales']);
        $this->assertSame(850.0, $data['summary']['net_purchases']);
        $this->assertSame(1200.0, $data['summary']['average_sale']);
        $this->assertSame(850.0, $data['summary']['average_purchase']);
        $this->assertSame(250.0, $data['summary']['sales_returns']);
    }

    public function test_custom_duration_filters_invoices_and_company_scope_is_preserved(): void
    {
        $product = Product::factory()->create(['company_id' => $this->companyId]);

        $recent = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED, 1, '2026-08-25', 500);
        $this->item($recent, $product, 1, 500, 500);

        $old = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED, 2, '2026-06-01', 700);
        $this->item($old, $product, 1, 700, 700);

        $otherCompany = Company::factory()->create(['fiscal_year' => 1405]);
        Invoice::withoutGlobalScopes()->insert([
            'number' => 3,
            'date' => '2026-08-28',
            'invoice_type' => InvoiceType::SELL->value,
            'status' => InvoiceStatus::APPROVED->value,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'vat' => 0,
            'subtraction' => 0,
            'amount' => 9000,
            'company_id' => $otherCompany->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = app(InvoiceDashboardService::class)->dashboard([
            'start_date' => '2026-08-20',
            'end_date' => '2026-08-31',
        ]);

        $this->assertSame(500.0, $data['summary']['net_sales']);
        $this->assertCount(1, $data['recentInvoices']);
        $this->assertSame($recent->id, $data['recentInvoices']->first()->id);
    }

    public function test_product_profit_uses_cogs_snapshot_and_reverses_returns(): void
    {
        $product = Product::factory()->create(['company_id' => $this->companyId]);

        $sell = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED, 1, '2026-08-10', 1300);
        $this->item($sell, $product, 5, 200, 1100, 100);

        $return = $this->invoice(InvoiceType::RETURN_SELL, InvoiceStatus::APPROVED, 2, '2026-08-12', 220);
        $this->item($return, $product, 1, 200, 220, 100);

        $data = app(InvoiceDashboardService::class)->dashboard();

        $this->assertSame(880.0, $data['summary']['product_revenue']);
        $this->assertSame(400.0, $data['summary']['product_cogs']);
        $this->assertSame(480.0, $data['summary']['product_profit']);
        $this->assertSame(54.55, $data['summary']['product_profit_margin']);
    }

    public function test_dashboard_tables_link_to_item_and_invoice_details(): void
    {
        $this->grant('invoices.dashboard', 'invoices.show', 'products.show');
        $product = Product::factory()->create(['company_id' => $this->companyId, 'name' => 'Linked Product']);
        $sell = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED, 11, '2026-08-10', 500);
        $this->item($sell, $product, 1, 500, 500, 200);

        $this->actingAs($this->user)
            ->get(route('invoices.dashboard'))
            ->assertOk()
            ->assertSee(route('products.show', $product), false)
            ->assertSee(route('invoices.show', $sell), false);
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
            'vat' => 0,
            'subtraction' => 0,
            'amount' => $amount,
        ]);
    }

    private function item(Invoice $invoice, Product|Service $item, float $quantity, float $unitPrice, float $amount, float $cogAfter = 0): InvoiceItem
    {
        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => $item::class,
            'itemable_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => $amount,
            'cog_after' => $cogAfter,
        ]);
    }

    private function grant(string ...$permissions): void
    {
        $this->user->givePermissionTo(
            collect($permissions)
                ->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission]))
                ->all()
        );
    }
}
