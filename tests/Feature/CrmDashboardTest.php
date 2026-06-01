<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CrmDashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class CrmDashboardTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private Customer $customer;

    private int $companyId;

    private int $fiscalYear;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the current Jalali year so "this month" ranges always contain "today".
        $this->fiscalYear = (int) toEnglish(jdate('Y'));

        $company = Company::factory()->create(['fiscal_year' => $this->fiscalYear]);
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId, 'active-company-fiscal-year' => $this->fiscalYear]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $customerGroup = CustomerGroup::factory()->withSubject()->create([
            'company_id' => $this->companyId,
            'name' => 'VIP',
        ]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'name' => 'Acme Co',
        ]);
    }

    public function test_user_with_permission_can_view_crm_dashboard(): void
    {
        $this->grant('crm.dashboard');

        $response = $this->actingAs($this->user)->get(route('crm.dashboard'));

        $response->assertOk();
        $response->assertViewIs('crm.dashboard');
        $response->assertViewHas('metrics');
        $response->assertViewHas('aging');
        $response->assertViewHas('salesByCategory');
        $response->assertViewHas('salesTrend');
        $response->assertViewHas('recentInvoices');
        $response->assertViewHas('topBuyersYear');
    }

    public function test_user_without_permission_cannot_view_crm_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get(route('crm.dashboard'));

        $response->assertForbidden();
    }

    public function test_service_computes_sales_receivables_and_aging(): void
    {
        $today = Carbon::today()->format('Y-m-d');

        // Approved sell invoice for 1,000 dated today.
        $this->makeSellInvoice($today, 1000);

        // Ledger debit on the customer subject => the customer owes 1,000 (unpaid).
        $this->postToCustomerLedger($today, -1000);

        $data = app(CrmDashboardService::class)->dashboard();

        $this->assertEqualsWithDelta(1000, $data['metrics']['salesThisMonth'], 0.001);
        $this->assertEqualsWithDelta(1000, $data['metrics']['totalUnpaid'], 0.001);
        $this->assertSame(1, $data['metrics']['unpaidCustomersCount']);

        // Whole receivable is brand new => sits in the first aging bucket.
        $this->assertEqualsWithDelta(1000, $data['aging'][0]['amount'], 0.001);
        $this->assertEqualsWithDelta(0, $data['aging'][3]['amount'], 0.001);

        // Sales by category and top buyers reflect the single customer.
        $this->assertSame('VIP', $data['salesByCategory'][0]['name']);
        $this->assertEqualsWithDelta(1000, $data['salesByCategory'][0]['amount'], 0.001);
        $this->assertSame('Acme Co', $data['topBuyersYear'][0]['name']);
    }

    public function test_payment_settles_receivable_and_counts_as_paid(): void
    {
        $today = Carbon::today()->format('Y-m-d');

        $this->makeSellInvoice($today, 1000);
        $this->postToCustomerLedger($today, -1000); // invoice debit
        $this->postToCustomerLedger($today, 600);   // partial receipt

        $data = app(CrmDashboardService::class)->dashboard();

        $this->assertEqualsWithDelta(400, $data['metrics']['totalUnpaid'], 0.001);
        $this->assertEqualsWithDelta(600, $data['metrics']['paidThisMonth'], 0.001);
    }

    public function test_yearly_sales_include_esfand_30_in_jalali_leap_years(): void
    {
        // 1403 is a Jalali leap year, so Esfand has 30 days. Esfand 30, 1403
        // maps to 2025-03-20; the old fixed Esfand-29 end date excluded it.
        config(['active-company-fiscal-year' => 1403]);

        $this->makeSellInvoice('2025-03-20', 1000);

        $data = app(CrmDashboardService::class)->dashboard();

        // The Esfand-30 sale must appear in the yearly top buyers and the
        // Esfand bucket (index 11) of the sales trend.
        $this->assertSame('Acme Co', $data['topBuyersYear'][0]['name']);
        $this->assertEqualsWithDelta(1000, $data['topBuyersYear'][0]['amount'], 0.001);
        $this->assertEqualsWithDelta(1000, $data['salesTrend']['values'][11], 0.001);
    }

    private function makeSellInvoice(string $date, float $amount, InvoiceType $type = InvoiceType::SELL): Invoice
    {
        return Invoice::create([
            'number' => Invoice::withoutGlobalScopes()->max('number') + 1,
            'date' => $date,
            'invoice_type' => $type,
            'status' => InvoiceStatus::APPROVED,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => $amount,
            'title' => 'test',
        ]);
    }

    private function postToCustomerLedger(string $date, float $value): Transaction
    {
        $document = Document::create([
            'number' => Document::withoutGlobalScopes()->max('number') + 1,
            'date' => $date,
            'creator_id' => $this->user->id,
            'title' => 'test',
            'company_id' => $this->companyId,
        ]);

        return Transaction::create([
            'subject_id' => $this->customer->subject_id,
            'document_id' => $document->id,
            'value' => $value,
            'desc' => 'test',
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
