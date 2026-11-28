<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\User;
use App\Services\CostIncomeService;
use App\Services\DocumentService;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CostsPartnersMemorandumDebugTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FiscalYear $year;

    private Subject $cash;

    private Subject $bank;

    private Subject $payable;

    private Subject $expense;

    private Subject $partners;

    private Subject $memorandum;

    private Subject $contra;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->year = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $this->year->id, 'active-company-fiscal-year' => 1405]);
        $this->user = User::factory()->create(['name' => 'مریم احمدی']);
        $this->actingAs($this->user);
        $this->cash = $this->subject('010', 'صندوق', true);
        $this->bank = $this->subject('011', 'بانک ملت', true);
        $this->payable = $this->subject('020', 'بدهی به نرگس امینی', true);
        $this->expense = $this->subject('040', 'هزینه اجاره', false);
        $this->partners = $this->subject('068', 'جاری شرکا', true);
        $this->memorandum = $this->subject('064', 'حساب‌های انتظامی', true);
        $this->contra = $this->subject('065', 'طرف حساب‌های انتظامی', true);
    }

    private function subject(string $code, string $name, bool $permanent, ?Subject $parent = null): Subject
    {
        return Subject::create(['name' => $name, 'code' => $code, 'parent_id' => $parent?->id, 'type' => SubjectType::BOTH, 'is_permanent' => $permanent]);
    }

    private function postEntries(array $values, bool $approved = true): Document
    {
        $data = ['date' => '2026-04-10', 'title' => 'سناریوی محلی مستقل'];
        if ($approved) {
            $data += ['approved_at' => now(), 'approver_id' => $this->user->id];
        }
        $entries = [];
        foreach ($values as $id => $value) {
            $entries[] = ['subject_id' => $id, 'value' => $value, 'desc' => 'داده فرضی آزمون'];
        }

        return DocumentService::createDocument($this->user, $data, $entries);
    }

    public static function expenseMethods(): array
    {
        return [['cash'], ['bank'], ['payable']];
    }

    #[DataProvider('expenseMethods')]
    public function test_expense_by_cash_bank_or_credit_and_settlement(string $method): void
    {
        $fund = $this->{$method};
        $document = $this->postEntries([$this->expense->id => -1250000, $fund->id => 1250000]);
        if ($method === 'payable') {
            $this->postEntries([$fund->id => -1250000, $this->bank->id => 1250000]);
            $this->assertEquals(0, SubjectService::sumSubject($fund));
        }
        $this->assertEquals(0, $document->transactions()->sum('value'));
        $summary = app(CostIncomeService::class)->summary();
        $this->assertEquals(1250000, $summary['totalCost']);
        $this->assertEquals(-1250000, $summary['profit']);
    }

    public function test_two_partners_deposits_and_withdrawals_do_not_become_operating_income_or_cost(): void
    {
        $reza = $this->subject('068001', 'رضا محمدی', true, $this->partners);
        $sara = $this->subject('068002', 'سارا کاظمی', true, $this->partners);
        $this->postEntries([$this->bank->id => -2000000, $reza->id => 2000000]);
        $this->postEntries([$this->cash->id => -3000000, $sara->id => 3000000]);
        $this->postEntries([$reza->id => -500000, $this->bank->id => 500000]);
        $this->assertEquals(1500000, SubjectService::sumSubject($reza));
        $this->assertEquals(3000000, SubjectService::sumSubject($sara));
        $summary = app(CostIncomeService::class)->summary();
        $this->assertEquals(0, $summary['totalIncome']);
        $this->assertEquals(0, $summary['totalCost']);
    }

    public function test_expense_paid_by_a_partner_is_cost_and_partner_debt(): void
    {
        $partner = $this->subject('068001', 'بهزاد کریمی', true, $this->partners);
        $this->postEntries([$this->expense->id => -750000, $partner->id => 750000]);
        $this->assertEquals(750000, SubjectService::sumSubject($partner));
        $this->assertEquals(750000, app(CostIncomeService::class)->summary()['totalCost']);
    }

    public function test_guarantee_registration_and_release_are_balanced_without_affecting_profit(): void
    {
        $guarantee = $this->subject('064001', 'تضمین حسن انجام کار', true, $this->memorandum);
        $counterparty = $this->subject('065001', 'تضمین نزد مریم احمدی', true, $this->contra);
        $first = $this->postEntries([$guarantee->id => -9000000, $counterparty->id => 9000000]);
        $this->assertEquals(-9000000, SubjectService::sumSubject($guarantee));
        $release = $this->postEntries([$guarantee->id => 9000000, $counterparty->id => -9000000]);
        $this->assertEquals(0, $first->transactions()->sum('value'));
        $this->assertEquals(0, $release->transactions()->sum('value'));
        $this->assertEquals(0, SubjectService::sumSubject($guarantee->fresh()));
        $this->assertEquals(0, SubjectService::sumSubject($counterparty->fresh()));
        $this->assertEquals(0, app(CostIncomeService::class)->summary()['profit']);
    }

    public function test_draft_expense_is_excluded_until_it_is_approved(): void
    {
        $document = $this->postEntries([$this->expense->id => -100000, $this->cash->id => 100000], false);
        $this->assertEquals(0, app(CostIncomeService::class)->summary()['totalCost']);
        DocumentService::changeDocumentStatus($document, $this->user, 'approved');
        $this->assertEquals(100000, app(CostIncomeService::class)->summary()['totalCost']);
    }

    public function test_sub_rial_cost_survives_summary_and_monthly_aggregation(): void
    {
        $this->postEntries([$this->expense->id => '-0.75', $this->cash->id => '0.75']);
        $summary = app(CostIncomeService::class)->summary();
        $this->assertEqualsWithDelta(0.75, $summary['totalCost'], 0.00001);
        $this->assertEqualsWithDelta(0.75, $summary['costBreakdown']['هزینه اجاره'] ?? 0, 0.00001);
        $monthly = app(CostIncomeService::class)->monthlyIncomeAndCostByMonth();
        $this->assertEqualsWithDelta(-0.75, $monthly['cost'][1], 0.00001);
    }

    public function test_unbalanced_document_cannot_be_created_as_approved_and_is_rolled_back(): void
    {
        try {
            $this->postEntries([$this->expense->id => -100000, $this->cash->id => 90000]);
            $this->fail('Unbalanced document was approved before its transactions were checked.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transactions', $e->errors());
        }
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('transactions', 0);
    }
}
