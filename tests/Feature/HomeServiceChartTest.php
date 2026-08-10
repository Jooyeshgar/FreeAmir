<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HomeService;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class HomeServiceChartTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private Customer $customer;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->companyId = $company->id;
        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId, 'active-company-fiscal-year' => 1405]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    public function test_total_sell_amount_includes_only_approved_or_settled_sales(): void
    {
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 1, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, amount: 100);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 2, '-'), InvoiceType::SELL, InvoiceStatus::PARTIALLY_PAID, amount: 200);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 3, '-'), InvoiceType::SELL, InvoiceStatus::PAID, amount: 300);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 4, '-'), InvoiceType::SELL, InvoiceStatus::UNAPPROVED, amount: 9000);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 5, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, amount: 8000);

        $this->assertSame(600.0, $this->service()->totalSellAmount());
    }

    public function test_total_sell_amount_is_scoped_to_the_active_company(): void
    {
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 1, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, amount: 400);

        $otherCompany = Company::factory()->create(['fiscal_year' => 1405]);
        config(['active-company-id' => $otherCompany->id]);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 2, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, amount: 9000);

        config(['active-company-id' => $this->companyId]);

        $this->assertSame(400.0, $this->service()->totalSellAmount());
    }

    public function test_total_buy_amount_includes_only_approved_or_settled_purchases(): void
    {
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 1, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, amount: 100);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 2, '-'), InvoiceType::BUY, InvoiceStatus::PARTIALLY_PAID, amount: 200);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 3, '-'), InvoiceType::BUY, InvoiceStatus::PAID, amount: 300);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 4, '-'), InvoiceType::BUY, InvoiceStatus::UNAPPROVED, amount: 9000);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 5, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, amount: 8000);

        $this->assertSame(600.0, $this->service()->totalBuyAmount());
    }

    public function test_average_invoice_amounts_include_only_approved_or_settled_invoices(): void
    {
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 1, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, amount: 100);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 2, '-'), InvoiceType::SELL, InvoiceStatus::PAID, amount: 300);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 3, '-'), InvoiceType::SELL, InvoiceStatus::UNAPPROVED, amount: 9000);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 4, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, amount: 200);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 5, '-'), InvoiceType::BUY, InvoiceStatus::PARTIALLY_PAID, amount: 400);

        $this->assertSame(200.0, $this->service()->averageSellAmount());
        $this->assertSame(300.0, $this->service()->averageBuyAmount());
    }

    public function test_warehouse_financial_values_use_current_product_costs_and_prices(): void
    {
        $productA = $this->makeProduct();
        $productA->update(['quantity' => 2, 'average_cost' => 100, 'selling_price' => 160]);
        $productB = $this->makeProduct();
        $productB->update(['quantity' => 3, 'average_cost' => 300, 'selling_price' => 440]);

        $this->assertSame(1640.0, $this->service()->totalWarehouseRetailValue());
        $this->assertSame(200.0, $this->service()->averageWarehouseUnitCost());
        $this->assertSame(300.0, $this->service()->averageWarehouseSellingPrice());
    }

    public function test_employee_payroll_summary_uses_the_latest_payroll(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->companyId, 'user_id' => $this->user->id]);
        Payroll::factory()->create(['company_id' => $this->companyId, 'employee_id' => $employee->id, 'year' => 1405, 'month' => 1]);
        Payroll::factory()->create([
            'company_id' => $this->companyId,
            'employee_id' => $employee->id,
            'year' => 1405,
            'month' => 2,
            'net_payment' => 700,
            'total_earnings' => 1000,
            'total_deductions' => 300,
            'income_tax_amount' => 100,
        ]);

        $this->assertSame([
            'net_payment' => 700.0,
            'total_earnings' => 1000.0,
            'total_deductions' => 300.0,
            'income_tax_amount' => 100.0,
        ], $this->service()->employeePayrollSummary($this->user));
    }

    public function test_total_warehouse_value_sums_inventory_subject_balances(): void
    {
        $product = $this->makeProduct();
        $inventorySubject = Subject::withoutGlobalScopes()->find($product->inventory_subject_id);

        $doc = $this->makeDocument(jalali_to_gregorian(1405, 5, 10, '-'));
        Transaction::create([
            'value' => 1500,
            'subject_id' => $inventorySubject->id,
            'document_id' => $doc->id,
            'user_id' => $this->user->id,
            'desc' => 'inventory value',
        ]);

        $this->assertSame(1500.0, $this->service()->totalWarehouseValue());
    }

    public function test_total_warehouse_value_aggregates_across_multiple_products(): void
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();

        $subjectA = Subject::withoutGlobalScopes()->find($productA->inventory_subject_id);
        $subjectB = Subject::withoutGlobalScopes()->find($productB->inventory_subject_id);

        $doc = $this->makeDocument(jalali_to_gregorian(1405, 3, 1, '-'));
        Transaction::create(['value' => 600, 'subject_id' => $subjectA->id, 'document_id' => $doc->id, 'user_id' => $this->user->id, 'desc' => 'a']);
        Transaction::create(['value' => 400, 'subject_id' => $subjectB->id, 'document_id' => $doc->id, 'user_id' => $this->user->id, 'desc' => 'b']);

        $this->assertSame(1000.0, $this->service()->totalWarehouseValue());
    }

    public function test_total_warehouse_value_is_zero_when_no_products_exist(): void
    {
        $this->assertSame(0.0, $this->service()->totalWarehouseValue());
    }

    public function test_total_warehouse_value_skips_products_without_inventory_subject(): void
    {
        $product = $this->makeProduct();
        $product->update(['inventory_subject_id' => null]);

        $this->assertSame(0.0, $this->service()->totalWarehouseValue());
    }

    public function test_seller_sees_only_role_specific_quick_links(): void
    {
        $this->signInWith(['home.summary', 'invoices.index', 'invoices.create', 'customers.index', 'customers.create', 'customer-groups.index', 'crm.dashboard', 'ancillary-costs.index']);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewHas('canFinancial', false)
            ->assertViewHas('canSales', true)
            ->assertViewHas('canInventory', false)
            ->assertViewHas('homeVariant', 'sales')
            ->assertSee('data-home-variant="sales"', false)
            ->assertSee('data-home-area="sell-invoices-link"', false)
            ->assertSee('data-home-area="buy-invoices-link"', false)
            ->assertSee('data-home-area="create-sell-invoice-link"', false)
            ->assertSee('data-home-area="customers-link"', false)
            ->assertSee('data-home-area="create-customer-link"', false)
            ->assertSee('data-home-area="customer-groups-link"', false)
            ->assertSee('data-home-area="crm-dashboard"', false)
            ->assertSee('data-home-area="ancillary-costs"', false)
            ->assertSee(route('invoices.index', ['invoice_type' => 'buy']), false)
            ->assertSee('data-private-metric="sales"', false)
            ->assertSee('data-private-metric="purchases"', false)
            ->assertSee('data-private-metric="average_sales"', false)
            ->assertSee('data-private-metric="average_purchases"', false)
            ->assertDontSee('data-home-area="accounting"', false)
            ->assertDontSee('data-home-area="sales"', false)
            ->assertDontSee('data-home-area="inventory"', false)
            ->assertDontSee('data-home-area="crm"', false)
            ->assertDontSee('data-home-area="employee"', false);
    }

    public function test_warehousekeeper_does_not_receive_sales_content_from_product_access(): void
    {
        $this->signInWith(['home.summary', 'products.index', 'products.create', 'products.report', 'products.import', 'products.export', 'product-groups.index', 'product-groups.create', 'warehouse.dashboard']);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewHas('canSales', false)
            ->assertViewHas('canInventory', true)
            ->assertViewHas('homeVariant', 'inventory')
            ->assertSee('data-home-variant="inventory"', false)
            ->assertSee('data-home-area="products-link"', false)
            ->assertSee('data-home-area="create-product-link"', false)
            ->assertSee('data-home-area="warehouse-dashboard"', false)
            ->assertSee('data-home-area="product-groups"', false)
            ->assertSee('data-home-area="inventory-report"', false)
            ->assertSee('data-home-area="create-product-group-link"', false)
            ->assertSee('data-home-area="import-products-link"', false)
            ->assertSee('data-home-area="export-products-link"', false)
            ->assertSee('data-private-metric="inventory"', false)
            ->assertSee('data-private-metric="inventory_retail"', false)
            ->assertSee('data-private-metric="inventory_average_cost"', false)
            ->assertSee('data-private-metric="inventory_average_price"', false)
            ->assertDontSee('data-home-area="inventory"', false)
            ->assertDontSee('data-home-area="sales"', false)
            ->assertDontSee('data-private-metric="sales"', false);
    }

    public function test_accounting_user_sees_all_permitted_business_areas(): void
    {
        $this->signInWith(['home.summary', 'documents.show', 'documents.index', 'reports.ledger', 'bank-accounts.index', 'invoices.index', 'products.index', 'services.index', 'customers.index']);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewHas('homeVariant', 'accounting')
            ->assertSee('data-home-variant="accounting"', false)
            ->assertSee('data-home-area="accounting"', false)
            ->assertSee('data-home-area="sales"', false)
            ->assertSee('data-home-area="inventory"', false)
            ->assertSee('data-home-area="services"', false)
            ->assertSee('data-home-area="crm"', false)
            ->assertSee('data-private-metric="profit"', false)
            ->assertSee('data-private-metric="expenses"', false)
            ->assertSee('data-private-metric="sales"', false)
            ->assertSee('data-private-metric="purchases"', false)
            ->assertSee('data-private-metric="inventory"', false);
    }

    public function test_mixed_business_permissions_are_combined_but_personal_portal_is_hidden(): void
    {
        $this->signInWith(['home.summary', 'invoices.index', 'products.index', 'employee-portal.dashboard']);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewHas('canSeePersonalPortal', false)
            ->assertViewHas('homeVariant', 'operations')
            ->assertSee('data-home-variant="operations"', false)
            ->assertSee('data-home-area="sell-invoices-link"', false)
            ->assertSee('data-home-area="buy-invoices-link"', false)
            ->assertSee('data-home-area="products-link"', false)
            ->assertSee('data-home-area="employee-overview"', false)
            ->assertDontSee('data-home-area="create-sell-invoice-link"', false)
            ->assertDontSee('data-home-area="create-product-link"', false)
            ->assertDontSee('data-home-area="employee-attendance"', false)
            ->assertDontSee('data-home-area="sales"', false)
            ->assertDontSee('data-home-area="inventory"', false);
    }

    public function test_platform_administrator_can_return_to_management(): void
    {
        $this->signInWith(['access-super-admin-panel', 'customers.index']);

        $this->get(route('home'))->assertOk()
            ->assertViewHas('homeVariant', 'platform')
            ->assertSee('data-home-variant="platform"', false)
            ->assertSee(route('management.dashboard'), false);
    }

    public function test_company_administrator_gets_the_administration_variant(): void
    {
        $this->signInWith(['documents.show', 'configs.index']);

        $this->get(route('home'))->assertOk()
            ->assertViewHas('homeVariant', 'admin')
            ->assertSee('data-home-variant="admin"', false)
            ->assertSeeText(__('Administration workspace'));
    }

    public function test_employee_only_user_gets_the_employee_variant(): void
    {
        $user = $this->signInWith([
            'home.summary',
            'employee-portal.dashboard',
            'employee-portal.attendance-logs',
            'employee-portal.payrolls',
            'employee-portal.personnel-requests.index',
        ]);
        Employee::factory()->create(['company_id' => $this->companyId, 'user_id' => $user->id]);

        $this->get(route('home'))->assertOk()
            ->assertViewHas('homeVariant', 'employee')
            ->assertSee('data-home-variant="employee"', false)
            ->assertSee('data-home-area="employee-overview"', false)
            ->assertDontSee('data-home-area="employee-profile"', false)
            ->assertSee('data-home-area="employee-attendance"', false)
            ->assertSee('data-home-area="employee-payroll"', false)
            ->assertSee('data-home-area="employee-requests"', false)
            ->assertSee('data-private-metric="employee_net_payment"', false)
            ->assertSee('data-private-metric="employee_earnings"', false)
            ->assertSee('data-private-metric="employee_deductions"', false)
            ->assertSee('data-private-metric="employee_tax"', false)
            ->assertDontSee('data-home-area="employee"', false);
    }

    public function test_initial_home_request_does_not_calculate_private_metrics(): void
    {
        $this->signInWith(['home.summary', 'documents.show', 'invoices.index', 'products.index']);

        $service = $this->mock(HomeService::class);
        $service->shouldNotReceive('profitFromNonPermanentSubjects');
        $service->shouldNotReceive('totalSellAmount');
        $service->shouldNotReceive('totalBuyAmount');
        $service->shouldNotReceive('totalWarehouseValue');

        $this->get(route('home'))->assertOk()->assertSee('••••••', false);
    }

    public function test_each_private_summary_metric_is_fetched_independently(): void
    {
        $this->signInWith(['home.summary', 'documents.show', 'invoices.index', 'products.index']);

        $service = $this->mock(HomeService::class);
        $service->shouldReceive('profitFromNonPermanentSubjects')->twice()->andReturn([
            'incomeData' => [],
            'costData' => ['Operating expenses' => 450],
            'profit' => 1250,
        ]);
        $service->shouldReceive('totalSellAmount')->once()->andReturn(2500.5);
        $service->shouldReceive('totalBuyAmount')->once()->andReturn(1750);
        $service->shouldReceive('averageSellAmount')->once()->andReturn(625.5);
        $service->shouldReceive('averageBuyAmount')->once()->andReturn(875);
        $service->shouldReceive('totalWarehouseValue')->once()->andReturn(3750);
        $service->shouldReceive('totalWarehouseRetailValue')->once()->andReturn(5200);
        $service->shouldReceive('averageWarehouseUnitCost')->once()->andReturn(240);
        $service->shouldReceive('averageWarehouseSellingPrice')->once()->andReturn(360);

        $this->getJson(route('home.summary', ['metric' => 'profit']))->assertOk()->assertExactJson([
            'metric' => 'profit',
            'formattedValue' => formatNumber(1250),
            'unit' => __('Rial'),
        ]);

        $this->getJson(route('home.summary', ['metric' => 'sales']))->assertOk()->assertExactJson([
            'metric' => 'sales',
            'formattedValue' => formatNumber(2500.5),
            'unit' => __('Rial'),
        ]);

        $this->getJson(route('home.summary', ['metric' => 'inventory']))->assertOk()->assertExactJson([
            'metric' => 'inventory',
            'formattedValue' => formatNumber(3750),
            'unit' => __('Rial'),
        ]);

        $this->getJson(route('home.summary', ['metric' => 'expenses']))->assertOk()->assertExactJson([
            'metric' => 'expenses',
            'formattedValue' => formatNumber(450),
            'unit' => __('Rial'),
        ]);

        $this->getJson(route('home.summary', ['metric' => 'purchases']))->assertOk()->assertExactJson([
            'metric' => 'purchases',
            'formattedValue' => formatNumber(1750),
            'unit' => __('Rial'),
        ]);

        $this->getJson(route('home.summary', ['metric' => 'average_sales']))->assertOk()->assertExactJson([
            'metric' => 'average_sales',
            'formattedValue' => formatNumber(625.5),
            'unit' => __('Rial'),
        ]);

        $this->getJson(route('home.summary', ['metric' => 'average_purchases']))->assertOk()->assertExactJson([
            'metric' => 'average_purchases',
            'formattedValue' => formatNumber(875),
            'unit' => __('Rial'),
        ]);

        $this->getJson(route('home.summary', ['metric' => 'inventory_retail']))->assertOk()->assertExactJson([
            'metric' => 'inventory_retail',
            'formattedValue' => formatNumber(5200),
            'unit' => __('Rial'),
        ]);

        $this->getJson(route('home.summary', ['metric' => 'inventory_average_cost']))->assertOk()->assertExactJson([
            'metric' => 'inventory_average_cost',
            'formattedValue' => formatNumber(240),
            'unit' => __('Rial'),
        ]);

        $this->getJson(route('home.summary', ['metric' => 'inventory_average_price']))->assertOk()->assertExactJson([
            'metric' => 'inventory_average_price',
            'formattedValue' => formatNumber(360),
            'unit' => __('Rial'),
        ]);
    }

    public function test_employee_private_payroll_metrics_are_fetched_independently(): void
    {
        $user = $this->signInWith(['home.summary', 'employee-portal.dashboard']);
        $service = $this->mock(HomeService::class);
        $service->shouldReceive('employeePayrollSummary')->times(4)->with($user)->andReturn([
            'net_payment' => 700,
            'total_earnings' => 1000,
            'total_deductions' => 300,
            'income_tax_amount' => 100,
        ]);

        foreach ([
            'employee_net_payment' => 700,
            'employee_earnings' => 1000,
            'employee_deductions' => 300,
            'employee_tax' => 100,
        ] as $metric => $value) {
            $this->getJson(route('home.summary', ['metric' => $metric]))->assertOk()->assertExactJson([
                'metric' => $metric,
                'formattedValue' => formatNumber($value),
                'unit' => __('Rial'),
            ]);
        }
    }

    public function test_metric_specific_permission_is_checked_before_calculation(): void
    {
        $this->signInWith(['home.summary', 'invoices.index']);

        $service = $this->mock(HomeService::class);
        $service->shouldNotReceive('profitFromNonPermanentSubjects');

        $this->getJson(route('home.summary', ['metric' => 'profit']))->assertForbidden();
    }

    public function test_unknown_private_summary_metric_returns_not_found(): void
    {
        $this->signInWith(['home.summary', 'invoices.index']);

        $this->getJson(route('home.summary', ['metric' => 'unknown']))->assertNotFound();
    }

    private function signInWith(array $permissions): User
    {
        $user = User::factory()->create();
        Company::find($this->companyId)->users()->attach($user);

        $permissionModels = collect(['home', ...$permissions])->unique()->map(fn (string $name) => Permission::firstOrCreate(['name' => $name]));

        $user->givePermissionTo($permissionModels);
        $this->actingAs($user);

        return $user;
    }

    private function service(): HomeService
    {
        return new HomeService(new SubjectService);
    }

    private function makeProduct(): Product
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);

        return Product::factory()->withGroup($group)->withSubjects()->create(['company_id' => $this->companyId]);
    }

    private function makeInvoice(
        string $date,
        InvoiceType $type = InvoiceType::BUY,
        InvoiceStatus $status = InvoiceStatus::APPROVED,
        float $amount = 100,
    ): Invoice {
        return Invoice::create([
            'number' => Invoice::withoutGlobalScopes()->max('number') + 1,
            'date' => $date,
            'invoice_type' => $type,
            'status' => $status,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => $amount,
            'title' => 'test',
        ]);
    }

    private function makeDocument(string $date): Document
    {
        return Document::create([
            'number' => Document::withoutGlobalScopes()->max('number') + 1,
            'date' => $date,
            'creator_id' => $this->user->id,
            'title' => 'test',
            'company_id' => $this->companyId,
        ]);
    }
}
