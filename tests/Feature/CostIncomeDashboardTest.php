<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CostIncomeService;
use App\Services\SubjectService;
use App\Services\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        config(['cache.default' => 'array']);
        Cache::flush();

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
        $response->assertViewHas('forecastIncome');
        $response->assertViewHas('forecastExpense');
        $response->assertViewHas('monthlyBudgetLinks', fn (array $links) => count($links) === 12 && str_contains($links[0], 'month=1'));
        $response->assertViewHas('debtors');
        $response->assertViewHas('creditors');
        $response->assertSee('"type":"line"', false);
        $response->assertSee("getElementsAtEventForMode(event, 'index', { intersect: true }", false);
        $response->assertDontSee("getElementsAtEventForMode(event, 'index', { intersect: false }", false);
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
        $this->transaction($income->id, -200, jalali_to_gregorian(1405, 8, 11, '-'));  // آبان؛ جهت ماهانه باید مستقل باشد
        $this->transaction($cost->id, -300, jalali_to_gregorian(1405, 8, 12, '-'));   // آبان

        $monthly = $this->service()->monthlyIncomeAndCost();

        $this->assertSame(1000, $monthly['income']['مرداد']);
        $this->assertSame(0, $monthly['income']['آبان']);
        $this->assertSame(500, $monthly['cost']['آبان']);
        $this->assertSame(0, $monthly['cost']['مرداد']);
    }

    public function test_monthly_totals_match_trial_balance_from_roots_to_deepest_descendants(): void
    {
        $incomeRoot = $this->nonPermanentSubject('Hierarchy income root', SubjectType::CREDITOR);
        $incomeChild = $this->childSubject($incomeRoot, 'Hierarchy income child', SubjectType::CREDITOR);
        $incomeLeaf = $this->childSubject($incomeChild, 'Hierarchy income leaf', SubjectType::CREDITOR);
        $expenseRoot = $this->nonPermanentSubject('Hierarchy expense root', SubjectType::DEBTOR);
        $expenseChild = $this->childSubject($expenseRoot, 'Hierarchy expense child', SubjectType::DEBTOR);
        $expenseLeaf = $this->childSubject($expenseChild, 'Hierarchy expense leaf', SubjectType::DEBTOR);
        $counter = $this->permanentCounterSubject();

        $document = $this->balancedDocument([
            [$incomeRoot, 100],
            [$incomeChild, 200],
            [$incomeLeaf, 300],
            [$expenseRoot, -50],
            [$expenseChild, -150],
            [$expenseLeaf, -250],
        ], jalali_to_gregorian(1405, 4, 15, '-'), $counter);

        $monthly = $this->service()->monthlyIncomeAndCost();
        $trialBalance = app(TrialBalanceService::class)->getTrialBalanceData(new Request([
            'start_document_number' => 1,
        ]));
        $trialIncome = $trialBalance['subjects']->firstWhere('id', $incomeRoot->id);
        $trialExpense = $trialBalance['subjects']->firstWhere('id', $expenseRoot->id);

        $this->assertSame(0.0, (float) $document->transactions()->sum('value'));
        $this->assertNotNull($trialIncome);
        $this->assertNotNull($trialExpense);
        $this->assertSame(600.0, (float) $trialIncome->balance);
        $this->assertSame(-450.0, (float) $trialExpense->balance);
        $this->assertSame(600, $monthly['income']['تیر']);
        $this->assertSame(450, $monthly['cost']['تیر']);
    }

    public function test_monthly_totals_net_each_root_tree_once_before_classifying_income_or_cost(): void
    {
        $incomeRoot = $this->nonPermanentSubject('Netted income root', SubjectType::CREDITOR);
        $incomeChild = $this->childSubject($incomeRoot, 'Netted income child', SubjectType::BOTH);
        $incomeLeaf = $this->childSubject($incomeChild, 'Netted income leaf', SubjectType::BOTH);
        $expenseRoot = $this->nonPermanentSubject('Netted expense root', SubjectType::DEBTOR);
        $expenseChild = $this->childSubject($expenseRoot, 'Netted expense child', SubjectType::BOTH);
        $expenseLeaf = $this->childSubject($expenseChild, 'Netted expense leaf', SubjectType::BOTH);
        $counter = $this->permanentCounterSubject();

        $this->balancedDocument([
            [$incomeRoot, 1000],
            [$incomeLeaf, -200],
            [$expenseRoot, 100],
            [$expenseLeaf, -900],
        ], jalali_to_gregorian(1405, 7, 10, '-'), $counter);

        $monthly = $this->service()->monthlyIncomeAndCost();

        $this->assertSame(800, $monthly['income']['مهر']);
        $this->assertSame(800, $monthly['cost']['مهر']);
        $this->assertSame(0, $monthly['income']['آبان']);
        $this->assertSame(0, $monthly['cost']['آبان']);
    }

    public function test_monthly_totals_include_leap_esfand_day_thirty_and_exclude_next_fiscal_year(): void
    {
        $this->setFiscalYear(1403);
        $income = $this->nonPermanentSubject('Leap year income', SubjectType::CREDITOR);
        $expense = $this->nonPermanentSubject('Leap year expense', SubjectType::DEBTOR);
        $counter = $this->permanentCounterSubject();

        $this->balancedDocument([
            [$income, 700],
            [$expense, -400],
        ], jalali_to_gregorian(1403, 12, 30, '-'), $counter);
        $this->balancedDocument([
            [$income, 900],
            [$expense, -600],
        ], jalali_to_gregorian(1404, 1, 1, '-'), $counter);

        $monthly = $this->service()->monthlyIncomeAndCost();

        $this->assertSame(700, $monthly['income']['اسفند']);
        $this->assertSame(400, $monthly['cost']['اسفند']);
        $this->assertSame(0, $monthly['income']['فروردین']);
        $this->assertSame(0, $monthly['cost']['فروردین']);
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

    private function nonPermanentSubject(string $name, SubjectType $type = SubjectType::BOTH): Subject
    {
        return Subject::factory()->create([
            'company_id' => $this->companyId,
            'name' => $name,
            'type' => $type,
            'is_permanent' => false,
        ]);
    }

    private function childSubject(Subject $parent, string $name, SubjectType $type): Subject
    {
        return Subject::factory()->withParent($parent)->create([
            'company_id' => $this->companyId,
            'name' => $name,
            'type' => $type,
            'is_permanent' => false,
        ]);
    }

    private function permanentCounterSubject(): Subject
    {
        return Subject::factory()->create([
            'company_id' => $this->companyId,
            'name' => 'Monthly totals counter account',
            'type' => SubjectType::BOTH,
            'is_permanent' => true,
        ]);
    }

    /**
     * @param  array<int, array{0: Subject, 1: int|float}>  $lines
     */
    private function balancedDocument(array $lines, string $date, Subject $counter): Document
    {
        $document = $this->makeDocument($date);
        $total = 0.0;

        foreach ($lines as [$subject, $value]) {
            $total += $value;
            Transaction::create([
                'value' => $value,
                'subject_id' => $subject->id,
                'document_id' => $document->id,
                'user_id' => $this->user->id,
                'desc' => 'monthly hierarchy test',
            ]);
        }

        Transaction::create([
            'value' => -$total,
            'subject_id' => $counter->id,
            'document_id' => $document->id,
            'user_id' => $this->user->id,
            'desc' => 'monthly hierarchy test counter',
        ]);

        return $document;
    }

    private function setFiscalYear(int $year): void
    {
        Company::withoutGlobalScopes()->findOrFail($this->companyId)->update(['fiscal_year' => $year]);
        config(['active-company-fiscal-year' => $year]);
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
