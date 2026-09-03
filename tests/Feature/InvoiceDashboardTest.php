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
            'salesMix',
            'topSales',
            'recentInvoices',
        ]);
        $response->assertSee('data-invoice-dashboard', false);
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
            ['name' => __('Products'), 'amount' => 700.0],
            ['name' => __('Services'), 'amount' => 800.0],
        ], $data['salesMix']);
        $this->assertSame('Dashboard Service', $data['topSales']->first()['name']);
    }

    public function test_period_filter_excludes_old_invoices_and_company_scope_is_preserved(): void
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

        $data = app(InvoiceDashboardService::class)->dashboard(['period' => 'month']);

        $this->assertSame(500.0, $data['summary']['net_sales']);
        $this->assertCount(1, $data['recentInvoices']);
        $this->assertSame($recent->id, $data['recentInvoices']->first()->id);
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

    private function item(Invoice $invoice, Product|Service $item, float $quantity, float $unitPrice, float $amount): InvoiceItem
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
