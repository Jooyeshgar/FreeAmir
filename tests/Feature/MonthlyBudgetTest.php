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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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

        config(['cache.default' => 'array']);
        Cache::flush();

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

    private function childSubject(Subject $parent, string $name, SubjectType $type): Subject
    {
        return Subject::factory()->create([
            'company_id' => $this->company->id,
            'name' => $name,
            'type' => $type,
            'is_permanent' => false,
            'parent_id' => $parent->id,
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

    private function transaction(Subject $subject, int $value, int $month, bool $approved = true): void
    {
        $document = Document::create([
            'number' => Document::withoutGlobalScopes()->max('number') + 1,
            'date' => jalali_to_gregorian((int) $this->company->fiscal_year, $month, 10, '-'),
            'creator_id' => $this->user->id,
            'approved_at' => $approved ? now() : null,
            'approver_id' => $approved ? $this->user->id : null,
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

    private function setFiscalYear(int $fiscalYear): void
    {
        $this->company->update(['fiscal_year' => $fiscalYear]);
        config(['active-company-fiscal-year' => $fiscalYear]);
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
        $response->assertViewHas('subjects', fn ($subjects) => ! collect($subjects)->contains('id', $subject->id));
        $response->assertViewHas('budgetLines', fn ($lines) => $lines->count() === 1);
        $response->assertViewHas('comparisonDatasets', fn (array $datasets) => $datasets[0]['data'][__('Expense')] === 0.0
            && ! str_contains(json_encode($datasets, JSON_THROW_ON_ERROR), '-0'));
        $response->assertSee($subject->name);
        $response->assertSee(__('Monthly Income and Expense Workbench'));
        $response->assertDontSee(__('Forecast Type'));
        $response->assertDontSee('annualBudgetForecastChart', false);
        $response->assertSee('monthlyBudgetVarianceChart', false);
    }

    public function test_forecast_table_shows_first_five_hierarchical_lines_and_all_lines_in_a_modal(): void
    {
        foreach (range(1, 6) as $index) {
            $subject = $this->temporarySubject('Top forecast '.$index, SubjectType::CREDITOR);
            $this->budget($subject, 'income', $index * 100, 5);
        }
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))->assertOk()
            ->assertViewHas('displayBudgetLines', fn ($lines) => $lines->count() === 5)
            ->assertViewHas('allBudgetLinesByForecast', fn ($lines) => $lines->count() === 6)->assertViewHas('hasMoreBudgetLines', true)
            ->assertSee(__('Show all'))->assertSee('all-monthly-forecasts-modal', false);
    }

    public function test_manual_forecast_groups_come_first_while_parent_and_child_stay_together(): void
    {
        $root = $this->temporarySubject('System parent', SubjectType::DEBTOR);
        $child = $this->childSubject($root, 'Manual child', SubjectType::DEBTOR);
        $otherRoot = $this->temporarySubject('Other manual root', SubjectType::CREDITOR);
        $pureSystemRoot = $this->temporarySubject('Pure system root', SubjectType::CREDITOR);
        $root->update(['code' => '100']);
        $child->update(['code' => '100001']);
        $otherRoot->update(['code' => '200']);
        $pureSystemRoot->update(['code' => '050']);
        $this->budget($child, 'expense', 800, 5);
        $this->budget($otherRoot, 'income', 1000, 5);

        $lines = $this->service->analysis(5)['budgetLines'];

        $this->assertSame([$root->id, $child->id, $otherRoot->id, $pureSystemRoot->id], $lines->pluck('subject.id')->all());
        $this->assertSame(['system', 'manual', 'manual', 'system'], $lines->pluck('source')->all());
        $this->assertFalse($lines[0]['isChild']);
        $this->assertTrue($lines[1]['isChild']);
    }

    public function test_income_and_expense_items_compare_forecast_with_actual_values(): void
    {
        $income = $this->temporarySubject('Itemized income', SubjectType::CREDITOR);
        $expense = $this->temporarySubject('Itemized expense', SubjectType::DEBTOR);
        $this->budget($income, 'income', 1000, 5);
        $this->budget($expense, 'expense', 800, 5);
        $this->transaction($income, 1200, 5);
        $this->transaction($expense, -700, 5);
        $this->grant('budgets.index');

        $response = $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]));

        $response->assertOk()->assertViewHas('incomeItemDatasets', function (array $datasets) {
            return count($datasets) === 2
                && array_values($datasets[0]['data']) === [1000.0]
                && array_values($datasets[1]['data']) === [1200.0];
        })->assertViewHas('expenseItemDatasets', function (array $datasets) {
            return count($datasets) === 2
                && array_values($datasets[0]['data']) === [800.0]
                && array_values($datasets[1]['data']) === [700.0];
        })->assertViewHas('comparisonDatasets', function (array $datasets) {
            return $datasets[0]['data'][__('Expense')] === 800.0
                && $datasets[1]['data'][__('Expense')] === 700.0;
        })->assertSee('monthlyIncomeForecastItemsChart', false)->assertSee('monthlyIncomeActualItemsChart', false)
            ->assertSee('monthlyExpenseForecastItemsChart', false)->assertSee('monthlyExpenseActualItemsChart', false)
            ->assertSee('emptySelectionBorderPlugin', false)->assertSee("type: 'pie'", false)
            ->assertSee(__('Manual Forecast'))->assertSee(__('Entered by the user.'))
            ->assertSee(__("Calculated automatically from the actual value or the previous month's applied forecast."))
            ->assertDontSee('data-tip="'.e(__('Entered by the user.')).'"', false)
            ->assertSee('(۸۰۰)', false)->assertSee('(۷۰۰)', false)
            ->assertDontSee('-۸۰۰', false)->assertDontSee('-۷۰۰', false);

        $this->assertSame(4, substr_count($response->getContent(), "type: 'pie'"));
    }

    /**
     * Cache invalidation belongs to the application boundary, not Eloquent.
     */
    public function test_financial_models_do_not_depend_on_the_income_expense_cache(): void
    {
        $models = [
            'Company.php',
            'Document.php',
            'Invoice.php',
            'MonthlyBudget.php',
            'Subject.php',
            'Transaction.php',
        ];

        foreach ($models as $model) {
            $source = file_get_contents(app_path("Models/{$model}"));

            $this->assertStringNotContainsString('IncomeExpenseCache', $source, "{$model} must not depend on the income/expense cache.");
        }
    }

    public function test_user_without_budget_permission_is_forbidden(): void
    {
        $this->actingAs($this->user)->get(route('budgets.index'))->assertForbidden();
    }

    public function test_budget_editor_is_hidden_when_subject_search_permission_is_missing(): void
    {
        $this->grant('budgets.index', 'budgets.store');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))
            ->assertOk()
            ->assertDontSee('forecast_amount_display', false);
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

        $response = $this->actingAs($this->user)->getJson(route('budgets.search-subjects', ['q' => 'Budget', 'month' => 5]));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $temporary->id);
    }

    public function test_budget_subject_search_validates_the_route_month(): void
    {
        $this->grant('budgets.search-subjects');

        $this->actingAs($this->user)->getJson(route('budgets.search-subjects', ['month' => 'invalid', 'q' => 'Budget']))
            ->assertUnprocessable()->assertJsonValidationErrors('month');

        $this->actingAs($this->user)->getJson(route('budgets.search-subjects', ['month' => 13, 'q' => 'Budget']))
            ->assertUnprocessable()->assertJsonValidationErrors('month');
    }

    public function test_bulk_budget_subject_search_includes_the_same_subject_with_an_existing_forecast(): void
    {
        $subject = $this->temporarySubject('Existing bulk forecast', SubjectType::CREDITOR);
        $this->budget($subject, 'income', 500, 5);
        $this->grant('budgets.search-subjects');

        $this->actingAs($this->user)->getJson(route('budgets.search-subjects', [
            'month' => 5,
            'scope' => 'all',
            'q' => 'Existing bulk',
        ]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $subject->id);
    }

    public function test_manual_forecast_is_created_and_cannot_be_selected_again_for_the_same_month(): void
    {
        $subject = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $this->grant('budgets.store');

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'month' => 4,
            'subject_id' => $subject->id,
            'forecast_amount' => -830000.25,
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
            'forecast_amount' => -900000,
        ])->assertSessionHasErrors('subject_id');

        $this->assertDatabaseCount('monthly_budgets', 1);
        $this->assertDatabaseHas('monthly_budgets', [
            'subject_id' => $subject->id,
            'month' => 4,
            'forecast_amount' => 830000.25,
        ]);

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'month' => 5,
            'subject_id' => $subject->id,
            'forecast_amount' => -900000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('monthly_budgets', [
            'subject_id' => $subject->id,
            'month' => 5,
            'forecast_amount' => 900000,
        ]);
    }

    public function test_bulk_forecast_is_created_for_every_selected_month(): void
    {
        $subject = $this->temporarySubject('Bulk sales', SubjectType::CREDITOR);
        $this->grant('budgets.store');

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'source' => 'cost-income',
            'months' => [5, 6, 12],
            'subject_id' => $subject->id,
            'forecast_amount' => 125000.25,
        ])->assertRedirect(route('reports.cost-income'))->assertSessionHasNoErrors();

        foreach ([5, 6, 12] as $month) {
            $this->assertDatabaseHas('monthly_budgets', [
                'company_id' => $this->company->id,
                'subject_id' => $subject->id,
                'month' => $month,
                'budget_type' => 'income',
                'forecast_amount' => 125000.25,
            ]);
        }
    }

    public function test_bulk_forecast_updates_the_same_subject_in_an_existing_month(): void
    {
        $subject = $this->temporarySubject('Updated bulk sales', SubjectType::CREDITOR);
        $existingBudget = $this->budget($subject, 'income', 500, 6);
        $this->grant('budgets.store');

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'source' => 'cost-income',
            'months' => [5, 6],
            'subject_id' => $subject->id,
            'forecast_amount' => 900,
        ])->assertRedirect(route('reports.cost-income'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('monthly_budgets', [
            'subject_id' => $subject->id,
            'month' => 5,
            'forecast_amount' => 900,
        ]);
        $this->assertDatabaseHas('monthly_budgets', [
            'id' => $existingBudget->id,
            'subject_id' => $subject->id,
            'month' => 6,
            'forecast_amount' => 900,
        ]);
        $this->assertDatabaseCount('monthly_budgets', 2);
    }

    public function test_bulk_forecast_requires_a_month_and_can_create_a_root_alongside_its_child(): void
    {
        $root = $this->temporarySubject('Bulk expenses', SubjectType::DEBTOR);
        $child = $this->childSubject($root, 'Existing bulk expense', SubjectType::DEBTOR);
        $this->budget($child, 'expense', 500, 6);
        $this->grant('budgets.store');

        $this->actingAs($this->user)->from(route('reports.cost-income'))->put(route('budgets.store'), [
            'source' => 'cost-income',
            'subject_id' => $root->id,
            'forecast_amount' => -900,
        ])->assertRedirect(route('reports.cost-income'))->assertSessionHasErrors('months');

        $this->actingAs($this->user)->from(route('reports.cost-income'))->put(route('budgets.store'), [
            'source' => 'cost-income',
            'months' => [5, 6],
            'subject_id' => $root->id,
            'forecast_amount' => -900,
        ])->assertRedirect(route('reports.cost-income'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('monthly_budgets', [
            'subject_id' => $root->id,
            'month' => 5,
            'forecast_amount' => 900,
        ]);
        $this->assertDatabaseHas('monthly_budgets', [
            'subject_id' => $root->id,
            'month' => 6,
            'forecast_amount' => 900,
        ]);
        $this->assertDatabaseCount('monthly_budgets', 3);
    }

    public function test_signed_forecast_amount_is_validated_against_subject_type(): void
    {
        $income = $this->temporarySubject('Signed income', SubjectType::CREDITOR);
        $expense = $this->temporarySubject('Signed expense', SubjectType::DEBTOR);
        $both = $this->temporarySubject('Signed both', SubjectType::BOTH);
        $this->grant('budgets.store');

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'month' => 4,
            'subject_id' => $income->id,
            'forecast_amount' => -100,
        ])->assertSessionHasErrors('forecast_amount');

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'month' => 4,
            'subject_id' => $expense->id,
            'forecast_amount' => 100,
        ])->assertSessionHasErrors('forecast_amount');

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'month' => 4,
            'subject_id' => $both->id,
            'forecast_amount' => -250,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('monthly_budgets', [
            'subject_id' => $both->id,
            'budget_type' => 'expense',
            'forecast_amount' => 250,
        ]);
    }

    public function test_manual_forecast_wins_and_is_inherited_by_the_next_open_month(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 3, 15, '-').' 12:00:00'));
        $expense = $this->temporarySubject('Recurring expense', SubjectType::DEBTOR);
        $this->transaction($expense, -100, 1);
        $this->transaction($expense, -300, 2);
        $this->budget($expense, 'expense', 500, 3);

        $line = $this->service->analysis(3)['budgetLines']->first(fn (array $item) => $item['subject']->id === $expense->id);

        $this->assertSame('manual', $line['source']);
        $this->assertSame(-500.0, $line['forecast']);
        $this->assertSame(-300.0, $line['systemForecast']);

        $systemLine = $this->service->analysis(4)['budgetLines']->first(fn (array $item) => $item['subject']->id === $expense->id);

        $this->assertSame('system', $systemLine['source']);
        $this->assertSame(-500.0, $systemLine['forecast']);
    }

    public function test_manual_forecast_overrides_current_actual_while_unforecasted_subject_inherits_previous_applied_forecast(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 5, 15, '-').' 12:00:00'));
        $manualIncome = $this->temporarySubject('Manual current income', SubjectType::CREDITOR);
        $actualExpense = $this->temporarySubject('Actual current expense', SubjectType::DEBTOR);
        $this->budget($manualIncome, 'income', 1000, 5);
        $this->transaction($actualExpense, -250, 4);
        $this->transaction($manualIncome, 1200, 5);
        $this->transaction($actualExpense, -300, 5);

        $analysis = $this->service->analysis(5);
        $manualLine = $analysis['budgetLines']->first(fn (array $line) => $line['subject']->id === $manualIncome->id);
        $systemLine = $analysis['budgetLines']->first(fn (array $line) => $line['subject']->id === $actualExpense->id);

        $this->assertSame('manual', $manualLine['source']);
        $this->assertSame(1000.0, $manualLine['forecast']);
        $this->assertSame('system', $systemLine['source']);
        $this->assertSame(-250.0, $systemLine['forecast']);
        $this->assertSame(1000.0, $analysis['forecastIncome']);
        $this->assertSame(-250.0, $analysis['forecastExpense']);
        $this->assertSame(1200.0, $analysis['actualIncome']);
        $this->assertSame(-300.0, $analysis['actualExpense']);
    }

    public function test_first_month_uses_its_actual_value_when_documents_exist_and_zero_otherwise(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 1, 15, '-').' 12:00:00'));
        $incomeWithActual = $this->temporarySubject('First month income', SubjectType::CREDITOR);
        $incomeWithoutActual = $this->temporarySubject('First month empty income', SubjectType::CREDITOR);
        $this->transaction($incomeWithActual, 400, 1);

        $lines = $this->service->analysis(1)['budgetLines']->keyBy('subject.id');

        $this->assertSame(400.0, $lines[$incomeWithActual->id]['forecast']);
        $this->assertSame(0.0, $lines[$incomeWithoutActual->id]['forecast']);
    }

    public function test_subject_direction_uses_the_approved_balance_of_itself_and_every_descendant(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 3, 15, '-').' 12:00:00'));
        $root = $this->temporarySubject('Direction from complete hierarchy', SubjectType::BOTH);
        $child = $this->childSubject($root, 'Direction child', SubjectType::BOTH);
        $this->transaction($root, 100, 1);
        $this->transaction($child, -300, 2);

        $analysis = $this->service->analysis(1);
        $line = $analysis['budgetLines']->first(fn (array $item) => $item['subject']->id === $root->id);

        $this->assertSame('expense', $line['type']);
        $this->assertSame(100.0, $line['actual']);
        $this->assertSame(100.0, $line['forecast']);
        $this->assertSame(0.0, $analysis['actualIncome']);
        $this->assertSame(100.0, $analysis['actualExpense']);
        $this->assertSame(0.0, $analysis['forecastIncome']);
        $this->assertSame(100.0, $analysis['forecastExpense']);

        $this->grant('budgets.index');
        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 1]))->assertOk()
            ->assertViewHas('comparisonDatasets', fn (array $datasets) => $datasets[0]['data'][__('Expense')] === 100.0
                && $datasets[1]['data'][__('Expense')] === 100.0);
    }

    public function test_manual_forecast_direction_overrides_history_for_a_both_type_subject(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 5, 15, '-').' 12:00:00'));
        $subject = $this->temporarySubject('Either direction', SubjectType::BOTH);
        $this->transaction($subject, 1000, 1);
        $this->budget($subject, 'expense', 400, 5);

        $analysis = $this->service->analysis(5);
        $line = $analysis['budgetLines']->sole();

        $this->assertSame('expense', $line['type']);
        $this->assertSame(-400.0, $line['forecast']);
        $this->assertSame(0.0, $analysis['forecastIncome']);
        $this->assertSame(-400.0, $analysis['forecastExpense']);
    }

    public function test_zero_applied_forecast_and_actual_use_the_subject_type_for_direction(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 3, 15, '-').' 12:00:00'));
        $cost = $this->temporarySubject('Cost with prior opposite-signed activity', SubjectType::DEBTOR);
        $income = $this->temporarySubject('Income with prior opposite-signed activity', SubjectType::CREDITOR);
        $other = $this->temporarySubject('Other activity', SubjectType::CREDITOR);

        // These prior balances would normally classify both rows opposite to their configured types.
        $this->transaction($cost, 500, 1);
        $this->transaction($income, -300, 1);
        // Make actual values available in month 2 without moving either subject.
        $this->transaction($other, 100, 2);

        $lines = $this->service->analysis(2)['budgetLines'];
        $costLine = $lines->first(fn (array $item) => $item['subject']->id === $cost->id);
        $incomeLine = $lines->first(fn (array $item) => $item['subject']->id === $income->id);

        $this->assertSame(0.0, $costLine['forecast']);
        $this->assertSame(0.0, $costLine['actual']);
        $this->assertSame('expense', $costLine['type']);
        $this->assertSame(0.0, $incomeLine['forecast']);
        $this->assertSame(0.0, $incomeLine['actual']);
        $this->assertSame('income', $incomeLine['type']);
    }

    public function test_system_forecast_rolls_leaf_invoice_and_payroll_postings_up_to_their_roots(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 3, 15, '-').' 12:00:00'));

        // Invoice builders post revenue and COGS to the product/service subject, below the configured root and group subjects.
        // Payroll accounting may likewise be posted to a personnel/department leaf below wages.
        $incomeRoot = $this->temporarySubject('Sales revenue', SubjectType::CREDITOR);
        $productIncomeGroup = $this->childSubject($incomeRoot, 'Product income group', SubjectType::CREDITOR);
        $productIncomeLeaf = $this->childSubject($productIncomeGroup, 'Product income', SubjectType::CREDITOR);

        $cogsRoot = $this->temporarySubject('Cost of goods sold', SubjectType::DEBTOR);
        $productCogsGroup = $this->childSubject($cogsRoot, 'Product COGS group', SubjectType::DEBTOR);
        $productCogsLeaf = $this->childSubject($productCogsGroup, 'Product COGS', SubjectType::DEBTOR);

        $wageRoot = $this->temporarySubject('Personnel costs', SubjectType::DEBTOR);
        $wageGroup = $this->childSubject($wageRoot, 'Salary costs', SubjectType::DEBTOR);
        $employeeWageLeaf = $this->childSubject($wageGroup, 'Employee salary', SubjectType::DEBTOR);

        $this->transaction($productIncomeLeaf, 300, 1);
        $this->transaction($productCogsLeaf, -90, 1);
        $this->transaction($employeeWageLeaf, -60, 1);
        $this->transaction($productIncomeLeaf, 600, 2);
        $this->transaction($productCogsLeaf, -180, 2);
        $this->transaction($employeeWageLeaf, -120, 2);

        $firstMonth = $this->service->analysis(1);
        $currentMonth = $this->service->analysis(3);

        $this->assertEqualsCanonicalizing(
            [$incomeRoot->id, $cogsRoot->id, $wageRoot->id],
            $currentMonth['budgetLines']->pluck('subject.id')->all()
        );
        $this->assertSame(300.0, $firstMonth['forecastIncome']);
        $this->assertSame(-150.0, $firstMonth['forecastExpense']);
        $this->assertSame(600.0, $currentMonth['forecastIncome']);
        $this->assertSame(-300.0, $currentMonth['forecastExpense']);
    }

    public function test_past_months_use_real_totals_and_mordad_inherits_tirs_applied_forecast(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 5, 15, '-').' 12:00:00'));

        // Types deliberately oppose the posting direction. The monthly result
        // must follow transaction signs, not the subject's normal balance.
        $income = $this->temporarySubject('Signed income', SubjectType::DEBTOR);
        $expense = $this->temporarySubject('Signed expense', SubjectType::CREDITOR);

        $this->transaction($income, 100, 1);
        $this->transaction($expense, -20, 1);
        $this->transaction($income, 200, 2);
        $this->transaction($expense, -40, 2);
        $this->transaction($expense, -60, 3);
        $this->transaction($income, 300, 4);
        $this->transaction($expense, -80, 4);

        $tir = $this->service->analysis(4);
        $mordad = $this->service->analysis(5);
        $tirIncomeLine = $tir['budgetLines']->first(fn (array $line) => $line['subject']->id === $income->id);
        $tirExpenseLine = $tir['budgetLines']->first(fn (array $line) => $line['subject']->id === $expense->id);

        $this->assertSame('income', $tirIncomeLine['type']);
        $this->assertSame('expense', $tirExpenseLine['type']);
        $this->assertSame(300.0, $tir['actualIncome']);
        $this->assertSame(-80.0, $tir['actualExpense']);
        $this->assertSame(300.0, $tir['forecastIncome']);
        $this->assertSame(-80.0, $tir['forecastExpense']);
        $this->assertSame(300.0, $mordad['forecastIncome']);
        $this->assertSame(-80.0, $mordad['forecastExpense']);
        $this->assertNull($mordad['actualIncome']);
        $this->assertNull($mordad['actualExpense']);
    }

    public function test_forecast_selector_is_limited_to_roots_and_direct_children(): void
    {
        $root = $this->temporarySubject('Two-level root', SubjectType::DEBTOR);
        $child = $this->childSubject($root, 'Two-level child', SubjectType::DEBTOR);
        $grandchild = $this->childSubject($child, 'Hidden grandchild', SubjectType::DEBTOR);
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))->assertOk()
            ->assertViewHas('subjects', function (array $subjects) use ($root, $child, $grandchild) {
                $ids = collect($subjects)->flatMap(fn (array $subject) => [
                    $subject['id'],
                    ...collect($subject['children'])->pluck('id')->all(),
                ]);

                return $ids->contains($root->id) && $ids->contains($child->id) && ! $ids->contains($grandchild->id);
            });
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
                'forecast_amount' => -100,
            ])->assertSessionHasErrors('subject_id');
        }

        $this->assertDatabaseCount('monthly_budgets', 0);
    }

    public function test_forecast_selector_only_hides_subjects_already_forecast_for_the_month(): void
    {
        $root = $this->temporarySubject('Root', SubjectType::DEBTOR);
        $forecastedChild = $this->childSubject($root, 'Forecasted child', SubjectType::DEBTOR);
        $this->childSubject($forecastedChild, 'Covered grandchild', SubjectType::DEBTOR);
        $availableSibling = $this->childSubject($root, 'Available sibling', SubjectType::DEBTOR);
        $forecastedRoot = $this->temporarySubject('Forecasted root', SubjectType::CREDITOR);
        $this->childSubject($forecastedRoot, 'Covered root child', SubjectType::CREDITOR);
        $availableRoot = $this->temporarySubject('Available root', SubjectType::BOTH);

        $this->budget($forecastedChild, 'expense', 800, 5);
        $this->budget($forecastedRoot, 'income', 1000, 5);
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))->assertOk()
            ->assertViewHas('subjects', function (array $subjects) use ($availableRoot, $availableSibling, $forecastedChild, $forecastedRoot, $root) {
                $rootIds = collect($subjects)->pluck('id');
                $rootOption = collect($subjects)->firstWhere('id', $root->id);

                return $rootIds->contains($root->id) && $rootIds->contains($availableRoot->id) && ! $rootIds->contains($forecastedRoot->id)
                    && collect($rootOption['children'])->contains('id', $availableSibling->id)
                    && ! collect($rootOption['children'])->contains('id', $forecastedChild->id);
            });
    }

    public function test_search_and_store_allow_root_and_direct_child_forecasts_but_reject_duplicates_and_deeper_levels(): void
    {
        $root = $this->temporarySubject('Budget root', SubjectType::DEBTOR);
        $forecastedChild = $this->childSubject($root, 'Budget forecasted child', SubjectType::DEBTOR);
        $grandchild = $this->childSubject($forecastedChild, 'Budget grandchild', SubjectType::DEBTOR);
        $sibling = $this->childSubject($root, 'Budget sibling', SubjectType::DEBTOR);
        $this->budget($forecastedChild, 'expense', 800, 5);
        $this->grant('budgets.search-subjects', 'budgets.store');

        $search = $this->actingAs($this->user)->getJson(route('budgets.search-subjects', ['q' => 'Budget', 'month' => 5]))->assertOk()->assertJsonCount(2);
        $this->assertEqualsCanonicalizing([$root->id, $sibling->id], collect($search->json())->pluck('id')->all());

        foreach ([$forecastedChild, $grandchild] as $unavailableSubject) {
            $this->actingAs($this->user)->put(route('budgets.store'), [
                'month' => 5,
                'subject_id' => $unavailableSubject->id,
                'forecast_amount' => -100,
            ])->assertSessionHasErrors('subject_id');
        }

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'month' => 5,
            'subject_id' => $root->id,
            'forecast_amount' => -900,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->put(route('budgets.store'), [
            'month' => 5,
            'subject_id' => $sibling->id,
            'forecast_amount' => -100,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('monthly_budgets', [
            'month' => 5,
            'subject_id' => $sibling->id,
        ]);
        $this->assertDatabaseCount('monthly_budgets', 3);
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
        $this->setFiscalYear((int) toEnglish(jdate('Y')) - 1);
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

        $analysis = $this->service->analysis(5);
        $expenseLine = $analysis['budgetLines']->first(fn (array $line) => $line['subject']->id === $expense->id);
        $unbudgetedLine = $analysis['budgetLines']->first(fn (array $line) => $line['subject']->id === $unbudgetedExpense->id);

        $this->assertTrue($analysis['actualsCalculated']);
        $this->assertSame(1200.0, $analysis['actualIncome']);
        $this->assertSame(-1500.0, $analysis['actualExpense']);
        $this->assertSame(1000.0, $analysis['forecastIncome']);
        $this->assertSame(-1700.0, $analysis['forecastExpense']);
        $this->assertSame(200.0, $analysis['incomeVariance']);
        $this->assertSame(200.0, $analysis['expenseVariance']);
        $this->assertSame(-600.0, $expenseLine['actual']);
        $this->assertSame(25.0, $expenseLine['variancePercent']);
        $this->assertSame('system', $unbudgetedLine['source']);
        $this->assertSame(-900.0, $unbudgetedLine['forecast']);
        $this->assertSame(-900.0, $unbudgetedLine['actual']);
    }

    public function test_actuals_include_every_descendant_level_for_income_and_expense_forecasts(): void
    {
        $this->setFiscalYear((int) toEnglish(jdate('Y')) - 1);
        $incomeRoot = $this->temporarySubject('Income root', SubjectType::CREDITOR);
        $incomeChild = $this->childSubject($incomeRoot, 'Income child', SubjectType::CREDITOR);
        $incomeGrandchild = Subject::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Income permanent grandchild',
            'type' => SubjectType::CREDITOR,
            'is_permanent' => true,
            'parent_id' => $incomeChild->id,
        ]);
        $expenseRoot = $this->temporarySubject('Expense root', SubjectType::DEBTOR);
        $expenseChild = $this->childSubject($expenseRoot, 'Expense child', SubjectType::DEBTOR);
        $expenseGrandchild = Subject::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Expense permanent grandchild',
            'type' => SubjectType::DEBTOR,
            'is_permanent' => true,
            'parent_id' => $expenseChild->id,
        ]);

        $this->budget($incomeRoot, 'income', 1000, 5);
        $this->budget($expenseChild, 'expense', 800, 5);

        $this->transaction($incomeRoot, 100, 5);
        $this->transaction($incomeChild, 200, 5);
        $this->transaction($incomeGrandchild, 300, 5);
        $this->transaction($expenseRoot, -100, 5);
        $this->transaction($expenseChild, -200, 5);
        $this->transaction($expenseGrandchild, -300, 5);

        $analysis = $this->service->analysis(5);
        $incomeLine = $analysis['budgetLines']->firstWhere('type', 'income');
        $expenseLine = $analysis['budgetLines']->first(fn (array $line) => $line['subject']->id === $expenseChild->id);

        $this->assertSame(600.0, $incomeLine['actual']);
        $this->assertSame(-500.0, $expenseLine['actual']);
        $this->assertSame(600.0, $analysis['actualIncome']);
        $this->assertSame(-600.0, $analysis['actualExpense']);
        $this->assertFalse($analysis['hasOverlappingForecasts']);
    }

    public function test_full_year_chart_contains_forecasts_and_actuals_for_all_completed_months(): void
    {
        $this->setFiscalYear((int) toEnglish(jdate('Y')) - 1);
        $income = $this->temporarySubject('Annual income', SubjectType::CREDITOR);
        $expense = $this->temporarySubject('Annual expense', SubjectType::DEBTOR);
        $this->budget($income, 'income', 1000, 2);
        $this->budget($expense, 'expense', 800, 11);
        $this->transaction($income, 1250, 2);
        $this->transaction($expense, -650, 11);

        $fullYearAnalysis = $this->service->fullYearAnalysis();
        $chart = $fullYearAnalysis['chart'];

        $this->assertCount(12, $chart['labels']);
        $this->assertSame(1000.0, $chart['forecastIncome'][1]);
        $this->assertSame(1250.0, $chart['actualIncome'][1]);
        $this->assertSame(0.0, $chart['forecastIncome'][2]);
        $this->assertSame(-800.0, $chart['forecastExpense'][10]);
        $this->assertSame(-650.0, $chart['actualExpense'][10]);
        $this->assertSame(0.0, $chart['forecastIncome'][11]);
        $this->assertSame(0.0, $chart['forecastExpense'][11]);
        $this->assertNull($chart['actualIncome'][0]);
        $this->assertSame(1, $chart['documentCounts'][1]);
        $this->assertSame([
            'forecastIncome' => 1000.0,
            'forecastExpense' => -800.0,
            'actualIncome' => 1250.0,
            'actualExpense' => -650.0,
        ], $fullYearAnalysis['totals']);
    }

    public function test_full_year_analysis_queries_monthly_balances_once_per_month(): void
    {
        $this->setFiscalYear((int) toEnglish(jdate('Y')) - 1);

        foreach (range(1, 5) as $index) {
            $subject = $this->temporarySubject('Query count '.$index, SubjectType::CREDITOR);
            $this->transaction($subject, $index * 100, 1);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->service->fullYearAnalysis();

        $balanceQueries = collect(DB::getQueryLog())->filter(function (array $query) {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'transactions') && str_contains($sql, 'group by');
        });

        DB::disableQueryLog();

        $this->assertCount(13, $balanceQueries);
    }

    public function test_system_forecasts_show_roots_only_and_split_out_manually_forecast_direct_children(): void
    {
        $this->setFiscalYear((int) toEnglish(jdate('Y')) - 1);
        $root = $this->temporarySubject('Root expense', SubjectType::DEBTOR);
        $manualChild = $this->childSubject($root, 'Manual child expense', SubjectType::DEBTOR);
        $manualGrandchild = $this->childSubject($manualChild, 'Manual child detail', SubjectType::DEBTOR);
        $systemChild = $this->childSubject($root, 'System child expense', SubjectType::DEBTOR);

        $this->transaction($root, -100, 5);
        $this->transaction($manualChild, -200, 5);
        $this->transaction($manualGrandchild, -300, 5);
        $this->transaction($systemChild, -400, 5);

        $rootOnly = $this->service->analysis(5);

        $this->assertCount(1, $rootOnly['budgetLines']);
        $this->assertSame($root->id, $rootOnly['budgetLines']->sole()['subject']->id);
        $this->assertSame(-1000.0, $rootOnly['forecastExpense']);

        $this->budget($manualChild, 'expense', 800, 5);
        $split = $this->service->analysis(5);
        $rootLine = $split['budgetLines']->first(fn (array $line) => $line['subject']->id === $root->id);
        $childLine = $split['budgetLines']->first(fn (array $line) => $line['subject']->id === $manualChild->id);

        $this->assertCount(2, $split['budgetLines']);
        $this->assertTrue($rootLine['isRemainder']);
        $this->assertTrue($rootLine['includedInSummary']);
        $this->assertSame(-500.0, $rootLine['forecast']);
        $this->assertTrue($childLine['isChild']);
        $this->assertTrue($childLine['includedInSummary']);
        $this->assertSame(-800.0, $childLine['forecast']);
        $this->assertSame(-1300.0, $split['forecastExpense']);
        $this->assertSame(-1000.0, $split['actualExpense']);
    }

    public function test_manual_root_total_controls_summary_while_manual_child_is_shown_as_detail(): void
    {
        $this->setFiscalYear((int) toEnglish(jdate('Y')) - 1);
        $root = $this->temporarySubject('Expense root', SubjectType::DEBTOR);
        $child = $this->childSubject($root, 'Expense child', SubjectType::DEBTOR);
        $grandchild = $this->childSubject($child, 'Expense grandchild', SubjectType::DEBTOR);

        $this->budget($root, 'expense', 1000, 5);
        $this->budget($child, 'expense', 500, 5);

        $this->transaction($root, -100, 5);
        $this->transaction($child, -200, 5);
        $this->transaction($grandchild, -300, 5);

        $analysis = $this->service->analysis(5);
        $rootLine = $analysis['budgetLines']->first(fn (array $line) => $line['budget']->subject_id === $root->id);
        $childLine = $analysis['budgetLines']->first(fn (array $line) => $line['budget']->subject_id === $child->id);

        $this->assertSame(-600.0, $rootLine['actual']);
        $this->assertSame(-500.0, $childLine['actual']);
        $this->assertTrue($rootLine['includedInSummary']);
        $this->assertFalse($childLine['includedInSummary']);
        $this->assertSame(-1000.0, $analysis['forecastExpense']);
        $this->assertSame(-600.0, $analysis['actualExpense']);
        $this->assertTrue($analysis['hasOverlappingForecasts']);
        $this->assertSame([$root->id, $child->id], $analysis['budgetLines']->pluck('subject.id')->all());

        $this->grant('budgets.index');
        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))->assertOk()->assertViewHas('actualsCalculated', true)
            ->assertSee(__('Forecasts for lower-level subjects shown below a manually forecast root are details only and are already included in the root total.'))
            ->assertSee(__('Included in parent total'));
    }

    public function test_parent_and_child_manual_forecasts_must_form_a_valid_breakdown(): void
    {
        $root = $this->temporarySubject('Validated expense root', SubjectType::DEBTOR);
        $firstChild = $this->childSubject($root, 'First expense detail', SubjectType::DEBTOR);
        $secondChild = $this->childSubject($root, 'Second expense detail', SubjectType::BOTH);
        $this->budget($root, 'expense', 1000, 5);
        $this->budget($firstChild, 'expense', 600, 5);

        try {
            $this->service->saveForecast(5, [
                'subject_id' => $secondChild->id,
                'budget_type' => 'expense',
                'forecast_amount' => 500,
            ]);
            $this->fail('A child total greater than its parent forecast must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('forecast_amount', $exception->errors());
        }

        try {
            $this->service->saveForecast(5, [
                'subject_id' => $secondChild->id,
                'budget_type' => 'income',
                'forecast_amount' => 100,
            ]);
            $this->fail('A child direction different from its parent forecast must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('forecast_amount', $exception->errors());
        }

        $this->service->saveForecast(5, [
            'subject_id' => $secondChild->id,
            'budget_type' => 'expense',
            'forecast_amount' => 400,
        ]);

        $analysis = $this->service->analysis(5);

        $this->assertSame(-1000.0, $analysis['forecastExpense']);
        $this->assertCount(3, $analysis['budgetLines']);
        $this->assertSame(2, $analysis['budgetLines']->where('includedInSummary', false)->count());
    }

    public function test_parent_and_child_manual_forecasts_carry_forward_without_counting_the_child_twice(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 5, 15, '-').' 12:00:00'));
        $root = $this->temporarySubject('Carried expense root', SubjectType::DEBTOR);
        $child = $this->childSubject($root, 'Carried expense child', SubjectType::DEBTOR);

        $this->budget($root, 'expense', 1000, 5);
        $this->budget($child, 'expense', 300, 5);
        $this->budget($child, 'expense', 400, 6);

        $monthFive = $this->service->analysis(5);
        $monthSix = $this->service->analysis(6);
        $rootRemainder = $monthSix['budgetLines']->first(fn (array $line) => $line['subject']->is($root));
        $childLine = $monthSix['budgetLines']->first(fn (array $line) => $line['subject']->is($child));

        $this->assertSame(-1000.0, $monthFive['forecastExpense']);
        $this->assertSame(-700.0, $rootRemainder['forecast']);
        $this->assertSame(-400.0, $childLine['forecast']);
        $this->assertSame(-1100.0, $monthSix['forecastExpense']);
    }

    public function test_actuals_are_calculated_when_the_month_has_documents_and_missing_documents_are_flagged(): void
    {
        $currentYear = (int) toEnglish(jdate('Y'));
        $currentMonth = (int) toEnglish(jdate('n'));
        $this->setFiscalYear($currentYear);
        $income = $this->temporarySubject('Sales', SubjectType::CREDITOR);
        $expense = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $this->budget($income, 'income', 1000, $currentMonth);
        $this->budget($expense, 'expense', 800, $currentMonth);
        $this->transaction($income, 1200, $currentMonth);
        $this->transaction($expense, -700, $currentMonth);

        $analysis = $this->service->analysis($currentMonth);

        $this->assertTrue($analysis['actualsCalculated']);
        $this->assertTrue($analysis['hasDocuments']);
        $this->assertSame(1200.0, $analysis['actualIncome']);
        $this->assertSame(-700.0, $analysis['actualExpense']);
        $this->assertSame(100.0, $analysis['expenseVariance']);

        $currentYearChart = $this->service->fullYearAnalysis()['chart'];

        $this->assertSame(1200.0, $currentYearChart['actualIncome'][$currentMonth - 1]);
        $this->assertSame(-700.0, $currentYearChart['actualExpense'][$currentMonth - 1]);

        $this->grant('budgets.index');
        $this->actingAs($this->user)->get(route('budgets.index', ['month' => $currentMonth]))
            ->assertOk()
            ->assertViewHas('actualsCalculated', true)
            ->assertDontSee(__('No accounting document exists for calculating actual income and expense.'));

        $this->setFiscalYear($currentYear + 1);
        $this->service = new MonthlyBudgetService;
        $futureAnalysis = $this->service->analysis(1);
        $futureYearChart = $this->service->fullYearAnalysis()['chart'];

        $this->assertFalse($futureAnalysis['actualsCalculated']);
        $this->assertNull($futureAnalysis['actualIncome']);
        $this->assertNull($futureAnalysis['actualExpense']);
        $this->assertSame(array_fill(0, 12, null), $futureYearChart['actualIncome']);
        $this->assertSame(array_fill(0, 12, null), $futureYearChart['actualExpense']);

        $this->grant('budgets.index');
        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 1]))->assertOk()->assertSee(__('No accounting document exists for calculating actual income and expense.'));
    }

    public function test_budget_line_can_be_deleted(): void
    {
        $subject = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $budget = $this->budget($subject, 'expense', 800, 5);
        $this->grant('budgets.destroy');

        $this->actingAs($this->user)->delete(route('budgets.destroy', $budget))->assertRedirect(route('budgets.index', ['month' => 5]));

        $this->assertDatabaseMissing('monthly_budgets', ['id' => $budget->id]);
    }

    public function test_deleting_subject_deletes_monthly_budget(): void
    {
        $subject = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $budget = $this->budget($subject, 'expense', 800, 5);

        $subject->delete();

        $this->assertDatabaseMissing('monthly_budgets', ['id' => $budget->id]);
    }

    public function test_deleting_company_deletes_monthly_budget(): void
    {
        $subject = $this->temporarySubject('Rent', SubjectType::DEBTOR);
        $budget = $this->budget($subject, 'expense', 800, 5);

        $this->company->delete();

        $this->assertDatabaseMissing('monthly_budgets', ['id' => $budget->id]);
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
