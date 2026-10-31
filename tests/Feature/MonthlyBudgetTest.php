<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Enums\InvoiceType;
use App\Enums\PayrollStatus;
use App\Enums\SubjectType;
use App\Models\Config;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MonthlyBudget;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\Service;
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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MonthlyBudgetTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $fiscalYear;

    private User $user;

    private MonthlyBudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();

        $this->fiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->syncWithoutDetaching([$this->user->id]);

        $this->service = new MonthlyBudgetService;

        $this->withCookies(['active-fiscal-year-id' => (string) $this->fiscalYear->id]);
        $_COOKIE['active-fiscal-year-id'] = (string) $this->fiscalYear->id;
        config([
            'active-fiscal-year-id' => $this->fiscalYear->id,
            'active-company-fiscal-year' => 1405,
        ]);
    }

    private function temporarySubject(string $name, SubjectType $type): Subject
    {
        return Subject::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => $name,
            'type' => $type,
            'is_permanent' => false,
            'parent_id' => null,
        ]);
    }

    private function childSubject(Subject $parent, string $name, SubjectType $type): Subject
    {
        return Subject::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => $name,
            'type' => $type,
            'is_permanent' => false,
            'parent_id' => $parent->id,
        ]);
    }

    private function budget(Subject $subject, string $type, float|int|string $amount, int $month): MonthlyBudget
    {
        return MonthlyBudget::create([
            'fiscal_year_id' => $this->fiscalYear->id,
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
            'date' => jalali_to_gregorian((int) $this->fiscalYear->year, $month, 10, '-'),
            'creator_id' => $this->user->id,
            'approved_at' => $approved ? now() : null,
            'approver_id' => $approved ? $this->user->id : null,
            'title' => 'budget actual',
            'fiscal_year_id' => $this->fiscalYear->id,
        ]);

        Transaction::create([
            'value' => $value,
            'subject_id' => $subject->id,
            'document_id' => $document->id,
            'user_id' => $this->user->id,
            'desc' => 'budget test',
        ]);
    }

    private function productInvoice(Product $product, InvoiceType $type, int $month, float $price, float $discount, float $cost, ?Invoice $voided = null): Invoice
    {
        $document = Document::create([
            'number' => Document::withoutGlobalScopes()->max('number') + 1,
            'date' => jalali_to_gregorian(1405, $month, 10, '-'),
            'creator_id' => $this->user->id,
            'approver_id' => $this->user->id,
            'approved_at' => now(),
            'title' => 'Monthly profitability test',
            'fiscal_year_id' => $this->fiscalYear->id,
        ]);
        $invoice = Invoice::create([
            'number' => random_int(100000, 999999),
            'date' => $document->date,
            'document_id' => $document->id,
            'customer_id' => Customer::factory()->create(['fiscal_year_id' => $this->fiscalYear->id])->id,
            'creator_id' => $this->user->id,
            'invoice_type' => $type,
            'returned_invoice_id' => $voided?->id,
            'status' => 3,
            'vat' => 0,
            'subtraction' => 0,
            'amount' => $price - $discount,
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => $price,
            'unit_discount' => $discount,
            'vat' => 0,
            'amount' => $price - $discount,
            'cog_after' => $cost,
        ]);

        return $invoice;
    }

    private function grant(string ...$permissions): void
    {
        $this->user->givePermissionTo(collect($permissions)->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission]))->all());
    }

    private function setFiscalYear(int $fiscalYear): void
    {
        $this->fiscalYear->update(['year' => $fiscalYear]);
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

    public function test_forecasts_are_available_in_one_searchable_table_without_a_modal(): void
    {
        foreach (range(1, 6) as $index) {
            $subject = $this->temporarySubject('Forecast '.$index, SubjectType::CREDITOR);
            $this->budget($subject, 'income', $index * 100, 5);
        }
        $this->grant('budgets.index');

        $response = $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]));
        $response->assertOk()->assertViewHas('budgetLines', fn ($lines) => $lines->count() === 6)
            ->assertSee('forecast-search', false)->assertSee('forecast-type', false)
            ->assertDontSee('all-monthly-forecasts-modal', false)
            ->assertSeeInOrder([__('Monthly overview'), __('Income and expense drivers'), __('Product Profitability'), __('Forecasts')]);
        foreach (range(1, 6) as $index) {
            $response->assertSee('Forecast '.$index);
        }
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
        $income = $this->childSubject($this->temporarySubject('Income root', SubjectType::CREDITOR), 'Itemized income', SubjectType::CREDITOR);
        $expense = $this->childSubject($this->temporarySubject('Expense root', SubjectType::DEBTOR), 'Itemized expense', SubjectType::DEBTOR);
        $this->budget($income, 'income', 1000, 5);
        $this->budget($expense, 'expense', 800, 5);
        $this->transaction($income, 1200, 5);
        $this->transaction($expense, -700, 5);
        $this->grant('budgets.index');

        $response = $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]));

        $response->assertOk()->assertViewHas('categoryDrivers', function (array $drivers) {
            return $drivers['income']->sole()['amount'] === 1200.0
                && $drivers['expense']->sole()['amount'] === 700.0;
        })->assertViewHas('comparisonDatasets', function (array $datasets) {
            return $datasets[0]['data'][__('Expense')] === 800.0
                && $datasets[1]['data'][__('Expense')] === 700.0;
        })->assertSee('monthlyBudgetVarianceChart', false)
            ->assertSee(__('Manual Forecast'))->assertSee(__('Entered by the user.'))
            ->assertSee(__("Calculated automatically from the actual value or the previous month's applied forecast."))
            ->assertSee('(۸۰۰)', false)->assertSee('(۷۰۰)', false)
            ->assertDontSee('-۸۰۰', false)->assertDontSee('-۷۰۰', false)
            ->assertDontSee("type: 'pie'", false);
    }

    public function test_drivers_rank_five_moein_accounts_by_subtree_balance_without_forecasts(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 7, 15, '-').' 12:00:00', 'Asia/Tehran'));
        $income = $this->temporarySubject('Income root', SubjectType::CREDITOR);
        $expense = $this->temporarySubject('Expense root', SubjectType::DEBTOR);
        $expected = [];
        foreach (range(1, 7) as $index) {
            $account = $this->childSubject($income, 'Moein '.$index, SubjectType::CREDITOR);
            $leaf = $this->childSubject($account, 'Detail '.$index, SubjectType::CREDITOR);
            $leaf->update(['is_permanent' => true]);
            $this->transaction($account, $index * 100, 6);
            $this->transaction($leaf, $index * 50, 6);
            $this->transaction($leaf, 99999, 6, approved: false);
            $this->transaction($leaf, $index * 50, 5);
            $expected[$index] = $account->id;
        }
        $cost = $this->childSubject($expense, 'Actual expense Moein', SubjectType::DEBTOR);
        $this->transaction($cost, -500, 6);
        $this->transaction($income, 10000, 6);
        $this->budget($income, 'income', 1, 6);
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 6]))->assertOk()
            ->assertViewHas('categoryDrivers', function (array $drivers) use ($expected, $cost) {
                $top = $drivers['income']->first();

                return $drivers['income']->pluck('subject.id')->all() === array_reverse(array_slice($expected, 2))
                    && $top['amount'] === 1050.0 && $top['previous'] === 350.0
                    && abs($top['share'] - 1050 / 14200 * 100) < 0.001
                    && $drivers['expense']->sole()['subject']->is($cost);
            });
    }

    public function test_module_shares_use_gross_payroll_and_net_approved_cost_accounts(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 7, 15, '-').' 12:00:00', 'Asia/Tehran'));
        $root = $this->temporarySubject('Expenses', SubjectType::DEBTOR);
        $cogs = $this->childSubject($root, 'COGS account', SubjectType::DEBTOR);
        $service = $this->childSubject($root, 'Purchased services', SubjectType::DEBTOR);
        $salary = $this->childSubject($root, 'Salary posted', SubjectType::DEBTOR);
        $cogsLeaf = $this->childSubject($cogs, 'Product costs', SubjectType::DEBTOR);
        $serviceLeaf = $this->childSubject($service, 'Service costs', SubjectType::DEBTOR);
        $this->transaction($salary, -500, 6);
        $this->transaction($cogsLeaf, -350, 6);
        $this->transaction($cogsLeaf, 50, 6);
        $this->transaction($serviceLeaf, -250, 6);
        $this->transaction($serviceLeaf, 50, 6);
        $this->transaction($serviceLeaf, -9999, 6, approved: false);
        foreach (['cost_of_goods_sold' => $cogs->id, 'cogs_service' => $service->id] as $key => $id) {
            Config::create(['fiscal_year_id' => $this->fiscalYear->id, 'key' => $key, 'value' => $id, 'type' => 3, 'category' => 1]);
        }
        // A configured parent and a module's own leaf account must count only once.
        Product::factory()->create(['fiscal_year_id' => $this->fiscalYear->id, 'cogs_subject_id' => $cogsLeaf->id]);
        Service::factory()->create(['fiscal_year_id' => $this->fiscalYear->id, 'cogs_subject_id' => $serviceLeaf->id]);
        $payroll = [
            'fiscal_year_id' => $this->fiscalYear->id,
            'year' => 1405, 'month' => 6, 'total_earnings' => 200,
            'total_deductions' => 50, 'net_payment' => 150, 'employer_insurance' => 50,
        ];
        foreach ([PayrollStatus::Approved, PayrollStatus::Paid, PayrollStatus::Draft, PayrollStatus::PendingManagerApproval] as $status) {
            Payroll::factory()->create([...$payroll, 'status' => $status]);
        }
        Payroll::factory()->create([...$payroll, 'status' => PayrollStatus::Approved, 'month' => 5]);
        Payroll::factory()->create([...$payroll, 'status' => PayrollStatus::Approved, 'year' => 1404]);
        $other = FiscalYear::factory()->create(['year' => 1405]);
        Payroll::factory()->create([...$payroll, 'status' => PayrollStatus::Approved, 'fiscal_year_id' => $other->id]);
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 6]))->assertOk()
            ->assertViewHas('actualExpense', -1000.0)
            ->assertViewHas('moduleCostShares', fn (array $shares) => $shares === [
                'payroll' => ['amount' => 500.0, 'share' => 50.0],
                'cogs' => ['amount' => 300.0, 'share' => 30.0],
                'services' => ['amount' => 200.0, 'share' => 20.0],
            ])->assertSee(__('Module share of total expenses'));
    }

    public function test_module_shares_handle_missing_expenses_configuration_and_payroll_overposting(): void
    {
        config(['amir.cost_of_goods_sold' => null, 'amir.cogs_service' => null]);
        $shares = $this->service->moduleCostShares(6, null);
        $this->assertNull($shares['payroll']['share']);
        $this->assertNull($shares['cogs']['amount']);
        $this->assertNull($shares['services']['amount']);
        $this->assertNull($this->service->moduleCostShares(6, 0)['payroll']['share']);
        $this->assertNull($this->service->moduleCostShares(6, 100)['payroll']['share']);

        Payroll::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id, 'year' => 1405, 'month' => 6,
            'total_earnings' => 200, 'employer_insurance' => 0, 'status' => PayrollStatus::Approved,
        ]);
        $this->assertSame(200.0, $this->service->moduleCostShares(6, -100)['payroll']['share']);
    }

    public function test_current_month_module_costs_and_drivers_respect_elapsed_day_cutoff(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 6, 15, '-').' 12:00:00', 'Asia/Tehran'));
        $root = $this->temporarySubject('Expenses', SubjectType::DEBTOR);
        $cost = $this->childSubject($root, 'Service cost', SubjectType::DEBTOR);
        config(['amir.cogs_service' => $cost->id]);
        foreach ([10, 25] as $day) {
            $this->transaction($cost, -100, 6);
            Document::query()->latest('id')->firstOrFail()->update(['date' => jalali_to_gregorian(1405, 6, $day, '-')]);
            Payroll::factory()->create([
                'fiscal_year_id' => $this->fiscalYear->id, 'year' => 1405, 'month' => 6,
                'total_earnings' => 50, 'employer_insurance' => 0, 'status' => PayrollStatus::Approved,
                'issue_date' => jalali_to_gregorian(1405, 6, $day, '-'),
            ]);
        }
        $shares = $this->service->moduleCostShares(6, -100, 15);
        $this->assertSame(50.0, $shares['payroll']['amount']);
        $this->assertSame(100.0, $shares['services']['amount']);
        $this->assertSame(-100.0, $this->service->driverActuals(6, 15)->sole()['actual']);
        $this->assertSame(-200.0, $this->service->driverActuals(6)->sole()['actual']);
    }

    public function test_monthly_profitability_uses_approved_invoice_cost_snapshots_and_reverses_returns(): void
    {
        $product = Product::factory()->create(['fiscal_year_id' => $this->fiscalYear->id, 'name' => 'Profit product']);
        $this->productInvoice($product, InvoiceType::SELL, 4, 1000, 100, 400);
        $this->productInvoice($product, InvoiceType::SELL, 5, 1500, 100, 500);
        $this->productInvoice($product, InvoiceType::RETURN_SELL, 5, 200, 0, 100);
        $this->productInvoice($product, InvoiceType::SELL, 5, 9999, 0, 1);
        Invoice::query()->latest('id')->firstOrFail()->document()->update(['approved_at' => null]);

        $analysis = $this->service->profitabilityAnalysis(5);

        $this->assertSame(1200.0, $analysis['sales']);
        $this->assertSame(400.0, $analysis['cogs']);
        $this->assertSame(800.0, $analysis['grossProfit']);
        $this->assertSame(500.0, $analysis['comparisons']['grossProfit']['previous']);
        $this->assertSame(300.0, $analysis['comparisons']['grossProfit']['change']);
        $this->assertSame(900.0, $analysis['comparisons']['sales']['previous']);
        $this->assertSame(300.0, $analysis['comparisons']['sales']['change']);
        $this->assertSame(66.66666666666666, $analysis['products'][0]['grossMargin']);
        $this->assertSame('Profit product', $analysis['products'][0]['name']);
    }

    public function test_returns_and_voids_reverse_their_original_product_movement(): void
    {
        $product = Product::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        $sale = $this->productInvoice($product, InvoiceType::SELL, 5, 1500, 0, 500);
        $return = $this->productInvoice($product, InvoiceType::RETURN_SELL, 5, 200, 0, 100);
        $this->productInvoice($product, InvoiceType::VOID, 5, 1500, 0, 500, $sale);
        $this->productInvoice($product, InvoiceType::VOID, 5, 200, 0, 100, $return);
        $this->productInvoice($product, InvoiceType::BUY, 5, 1000, 0, 0);
        $purchaseReturn = $this->productInvoice($product, InvoiceType::RETURN_BUY, 5, 200, 0, 0);
        $this->productInvoice($product, InvoiceType::VOID, 5, 200, 0, 0, $purchaseReturn);

        $analysis = $this->service->profitabilityAnalysis(5);

        $this->assertSame(0.0, $analysis['sales']);
        $this->assertSame(0.0, $analysis['cogs']);
        $this->assertSame(1000.0, $analysis['purchases']);
        $this->assertSame(0.0, $analysis['grossProfit']);
        $this->assertNull($analysis['grossMargin']);
    }

    public function test_current_month_accounting_and_product_comparisons_use_equal_elapsed_days(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 5, 15, '-').' 12:00:00', 'Asia/Tehran'));
        $income = $this->childSubject($this->temporarySubject('Income root', SubjectType::CREDITOR), 'Elapsed sales', SubjectType::CREDITOR);
        $expense = $this->childSubject($this->temporarySubject('Expense root', SubjectType::DEBTOR), 'Elapsed costs', SubjectType::DEBTOR);
        $this->budget($income, 'income', 2000, 5);
        $this->transaction($income, 1000, 4);
        $this->transaction($expense, -400, 4);
        $this->transaction($income, 1500, 5);
        $this->transaction($expense, -500, 5);
        foreach ([4, 5] as $month) {
            $this->transaction($income, 9000, $month);
            Document::query()->latest('id')->firstOrFail()->update(['date' => jalali_to_gregorian(1405, $month, 25, '-')]);
        }
        $product = Product::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        $this->productInvoice($product, InvoiceType::SELL, 4, 1000, 0, 400);
        $this->productInvoice($product, InvoiceType::SELL, 5, 1500, 0, 500);
        foreach ([4, 5] as $month) {
            $this->productInvoice($product, InvoiceType::SELL, $month, 9000, 0, 100)->document()->update(['date' => jalali_to_gregorian(1405, $month, 25, '-')]);
        }
        $this->grant('budgets.index');

        $response = $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]));
        $response->assertOk()->assertViewHas('actualIncome', 1500.0)->assertViewHas('actualExpense', -500.0)
            ->assertViewHas('forecastIncome', 2000.0)
            ->assertViewHas('profitability', function (array $metrics) {
                return $metrics['isCurrentMonth'] && $metrics['sales'] === 1500.0
                    && $metrics['comparisons']['sales']['previous'] === 1000.0
                    && $metrics['accountingNet'] === 1000.0
                    && $metrics['accountingComparisons']['income']['previous'] === 1000.0
                    && $metrics['accountingComparisons']['accountingNet']['previous'] === 600.0;
            })->assertViewHas('categoryDrivers', fn (array $drivers) => $drivers['income']->sole()['percent'] === 50.0)
            ->assertSee(route('transactions.index', ['subject_id' => $income->id]), false)
            ->assertSee(__('Accounting Net Profit or Loss'))
            ->assertSee(formatNumber(1000.0));

        $this->assertSame(10500.0, $this->service->analysis(5)['actualIncome']);
    }

    public function test_product_table_keeps_losses_visible_beyond_the_ten_largest_contributors(): void
    {
        foreach (range(1, 11) as $index) {
            $product = Product::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
            $this->productInvoice($product, InvoiceType::SELL, 5, 1000, 0, 400);
        }
        $loss = Product::factory()->create(['fiscal_year_id' => $this->fiscalYear->id, 'name' => 'Loss-making sale']);
        $this->productInvoice($loss, InvoiceType::SELL, 5, 100, 0, 200);
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))->assertOk()
            ->assertViewHas('profitability', fn (array $metrics) => $metrics['products']->count() === 12 && $metrics['products']->last()['grossProfit'] === -100.0)
            ->assertSee('Loss-making sale')->assertSee(__('Loss-making products only'));
    }

    public function test_future_month_is_labeled_as_upcoming_and_first_month_comparison_is_unavailable(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 5, 15, '-').' 12:00:00', 'Asia/Tehran'));
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 6]))->assertOk()
            ->assertSee(__('Upcoming month'))->assertDontSee(__('Month to date'));
        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 1]))->assertOk()
            ->assertSee(__('Previous month comparison is unavailable for the first month of the fiscal year.'));
    }

    public function test_product_changes_show_unavailable_and_zero_as_neutral(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 8, 15, '-').' 12:00:00', 'Asia/Tehran'));
        $product = Product::factory()->create(['fiscal_year_id' => $this->fiscalYear->id, 'name' => 'Steady product']);
        $this->productInvoice($product, InvoiceType::SELL, 1, 1000, 0, 400);
        $this->productInvoice($product, InvoiceType::SELL, 4, 1000, 0, 400);
        $this->productInvoice($product, InvoiceType::SELL, 5, 1000, 0, 400);
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 1]))->assertOk()
            ->assertViewHas('profitability', fn (array $metrics) => is_null($metrics['products']->sole()['salesChange']))
            ->assertSeeInOrder(['Steady product', 'data-direction="neutral"', '—', 'data-direction="neutral"', '—'], false);
        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))->assertOk()
            ->assertViewHas('profitability', fn (array $metrics) => $metrics['products']->sole()['salesChange'] === 0.0)
            ->assertSeeInOrder(['Steady product', 'data-direction="neutral"', '→', 'data-direction="neutral"', '→'], false);
    }

    public function test_drivers_without_previous_month_documents_are_unavailable_not_new(): void
    {
        $this->travelTo(Carbon::parse(jalali_to_gregorian(1405, 8, 15, '-').' 12:00:00', 'Asia/Tehran'));
        $root = $this->temporarySubject('Driver income root', SubjectType::CREDITOR);
        $existing = $this->childSubject($root, 'Existing driver', SubjectType::CREDITOR);
        $added = $this->childSubject($root, 'Added driver', SubjectType::CREDITOR);
        $this->transaction($existing, 100, 5);
        $this->transaction($existing, 100, 6);
        $this->transaction($existing, 100, 7);
        $this->transaction($added, 50, 7);
        $this->grant('budgets.index');

        $response = $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))->assertOk()
            ->assertViewHas('categoryDrivers', fn (array $drivers) => is_null($drivers['income']->sole()['previous']));
        $this->assertStringNotContainsString(__('New'), Str::between($response->getContent(), 'Existing driver', '</article>'));
        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 7]))->assertOk()
            ->assertViewHas('categoryDrivers', function (array $drivers) use ($added) {
                $driver = $drivers['income']->first(fn (array $driver) => $driver['subject']->is($added));

                return $driver['previous'] === 0.0 && is_null($driver['percent']);
            })->assertSeeInOrder(['Added driver', __('New')]);
    }

    public function test_actual_category_comparison_stays_stable_when_forecast_hierarchy_changes(): void
    {
        $root = $this->temporarySubject('Stable income category', SubjectType::CREDITOR);
        $child = $this->childSubject($root, 'Forecast detail', SubjectType::CREDITOR);
        $this->budget($child, 'income', 50, 4);
        $this->budget($root, 'income', 500, 5);
        $this->transaction($root, 100, 4);
        $this->transaction($child, 100, 4);
        $this->transaction($root, 100, 5);
        $this->transaction($child, 200, 5);
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))->assertOk()
            ->assertViewHas('categoryDrivers', function (array $drivers) use ($child) {
                $category = $drivers['income']->sole();

                return $category['subject']->is($child) && $category['amount'] === 200.0
                    && $category['previous'] === 100.0 && abs($category['share'] - 200 / 300 * 100) < 0.001
                    && $category['change'] === 100.0 && $category['percent'] === 100.0;
            });
    }

    public function test_view_only_permission_hides_forecast_mutations(): void
    {
        $subject = $this->temporarySubject('Read only forecast', SubjectType::CREDITOR);
        $this->budget($subject, 'income', 1000, 5);
        $this->grant('budgets.index');

        $this->actingAs($this->user)->get(route('budgets.index', ['month' => 5]))->assertOk()
            ->assertSee('Read only forecast')->assertDontSee(__('Save Forecast'))
            ->assertDontSee(__('Copy Previous Month'))
            ->assertDontSee(__('Delete forecast for :subject', ['subject' => $subject->name]));
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
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'Budget permanent asset',
            'is_permanent' => true,
        ]);
        $otherFiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        Subject::withoutGlobalScopes()->create([
            'fiscal_year_id' => $otherFiscalYear->id,
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
            'fiscal_year_id' => $this->fiscalYear->id,
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
                'fiscal_year_id' => $this->fiscalYear->id,
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
            'fiscal_year_id' => $this->fiscalYear->id,
            'is_permanent' => true,
        ]);
        $otherFiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        $otherSubject = Subject::withoutGlobalScopes()->create([
            'fiscal_year_id' => $otherFiscalYear->id,
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
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'Income permanent grandchild',
            'type' => SubjectType::CREDITOR,
            'is_permanent' => true,
            'parent_id' => $incomeChild->id,
        ]);
        $expenseRoot = $this->temporarySubject('Expense root', SubjectType::DEBTOR);
        $expenseChild = $this->childSubject($expenseRoot, 'Expense child', SubjectType::DEBTOR);
        $expenseGrandchild = Subject::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
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
        $this->travelTo(Carbon::parse(jalali_to_gregorian($currentYear, $currentMonth, 15, '-').' 12:00:00', 'Asia/Tehran'));
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

        $this->fiscalYear->delete();

        $this->assertDatabaseMissing('monthly_budgets', ['id' => $budget->id]);
    }

    public function test_subjects_export_includes_monthly_budgets(): void
    {
        $subject = Subject::factory()->create(['is_permanent' => false]);
        $budget = MonthlyBudget::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'subject_id' => $subject->id,
            'month' => 4,
            'budget_type' => 'income',
            'forecast_amount' => 125000,
        ]);

        $exportData = FiscalYearService::exportData($this->fiscalYear->id, [FiscalYearSection::SUBJECTS->value]);

        $this->assertArrayHasKey('monthly_budgets', $exportData);
        $this->assertCount(1, $exportData['monthly_budgets']);
        $this->assertSame($budget->id, $exportData['monthly_budgets'][0]['id']);
    }

    public function test_monthly_budgets_are_imported_with_remapped_subjects(): void
    {
        $subject = Subject::factory()->create(['is_permanent' => false]);
        MonthlyBudget::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'subject_id' => $subject->id,
            'month' => 7,
            'budget_type' => 'expense',
            'forecast_amount' => 98765.43,
        ]);

        $exportData = FiscalYearService::exportData($this->fiscalYear->id, [FiscalYearSection::SUBJECTS->value]);
        $target = FiscalYearService::importData($exportData, ['name' => 'Next Year', 'fiscal_year' => 1404]);

        $importedSubject = Subject::withoutGlobalScopes()->where('fiscal_year_id', $target->id)->sole();
        $importedBudget = MonthlyBudget::withoutGlobalScopes()->where('fiscal_year_id', $target->id)->sole();

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
                'fiscal_year_id' => $this->fiscalYear->id,
                'subject_id' => 20,
                'month' => 2,
                'budget_type' => 'income',
                'forecast_amount' => '1000.00',
            ]],
        ], ['name' => 'No Subjects', 'fiscal_year' => 1404]);

        $this->assertSame(0, MonthlyBudget::withoutGlobalScopes()->where('fiscal_year_id', $target->id)->count());
        Log::shouldHaveReceived('warning')->once()->with('Skipping monthly budgets import due to missing subject mapping.', ['target_year_id' => $target->id]);
    }
}
