<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SubjectType;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Subject;
use App\Models\User;
use App\Services\CostIncomeService;
use App\Services\DocumentService;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPrecisionDebugTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Subject $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $year = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $year->id, 'active-company-fiscal-year' => 1405]);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->cash = Subject::create(['code' => '011', 'name' => 'صندوق', 'parent_id' => null, 'type' => SubjectType::BOTH, 'is_permanent' => true]);
    }

    public function test_monthly_subject_totals_keep_fractional_income_and_cost(): void
    {
        $income = Subject::create(['code' => '050', 'name' => 'درآمد خدمات', 'parent_id' => null, 'type' => SubjectType::CREDITOR, 'is_permanent' => false]);
        $cost = Subject::create(['code' => '040', 'name' => 'هزینه خدمات', 'parent_id' => null, 'type' => SubjectType::DEBTOR, 'is_permanent' => false]);
        DocumentService::createDocument($this->user, ['date' => '2026-04-10', 'approved_at' => now(), 'approver_id' => $this->user->id], [
            ['subject_id' => $income->id, 'value' => '0.75'], ['subject_id' => $cost->id, 'value' => '-0.25'], ['subject_id' => $this->cash->id, 'value' => '-0.50'],
        ]);
        $this->assertEqualsWithDelta(0.75, app(SubjectService::class)->sumApprovedSubjectWithDateRange($income)[1], 0.00001);
        $monthly = app(CostIncomeService::class)->monthlyIncomeAndCostByMonth();
        $this->assertEqualsWithDelta(0.75, $monthly['income'][1], 0.00001);
        $this->assertEqualsWithDelta(-0.25, $monthly['cost'][1], 0.00001);
        $this->assertEqualsWithDelta(0.50, app(CostIncomeService::class)->summary()['profit'], 0.00001);
    }

    public function test_invoice_summary_keeps_fractional_returns_and_amounts(): void
    {
        foreach ([[InvoiceType::SELL, '1000.75'], [InvoiceType::RETURN_SELL, '0.25'], [InvoiceType::BUY, '800.50'], [InvoiceType::RETURN_BUY, '100.25']] as $index => [$type, $amount]) {
            Invoice::withoutEvents(fn () => Invoice::create(['invoice_type' => $type, 'number' => $index + 1, 'date' => '2026-04-10', 'status' => InvoiceStatus::UNAPPROVED, 'amount' => $amount, 'vat' => 0, 'fiscal_year_id' => getActiveFiscalYear()]));
        }
        $summary = app(CostIncomeService::class)->invoiceSummary();
        $this->assertEqualsWithDelta(1000.50, $summary['netSales'], 0.00001);
        $this->assertEqualsWithDelta(700.25, $summary['netPurchases'], 0.00001);
        $this->assertEqualsWithDelta(300.25, $summary['tradingMargin'], 0.00001);
    }

    public function test_top_customer_report_keeps_a_sub_rial_debt(): void
    {
        $group = CustomerGroup::factory()->withSubject()->create();
        $customer = Customer::factory()->withGroup($group)->withSubject()->create(['name' => 'نرگس امینی']);
        DocumentService::createDocument($this->user, ['date' => '2026-04-10'], [
            ['subject_id' => $customer->subject_id, 'value' => '-0.75'], ['subject_id' => $this->cash->id, 'value' => '0.75'],
        ]);
        $report = app(CostIncomeService::class)->topCustomers();
        $this->assertCount(1, $report['debtors']);
        $this->assertSame('نرگس امینی', $report['debtors'][0]['name']);
        $this->assertEqualsWithDelta(0.75, $report['debtors'][0]['amount'], 0.00001);
    }
}
