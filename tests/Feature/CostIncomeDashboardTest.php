<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CostIncomeService;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class CostIncomeDashboardTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private int $companyId;

    private CustomerGroup $customerGroup;

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

        $this->customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
    }

    public function test_user_with_permission_can_view_dashboard(): void
    {
        $this->grant('reports.cost-income');

        $response = $this->actingAs($this->user)->get(route('reports.cost-income'));

        $response->assertOk();
        $response->assertViewIs('reports.cost-income.index');
        $response->assertViewHas('totalIncome');
        $response->assertViewHas('totalCost');
        $response->assertViewHas('profit');
        $response->assertViewHas('margin');
        $response->assertViewHas('monthlyIncome');
        $response->assertViewHas('monthlyCost');
        $response->assertViewHas('debtors');
        $response->assertViewHas('creditors');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $response = $this->actingAs($this->user)->get(route('reports.cost-income'));

        $response->assertForbidden();
    }

    public function test_summary_classifies_balance_by_sign_and_computes_profit(): void
    {
        $income = $this->nonPermanentSubject('Sales revenue');
        $cost = $this->nonPermanentSubject('Wages');

        $this->transaction($income->id, 1000);
        $this->transaction($cost->id, -400);

        $summary = $this->service()->summary();

        $this->assertSame(1000, $summary['totalIncome']);
        $this->assertSame(400, $summary['totalCost']);
        $this->assertSame(600, $summary['profit']);
        $this->assertSame(60, $summary['margin']); // 600 / 1000 * 100
        $this->assertSame(['Sales revenue' => 1000], $summary['incomeBreakdown']);
        $this->assertSame(['Wages' => 400], $summary['costBreakdown']);
    }

    public function test_summary_excludes_permanent_subjects(): void
    {
        $income = $this->nonPermanentSubject('Service revenue');
        $this->transaction($income->id, 1000);

        // A permanent subject (e.g. a balance-sheet account) must never affect P&L.
        $permanent = Subject::factory()->create([
            'company_id' => $this->companyId,
            'name' => 'Bank',
            'is_permanent' => true,
        ]);
        $this->transaction($permanent->id, 9999);

        $summary = $this->service()->summary();

        $this->assertSame(1000, $summary['totalIncome']);
        $this->assertSame(0, $summary['totalCost']);
    }

    public function test_summary_margin_is_zero_when_there_is_no_income(): void
    {
        $cost = $this->nonPermanentSubject('Rent');
        $this->transaction($cost->id, -500);

        $summary = $this->service()->summary();

        $this->assertSame(0, $summary['totalIncome']);
        $this->assertSame(500, $summary['totalCost']);
        $this->assertSame(-500, $summary['profit']);
        $this->assertSame(0, $summary['margin']);
    }

    public function test_monthly_income_and_cost_buckets_by_month_and_sign(): void
    {
        $income = $this->nonPermanentSubject('Sales');
        $cost = $this->nonPermanentSubject('Cost');

        $this->transaction($income->id, 1000, jalali_to_gregorian(1405, 5, 10, '-')); // مرداد
        $this->transaction($cost->id, -300, jalali_to_gregorian(1405, 8, 12, '-'));   // آبان

        $monthly = $this->service()->monthlyIncomeAndCost();

        $this->assertSame(1000, $monthly['income']['مرداد']);
        $this->assertSame(0, $monthly['income']['آبان']);
        $this->assertSame(300, $monthly['cost']['آبان']);
        $this->assertSame(0, $monthly['cost']['مرداد']);
    }

    public function test_top_customers_splits_debtors_and_creditors_by_sign(): void
    {
        $debtor = $this->customerWithBalance('Owes us', -700);
        $creditor = $this->customerWithBalance('We owe', 500);

        $result = $this->service()->topCustomers();

        $this->assertCount(1, $result['debtors']);
        $this->assertCount(1, $result['creditors']);

        $this->assertSame($debtor->subject_id, $result['debtors'][0]['subject_id']);
        $this->assertSame(700, $result['debtors'][0]['amount']);

        $this->assertSame($creditor->subject_id, $result['creditors'][0]['subject_id']);
        $this->assertSame(500, $result['creditors'][0]['amount']);
    }

    private function service(): CostIncomeService
    {
        return new CostIncomeService(new SubjectService);
    }

    private function nonPermanentSubject(string $name): Subject
    {
        return Subject::factory()->create([
            'company_id' => $this->companyId,
            'name' => $name,
            'is_permanent' => false,
        ]);
    }

    private function customerWithBalance(string $name, int $balance): Customer
    {
        $customer = Customer::factory()
            ->withGroup($this->customerGroup)
            ->withSubject()
            ->create(['company_id' => $this->companyId, 'name' => $name]);

        $this->transaction($customer->subject_id, $balance);

        return $customer;
    }

    private function transaction(int $subjectId, float $value, ?string $date = null): Transaction
    {
        $document = $this->makeDocument($date ?? jalali_to_gregorian(1405, 1, 1, '-'));

        return Transaction::create([
            'value' => $value,
            'subject_id' => $subjectId,
            'document_id' => $document->id,
            'user_id' => $this->user->id,
            'desc' => 'test',
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

    private function grant(string ...$permissions): void
    {
        $this->user->givePermissionTo(
            collect($permissions)
                ->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission]))
                ->all()
        );
    }
}
