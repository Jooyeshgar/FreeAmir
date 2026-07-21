<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Document;
use App\Models\MonthlyBudget;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FiscalYearService;
use App\Services\MonthlyBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MonthlyBudgetTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private MonthlyBudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->user = User::factory()->create();
        $this->company->users()->syncWithoutDetaching([$this->user->id]);

        $this->service = new MonthlyBudgetService;

        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        $_COOKIE['active-company-id'] = (string) $this->company->id;
        config([
            'active-company-id' => $this->company->id,
            'active-company-fiscal-year' => 1405,
        ]);
    }

    private function temporarySubject(string $name, SubjectType $type): Subject
    {
        return Subject::factory()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'type' => $type,
            'is_permanent' => false,
            'parent_id' => null,
        ]);
    }

    private function budget(Subject $subject, string $type, float|int|string $amount, int $month): MonthlyBudget
    {
        return MonthlyBudget::create([
            'company_id' => $this->company->id,
            'subject_id' => $subject->id,
            'month' => $month,
            'budget_type' => $type,
            'forecast_amount' => $amount,
        ]);
    }

    private function transaction(Subject $subject, int $value, int $month): void
    {
        $document = Document::create([
            'number' => Document::withoutGlobalScopes()->max('number') + 1,
            'date' => jalali_to_gregorian(1405, $month, 10, '-'),
            'creator_id' => $this->user->id,
            'title' => 'budget actual',
            'company_id' => $this->company->id,
        ]);

        Transaction::create([
            'value' => $value,
            'subject_id' => $subject->id,
            'document_id' => $document->id,
            'user_id' => $this->user->id,
            'desc' => 'budget test',
        ]);
    }

    private function grant(string ...$permissions): void
    {
        $this->user->givePermissionTo(collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission]))->all());
    }

    public function test_authorized_user_can_view_subject_level_budget_analysis(): void
    {
        $subject = $this->temporarySubject('Sales', SubjectType::CREDITOR);
        $this->budget($subject, 'income', 1000, 5);
        $this->grant('budgets.index');

        $response = $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]));

        $response->assertOk();
        $response->assertViewIs('monthly-budgets.index');
        $response->assertViewHas('selectedMonth', 5);
        $response->assertViewHas('subjects', fn ($subjects) => collect($subjects)->contains('id', $subject->id));
        $response->assertViewHas('budgetLines', fn ($lines) => $lines->count() === 1);
        $response->assertSee($subject->name);
        $response->assertSee('monthlyBudgetVarianceChart', false);
    }

    public function test_user_without_budget_permission_is_forbidden(): void
    {
        $this->actingAs($this->user)->get(route('budgets.index'))->assertForbidden();
    }

    public function test_budget_subject_search_only_returns_temporary_subjects_from_active_company(): void
    {
        $temporary = $this->temporarySubject('Budget utilities', SubjectType::DEBTOR);
        Subject::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Budget permanent asset',
            'is_permanent' => true,
        ]);
        $otherCompany = Company::factory()->create(['fiscal_year' => 1405]);
        Subject::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'parent_id' => null,
            'code' => '999',
            'name' => 'Budget other company',
            'type' => SubjectType::DEBTOR,
            'is_permanent' => false,
        ]);
        $this->grant('budgets.search-subjects');

        $response = $this->actingAs($this->user)->getJson(route('budgets.search-subjects', ['q' => 'Budget']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $temporary->id);
    }

    public function test_manual_forecast_is_created_and_updated_for_a_subject(): void
    {
        $subject = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $this->grant('budgets.store');

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'month' => 4,
            'subject_id' => $subject->id,
            'budget_type' => 'expense',
            'forecast_amount' => 830000.25,
        ])->assertRedirect(route('budgets.index', ['month' => 4]));

        $this->assertDatabaseHas('monthly_budgets', [
            'company_id' => $this->company->id,
            'subject_id' => $subject->id,
            'month' => 4,
            'budget_type' => 'expense',
            'forecast_amount' => 830000.25,
        ]);

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'month' => 4,
            'subject_id' => $subject->id,
            'budget_type' => 'expense',
            'forecast_amount' => 900000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('monthly_budgets', 1);
        $this->assertDatabaseHas('monthly_budgets', [
            'subject_id' => $subject->id,
            'month' => 4,
            'forecast_amount' => 900000,
        ]);
    }

    public function test_forecast_rejects_permanent_and_other_company_subjects(): void
    {
        $permanent = Subject::factory()->create([
            'company_id' => $this->company->id,
            'is_permanent' => true,
        ]);
        $otherCompany = Company::factory()->create(['fiscal_year' => 1405]);
        $otherSubject = Subject::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'parent_id' => null,
            'code' => '999',
            'name' => 'Other company expense',
            'type' => SubjectType::DEBTOR,
            'is_permanent' => false,
        ]);
        $this->grant('budgets.store');

        foreach ([$permanent->id, $otherSubject->id] as $subjectId) {
            $this->actingAs($this->user)->put(route('budgets.store'), [
                'month' => 4,
                'subject_id' => $subjectId,
                'budget_type' => 'expense',
                'forecast_amount' => 100,
            ])->assertSessionHasErrors('subject_id');
        }

        $this->assertDatabaseCount('monthly_budgets', 0);
    }

    public function test_rollover_replaces_current_lines_with_exact_previous_subject_forecasts(): void
    {
        $income = $this->temporarySubject('Sales', SubjectType::CREDITOR);
        $expense = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $obsolete = $this->temporarySubject('Old plan', SubjectType::DEBTOR);
        $this->budget($income, 'income', '123456.78', 6);
        $this->budget($expense, 'expense', '87654.32', 6);
        $this->budget($obsolete, 'expense', 1, 7);
        $this->grant('budgets.rollover');

        $this->actingAs($this->user)->post(route('budgets.rollover'), ['month' => 7])->assertRedirect(route('budgets.index', ['month' => 7]))->assertSessionHas('success');

        $current = MonthlyBudget::where('month', 7)->get();

        $this->assertCount(2, $current);
        $this->assertFalse($current->contains('subject_id', $obsolete->id));
        $this->assertSame('123456.78', $current->firstWhere('subject_id', $income->id)->forecast_amount);
        $this->assertSame('87654.32', $current->firstWhere('subject_id', $expense->id)->forecast_amount);
    }

    public function test_expense_calculation_sums_matching_subject_transactions_from_selected_month_documents(): void
    {
        $income = $this->temporarySubject('Revenue', SubjectType::CREDITOR);
        $expense = $this->temporarySubject('Utilities', SubjectType::DEBTOR);
        $unbudgetedExpense = $this->temporarySubject('Unbudgeted', SubjectType::DEBTOR);
        $this->budget($income, 'income', 1000, 5);
        $this->budget($expense, 'expense', 800, 5);

        $this->transaction($income, 1200, 5);
        $this->transaction($expense, -700, 5);
        $this->transaction($expense, 100, 5);
        $this->transaction($expense, -500, 6);
        $this->transaction($unbudgetedExpense, -900, 5);

        $analysis = $this->service->analysis(5, true);
        $expenseLine = $analysis['budgetLines']->firstWhere('type', 'expense');

        $this->assertSame(1200.0, $analysis['actualIncome']);
        $this->assertSame(600.0, $analysis['actualExpense']);
        $this->assertSame(200.0, $analysis['incomeVariance']);
        $this->assertSame(200.0, $analysis['expenseVariance']);
        $this->assertSame(600.0, $expenseLine['actual']);
        $this->assertSame(25.0, $expenseLine['variancePercent']);
    }

    public function test_expenses_are_only_calculated_by_the_explicit_calculation_action(): void
    {
        $expense = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $this->budget($expense, 'expense', 800, 5);
        $this->transaction($expense, -700, 5);

        $withoutCalculation = $this->service->analysis(5);

        $this->assertNull($withoutCalculation['actualExpense']);
        $this->assertNull($withoutCalculation['expenseVariance']);

        $this->grant('budgets.calculate-expenses');
        $response = $this->actingAs($this->user)->get(route('budgets.calculate-expenses', ['month' => 5]));

        $response->assertOk();
        $response->assertViewHas('expensesCalculated', true);
        $response->assertViewHas('actualExpense', 700.0);
        $response->assertViewHas('expenseVariance', 100.0);
    }

    public function test_budget_line_can_be_deleted(): void
    {
        $subject = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $budget = $this->budget($subject, 'expense', 800, 5);
        $this->grant('budgets.destroy');

        $this->actingAs($this->user)->delete(route('budgets.destroy', $budget))->assertRedirect(route('budgets.index', ['month' => 5]));

        $this->assertDatabaseMissing('monthly_budgets', ['id' => $budget->id]);
    }

    public function test_subject_with_monthly_budget_cannot_be_deleted(): void
    {
        $subject = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $this->budget($subject, 'expense', 800, 5);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(__('Cannot delete subject with monthly budgets'));

        $subject->delete();
    }

    public function test_subjects_export_includes_monthly_budgets(): void
    {
        $subject = Subject::factory()->create(['is_permanent' => false]);
        $budget = MonthlyBudget::create([
            'company_id' => $this->company->id,
            'subject_id' => $subject->id,
            'month' => 4,
            'budget_type' => 'income',
            'forecast_amount' => 125000,
        ]);

        $exportData = FiscalYearService::exportData($this->company->id, [FiscalYearSection::SUBJECTS->value]);

        $this->assertArrayHasKey('monthly_budgets', $exportData);
        $this->assertCount(1, $exportData['monthly_budgets']);
        $this->assertSame($budget->id, $exportData['monthly_budgets'][0]['id']);
    }

    public function test_monthly_budgets_are_imported_with_remapped_subjects(): void
    {
        $subject = Subject::factory()->create(['is_permanent' => false]);
        MonthlyBudget::create([
            'company_id' => $this->company->id,
            'subject_id' => $subject->id,
            'month' => 7,
            'budget_type' => 'expense',
            'forecast_amount' => 98765.43,
        ]);

        $exportData = FiscalYearService::exportData($this->company->id, [FiscalYearSection::SUBJECTS->value]);
        $target = FiscalYearService::importData($exportData, ['name' => 'Next Year', 'fiscal_year' => 1404]);

        $importedSubject = Subject::withoutGlobalScopes()->where('company_id', $target->id)->sole();
        $importedBudget = MonthlyBudget::withoutGlobalScopes()->where('company_id', $target->id)->sole();

        $this->assertSame($importedSubject->id, $importedBudget->subject_id);
        $this->assertSame(7, $importedBudget->month);
        $this->assertSame('expense', $importedBudget->budget_type);
        $this->assertSame('98765.43', $importedBudget->forecast_amount);
    }

    public function test_monthly_budgets_without_subjects_are_logged_and_skipped(): void
    {
        Log::spy();

        $target = FiscalYearService::importData([
            'monthly_budgets' => [[
                'id' => 10,
                'company_id' => $this->company->id,
                'subject_id' => 20,
                'month' => 2,
                'budget_type' => 'income',
                'forecast_amount' => '1000.00',
            ]],
        ], ['name' => 'No Subjects', 'fiscal_year' => 1404]);

        $this->assertSame(0, MonthlyBudget::withoutGlobalScopes()->where('company_id', $target->id)->count());
        Log::shouldHaveReceived('warning')->once()->with('Skipping monthly budgets import due to missing subject mapping.', ['target_year_id' => $target->id]);
    }
}
