<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PayrollStatus;
use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\MonthlyBudget;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subject;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthlyBudgetService
{
    public const MONTHS = [
        1 => 'Farvardin',
        2 => 'Ordibehesht',
        3 => 'Khordad',
        4 => 'Tir',
        5 => 'Mordad',
        6 => 'Shahrivar',
        7 => 'Mehr',
        8 => 'Aban',
        9 => 'Azar',
        10 => 'Dey',
        11 => 'Bahman',
        12 => 'Esfand',
    ];

    private ?Collection $temporarySubjects = null;

    private ?Collection $allSubjects = null;

    private array $monthlyBalances = [];

    private array $monthlyDocumentCounts = [];

    private array $monthlyIncomeAndExpense = [];

    private ?Collection $approvedSubjectBalances = null;

    private ?array $balanceSubjectIds = null;

    private ?Collection $prefetchedManualBudgetsByMonth = null;

    private ?int $activeFiscalYear = null;

    private ?array $currentJalaliYearMonth = null;

    private array $actualDayLimits = [];

    /** A separate snapshot keeps elapsed-period comparisons from changing full-month forecasts. */
    public function analysisThroughDay(int $month, int $day): array
    {
        $analysis = $this->analysis($month);
        $snapshot = new self;
        $snapshot->actualDayLimits[$month] = $day;
        $actuals = $snapshot->analysis($month);
        foreach (['actualIncome', 'actualExpense', 'hasDocuments', 'actualsCalculated', 'documentCount'] as $key) {
            $analysis[$key] = $actuals[$key];
        }
        $analysis['incomeVariance'] = is_null($analysis['actualIncome']) ? null : $analysis['actualIncome'] - $analysis['forecastIncome'];
        $analysis['expenseVariance'] = is_null($analysis['actualExpense']) ? null : $analysis['actualExpense'] - $analysis['forecastExpense'];
        $actualLines = $actuals['budgetLines']->keyBy(fn (array $line) => (int) $line['subject']->id);
        $analysis['budgetLines'] = $analysis['budgetLines']->map(function (array $line) use ($actualLines) {
            $line['actual'] = $actualLines->get((int) $line['subject']->id)['actual'] ?? null;
            $line['variance'] = is_null($line['actual']) ? null : $line['actual'] - $line['forecast'];
            $line['variancePercent'] = is_null($line['variance']) || $line['forecast'] == 0 ? null : $line['variance'] / abs($line['forecast']) * 100;

            return $line;
        });

        return $analysis;
    }

    /** Actual second-level accounts include every descendant, independently of forecast rows. */
    public function driverActuals(int $month, ?int $dayLimit = null): Collection
    {
        if ($dayLimit !== null) {
            $snapshot = new self;
            $snapshot->actualDayLimits[$month] = $dayLimit;

            return $snapshot->driverActuals($month);
        }

        if ($this->documentCountForMonth($month) === 0) {
            return collect();
        }

        $subjects = $this->allSubjects();
        $roots = $subjects->where('is_permanent', false)->whereNull('parent_id')->keyBy('id');
        $accounts = $subjects->filter(fn (Subject $subject) => $roots->has($subject->parent_id));
        $descendants = $this->descendantIdsBySubject($accounts->pluck('id')->all(), $subjects);
        $balances = $this->subjectBalancesForMonth($month, $this->balanceSubjectIds());

        return $accounts->map(function (Subject $subject) use ($descendants, $balances) {
            $ids = $descendants[(int) $subject->id];
            $actual = (float) collect($ids)->sum(fn (int $id) => (float) ($balances[$id] ?? 0));
            $historical = (float) collect($ids)->sum(fn (int $id) => (float) ($this->approvedSubjectBalances()[$id] ?? 0));

            return [
                'subject' => $subject,
                'actual' => $actual,
                'type' => $this->subjectDirection($subject, $historical, $actual),
            ];
        })->values();
    }

    /** Module amounts are compared with accounting expenses, never added to them again. */
    public function moduleCostShares(int $month, ?float $actualExpense, ?int $dayLimit = null): array
    {
        $snapshot = $this;
        if ($dayLimit !== null) {
            $snapshot = new self;
            $snapshot->actualDayLimits[$month] = $dayLimit;
        }
        $year = (int) FiscalYear::query()->findOrFail(getActiveFiscalYear())->year;
        $payrolls = Payroll::query()->where('year', $year)->where('month', $month)
            ->whereIn('status', [PayrollStatus::Approved, PayrollStatus::Paid]);
        if ($dayLimit !== null) {
            [, $cutoff] = $snapshot->monthDateRange($month);
            $payrolls->whereDate('issue_date', '<=', $cutoff);
        }
        $payrollCost = (float) $payrolls->sum(DB::raw('total_earnings + COALESCE(employer_insurance, 0)'));
        $subjects = $snapshot->allSubjects();
        $balances = $snapshot->subjectBalancesForMonth($month, $snapshot->balanceSubjectIds());
        $total = is_null($actualExpense) ? null : -$actualExpense;
        $accountCost = function (Collection $accountIds) use ($snapshot, $subjects, $balances): ?float {
            $accountIds = $accountIds->filter()->map(fn ($id) => (int) $id)
                ->intersect($snapshot->balanceSubjectIds())->unique()->values();
            if ($accountIds->isEmpty()) {
                return null;
            }
            $ids = collect($snapshot->descendantIdsBySubject($accountIds->all(), $subjects))->flatten()->unique();

            return -(float) $ids->sum(fn (int $id) => (float) ($balances[$id] ?? 0));
        };
        $amounts = [
            'payroll' => $payrollCost,
            'cogs' => $accountCost(Product::query()->pluck('cogs_subject_id')->push(config('amir.cost_of_goods_sold'))),
            'services' => $accountCost(Service::query()->pluck('cogs_subject_id')->push(config('amir.cogs_service'))),
        ];

        return collect($amounts)->mapWithKeys(fn (?float $amount, string $key) => [$key => [
            'amount' => $amount,
            'share' => ! is_null($amount) && $total > 0 ? $amount / $total * 100 : null,
        ]])->all();
    }

    public function saveForecast(int $month, array $data): MonthlyBudget
    {
        app(FiscalYearMutationGuard::class)->ensureOpen();

        return DB::transaction(function () use ($month, $data) {
            $this->validateHierarchyForecast($month, $data);

            return MonthlyBudget::updateOrCreate(
                [
                    'fiscal_year_id' => getActiveFiscalYear(),
                    'month' => $month,
                    'subject_id' => $data['subject_id'],
                ],
                [
                    'budget_type' => $data['budget_type'],
                    'forecast_amount' => $data['forecast_amount'],
                ]
            );
        });
    }

    /**
     * A manual root forecast is the total for its hierarchy, so its direct-child
     * breakdown must use the same direction and fit inside that total.
     */
    private function validateHierarchyForecast(int $month, array $data): void
    {
        $subject = Subject::query()->findOrFail((int) $data['subject_id']);
        $rootId = (int) ($subject->parent_id ?: $subject->id);
        Subject::query()->whereKey($rootId)->lockForUpdate()->firstOrFail();
        $childIds = Subject::query()->where('parent_id', $rootId)->where('is_permanent', false)
            ->pluck('id')->map(fn ($id) => (int) $id);
        $budgets = MonthlyBudget::query()->where('month', $month)
            ->whereIn('subject_id', [...$childIds->all(), $rootId])
            ->get()->keyBy(fn (MonthlyBudget $budget) => (int) $budget->subject_id);
        $rootBudget = (int) $subject->id === $rootId ? null : $budgets->get($rootId);
        $rootType = (int) $subject->id === $rootId ? $data['budget_type'] : $rootBudget?->budget_type;
        $rootAmount = (int) $subject->id === $rootId ? (float) $data['forecast_amount'] : (float) ($rootBudget?->forecast_amount ?? 0);

        if (is_null($rootType)) {
            return;
        }

        $childForecasts = $childIds->map(function (int $childId) use ($subject, $data, $budgets) {
            if ($childId === (int) $subject->id) {
                return [
                    'type' => $data['budget_type'],
                    'amount' => (float) $data['forecast_amount'],
                ];
            }

            $budget = $budgets->get($childId);

            return $budget ? [
                'type' => $budget->budget_type,
                'amount' => (float) $budget->forecast_amount,
            ] : null;
        })->filter();

        if ($childForecasts->contains(fn (array $forecast) => $forecast['type'] !== $rootType)) {
            throw ValidationException::withMessages([
                'forecast_amount' => __('A parent forecast and its lower-level forecasts must use the same income or expense direction.'),
            ]);
        }

        if ((float) $childForecasts->sum('amount') > $rootAmount + 0.005) {
            throw ValidationException::withMessages([
                'forecast_amount' => __('The combined lower-level forecasts cannot exceed the parent forecast.'),
            ]);
        }
    }

    public function saveForecastForMonths(array $months, array $data): Collection
    {
        app(FiscalYearMutationGuard::class)->ensureOpen();

        $months = collect($months)->map(fn ($month) => (int) $month)->unique()->sort()->values();
        $unavailableMonths = $months->reject(fn (int $month) => $this->subjectIsAvailableForForecast($month, (int) $data['subject_id'], allowExistingSubject: true));

        if ($unavailableMonths->isNotEmpty()) {
            $monthLabels = $unavailableMonths->map(fn (int $month) => __(self::MONTHS[$month]))->join(config('app.locale') === 'fa' ? '، ' : ', ');

            throw ValidationException::withMessages(['subject_id' => __('The selected subject overlaps an existing forecast in: :months.', ['months' => $monthLabels])]);
        }

        return DB::transaction(fn () => $months->map(fn (int $month) => $this->saveForecast($month, $data)));
    }

    /** Convert the signed amount entered by the user into the stored forecast direction and magnitude, while respecting the subject's normal balance. */
    public function normalizeSignedForecast(Subject $subject, int|float|string $signedAmount): array
    {
        $amount = trim((string) $signedAmount);
        $type = str_starts_with($amount, '-') ? 'expense' : 'income';

        if (($subject->type === SubjectType::CREDITOR && $type !== 'income') || ($subject->type === SubjectType::DEBTOR && $type !== 'expense')) {
            throw ValidationException::withMessages(['forecast_amount' => __('The forecast sign does not match the selected subject type.')]);
        }

        return [
            'budget_type' => $type,
            'forecast_amount' => ltrim($amount, '-+'),
        ];
    }

    /** The same subject cannot be selected twice; roots and their direct children may coexist. */
    public function subjectSelectionRestrictions(int $month, ?int $exceptSubjectId = null): array
    {
        return MonthlyBudget::query()->where('month', $month)
            ->when($exceptSubjectId, fn ($query) => $query->where('subject_id', '!=', $exceptSubjectId))
            ->pluck('subject_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Only roots and their direct children are exposed in forecast selectors. */
    public function selectableSubjects(array $unavailableSubjectIds = []): Collection
    {
        $subjects = $this->forecastableSubjects();
        $subjectsById = $subjects->keyBy(fn (Subject $subject) => (int) $subject->id);
        $unavailable = array_fill_keys($unavailableSubjectIds, true);

        return $subjects->reject(fn (Subject $subject) => isset($unavailable[(int) $subject->id]))
            ->map(function (Subject $subject) use ($subjectsById, $unavailable) {
                $option = clone $subject;
                $parentId = $subject->parent_id ? (int) $subject->parent_id : null;

                if ($parentId && (isset($unavailable[$parentId]) || ! $subjectsById->has($parentId))) {
                    $parentId = null;
                }

                $option->setAttribute('parent_id', $parentId);

                return $option;
            })->values();
    }

    public function forecastableSubjectIds(): array
    {
        return $this->forecastableSubjects()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function subjectIsAvailableForForecast(int $month, int $subjectId, bool $allowExistingSubject = false): bool
    {
        return in_array($subjectId, $this->forecastableSubjectIds(), true)
            && ! in_array($subjectId, $this->subjectSelectionRestrictions($month, $allowExistingSubject ? $subjectId : null), true);
    }

    /** Replace the current month's complete manual forecast set with the prior month's set. */
    public function rollover(int $month): Collection
    {
        app(FiscalYearMutationGuard::class)->ensureOpen();

        if ($month === 1) {
            throw ValidationException::withMessages(['month' => __('The first month has no previous month in the active fiscal year.')]);
        }

        return DB::transaction(function () use ($month) {
            $previousLines = MonthlyBudget::query()->where('month', $month - 1)->get();

            if ($previousLines->isEmpty()) {
                throw ValidationException::withMessages(['month' => __('No forecast exists for the previous month.')]);
            }

            MonthlyBudget::query()->where('month', $month)->delete();

            return $previousLines->map(fn (MonthlyBudget $line) => MonthlyBudget::create([
                'fiscal_year_id' => getActiveFiscalYear(),
                'subject_id' => $line->subject_id,
                'month' => $month,
                'budget_type' => $line->budget_type,
                'forecast_amount' => $line->forecast_amount,
            ]));
        });
    }

    /**
     * Build one system line per root. Direct children appear only when they have a manual forecast.
     * A child forecast either splits its subtree from the root's system remainder or becomes detail under a manual root total.
     * Hierarchies containing a manual forecast come first. Within each group,
     * roots retain subject-code order and are immediately followed by their forecasted children.
     */
    public function analysis(int $selectedMonth): array
    {
        $subjects = $this->forecastableSubjects();
        $forecastableSubjectIds = $subjects->pluck('id')->map(fn ($id) => (int) $id);
        $manualBudgetsByMonth = $this->prefetchedManualBudgetsByMonth
            ?? $this->manualBudgetsByMonth($forecastableSubjectIds);
        $manualBudgets = $manualBudgetsByMonth->get($selectedMonth, collect())->values();
        $manualBySubject = $manualBudgets->keyBy(fn (MonthlyBudget $budget) => (int) $budget->subject_id);
        $childrenByParent = $subjects->whereNotNull('parent_id')->groupBy('parent_id');
        $roots = $subjects->whereNull('parent_id')->sortBy(function (Subject $root) use ($childrenByParent, $manualBySubject) {
            $hasManualForecast = $manualBySubject->has((int) $root->id)
                || $childrenByParent->get((int) $root->id, collect())->contains(
                    fn (Subject $child) => $manualBySubject->has((int) $child->id)
                );

            return [$hasManualForecast ? 0 : 1, $root->code];
        });
        $lineSubjects = collect();
        $manualChildIdsByRoot = [];

        foreach ($roots as $root) {
            $rootId = (int) $root->id;
            $manualChildren = $childrenByParent->get($rootId, collect())->filter(fn (Subject $child) => $manualBySubject->has((int) $child->id));

            $lineSubjects->push($root, ...$manualChildren);
            $manualChildIdsByRoot[$rootId] = $manualChildren->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $documentCount = $this->documentCountForMonth($selectedMonth);
        $hasDocuments = $documentCount > 0;
        $descendantIdsBySubject = $this->descendantIdsBySubject($lineSubjects->pluck('id')->all(), $this->allSubjects());
        $lineSubjectIds = collect($descendantIdsBySubject)->flatten()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $balances = $hasDocuments ? $this->subjectBalancesForMonth($selectedMonth, $lineSubjectIds) : collect();
        $approvedSubjectBalances = $this->approvedSubjectBalances();
        $appliedForecastCache = [];

        $lines = $lineSubjects->map(function (Subject $subject) use ($selectedMonth, $manualBySubject, $manualBudgetsByMonth, $hasDocuments, $balances, $approvedSubjectBalances, $descendantIdsBySubject, $manualChildIdsByRoot, &$appliedForecastCache) {
            $budget = $manualBySubject->get((int) $subject->id);
            $isChild = (bool) $subject->parent_id;
            $classificationSubjectIds = $descendantIdsBySubject[(int) $subject->id] ?? [(int) $subject->id];
            $subjectIds = $classificationSubjectIds;
            $separatelyForecastSubjectIds = [];

            if (! $isChild && ! $budget) {
                $separatelyForecastSubjectIds = collect($manualChildIdsByRoot[(int) $subject->id] ?? [])
                    ->flatMap(fn (int $childId) => $descendantIdsBySubject[$childId] ?? [$childId])->unique()->values()->all();
                $subjectIds = array_values(array_diff($subjectIds, $separatelyForecastSubjectIds));
            }

            $rawCurrentBalance = (float) collect($subjectIds)->sum(fn (int $subjectId) => (float) ($balances[$subjectId] ?? 0));
            $classificationBalance = (float) collect($classificationSubjectIds)->sum(fn (int $subjectId) => (float) ($approvedSubjectBalances[$subjectId] ?? 0));
            $manualForecast = $budget ? $this->signedManualForecast($budget) : 0.0;
            $type = $budget?->budget_type ?? $this->subjectDirection($subject, $classificationBalance, $rawCurrentBalance);
            $currentBalance = $rawCurrentBalance;
            $separatedChildren = collect($manualChildIdsByRoot[(int) $subject->id] ?? [])->map(fn (int $childId) => [
                'subjectId' => $childId,
                'subjectIds' => $descendantIdsBySubject[$childId] ?? [$childId],
                'type' => $manualBySubject->get($childId)->budget_type,
            ])->all();
            $systemForecast = $separatedChildren !== [] && ! $isChild && ! $budget
                ? $this->systemForecastForRemainder(
                    (int) $subject->id,
                    $subjectIds,
                    $separatedChildren,
                    $selectedMonth,
                    $type,
                    $manualBudgetsByMonth,
                    $appliedForecastCache
                )
                : $this->systemForecastForSubject(
                    (int) $subject->id,
                    $subjectIds,
                    $selectedMonth,
                    $type,
                    $manualBudgetsByMonth,
                    $appliedForecastCache
                );

            $forecast = $budget ? $manualForecast : $systemForecast;
            $source = $budget ? 'manual' : 'system';

            // With no amount to classify, show the configured normal balance instead of an unrelated historical direction.
            if ($hasDocuments && $forecast == 0.0 && $rawCurrentBalance == 0.0) {
                $type = $this->directionFromSubjectType($subject, $type);
            }

            $systemIncomeForecast = ! $budget && $type === 'income' ? $systemForecast : 0.0;
            $systemExpenseForecast = ! $budget && $type === 'expense' ? $systemForecast : 0.0;

            // The resolved direction determines the bucket, while the monthly balance keeps its accounting sign.
            $actual = $hasDocuments ? $currentBalance : null;
            $variance = is_null($actual) ? null : $actual - $forecast;
            $forecastMagnitude = $forecast < 0 ? -1 * $forecast : $forecast;

            return [
                'budget' => $budget,
                'subject' => $subject,
                'type' => $type,
                'source' => $source,
                'forecast' => $forecast,
                'systemForecast' => $systemForecast,
                'systemIncomeForecast' => $systemIncomeForecast,
                'systemExpenseForecast' => $systemExpenseForecast,
                'actual' => $actual,
                'variance' => $variance,
                'variancePercent' => is_null($variance) ? null : ($forecastMagnitude != 0 ? round($variance / $forecastMagnitude * 100, 2) : 0),
                'isChild' => $isChild,
                'isRemainder' => ! $isChild && ! $budget && $separatelyForecastSubjectIds !== [],
                'includedInSummary' => ! $isChild || ! $manualBySubject->has((int) $subject->parent_id),
            ];
        })->values();

        $summaryLines = $lines->where('includedInSummary', true);
        $forecastIncome = (float) $summaryLines->sum(fn (array $line) => $line['type'] === 'income' ? $line['forecast'] : 0.0);
        $forecastExpense = (float) $summaryLines->sum(fn (array $line) => $line['type'] === 'expense' ? $line['forecast'] : 0.0);
        $actualAmounts = $this->monthlyIncomeAndExpenseForMonth($selectedMonth);
        $actualIncome = $hasDocuments ? $actualAmounts['income'] : null;
        $actualExpense = $hasDocuments ? $actualAmounts['expense'] : null;

        $hasOverlappingForecasts = $lines->contains(fn (array $line) => ! $line['includedInSummary']);

        return [
            'months' => $this->translatedMonths(),
            'selectedMonth' => $selectedMonth,
            'selectedMonthLabel' => __(self::MONTHS[$selectedMonth]),
            'budgetLines' => $lines,
            'manualForecastsCount' => $manualBudgets->count(),
            'forecastIncome' => $forecastIncome,
            'forecastExpense' => $forecastExpense,
            'actualIncome' => $actualIncome,
            'actualExpense' => $actualExpense,
            'incomeVariance' => is_null($actualIncome) ? null : $actualIncome - $forecastIncome,
            'expenseVariance' => is_null($actualExpense) ? null : $actualExpense - $forecastExpense,
            'actualsCalculated' => $hasDocuments,
            'hasDocuments' => $hasDocuments,
            'documentCount' => $documentCount,
            'hasOverlappingForecasts' => $hasOverlappingForecasts,
            'hasPreviousForecast' => $selectedMonth > 1 && MonthlyBudget::query()->where('month', $selectedMonth - 1)->exists(),
        ];
    }

    /** Accounting results, invoice profitability and leading accounts for the monthly workbench, each compared with the previous month. */
    public function workbench(int $month): array
    {
        $profitability = $this->profitabilityAnalysis($month);
        $dayLimit = $profitability['elapsedDay'];
        $accountingFor = fn (int $month) => $dayLimit === null ? $this->analysis($month) : $this->analysisThroughDay($month, $dayLimit);
        $analysis = $accountingFor($month);
        $previousAnalysis = $month > 1 ? $accountingFor($month - 1) : null;
        $net = fn (?array $analysis) => is_null($analysis['actualIncome'] ?? null) || is_null($analysis['actualExpense'] ?? null)
            ? null
            : (float) $analysis['actualIncome'] + (float) $analysis['actualExpense'];
        $absolute = fn ($amount) => is_null($amount) ? null : abs((float) $amount);

        $profitability['accountingNet'] = $net($analysis);
        $profitability['accountingMargin'] = ! is_null($profitability['accountingNet']) && (float) $analysis['actualIncome'] != 0
            ? $profitability['accountingNet'] / (float) $analysis['actualIncome'] * 100
            : null;
        $profitability['accountingComparisons'] = [
            'income' => $this->comparison($absolute($analysis['actualIncome']), $absolute($previousAnalysis['actualIncome'] ?? null)),
            'expense' => $this->comparison($absolute($analysis['actualExpense']), $absolute($previousAnalysis['actualExpense'] ?? null)),
            'accountingNet' => $this->comparison($profitability['accountingNet'], $net($previousAnalysis)),
        ];

        $categoryLines = $this->driverActuals($month, $dayLimit);
        $previousAvailable = (bool) ($previousAnalysis['hasDocuments'] ?? false);
        $previousLines = $previousAvailable
            ? $this->driverActuals($month - 1, $dayLimit)->keyBy(fn (array $line) => (int) $line['subject']->id)
            : collect();
        $categoryDrivers = [];
        foreach (['income' => 'actualIncome', 'expense' => 'actualExpense'] as $type => $totalKey) {
            $total = abs((float) ($analysis[$totalKey] ?? 0));
            $categoryDrivers[$type] = $categoryLines
                ->filter(fn (array $line) => $line['type'] === $type && $line['actual'] != 0)
                ->sortByDesc(fn (array $line) => abs((float) $line['actual']))
                ->take(5)
                ->map(function (array $line) use ($previousAvailable, $previousLines, $total) {
                    $amount = abs((float) $line['actual']);
                    $previous = $previousAvailable
                        ? abs((float) ($previousLines->get((int) $line['subject']->id)['actual'] ?? 0))
                        : null;

                    return [
                        'subject' => $line['subject'],
                        'amount' => $amount,
                        'share' => $total > 0 ? ($amount / $total) * 100 : null,
                        ...$this->comparison($amount, $previous),
                    ];
                })->values();
        }

        return [
            'analysis' => $analysis,
            'profitability' => $profitability,
            'categoryDrivers' => $categoryDrivers,
            'moduleCostShares' => $this->moduleCostShares($month, $analysis['actualExpense'], $dayLimit),
        ];
    }

    /** Invoice movement metrics use approved accounting documents and line snapshots, never today's product cost. */
    public function profitabilityAnalysis(int $month): array
    {
        $fiscalYear = $this->activeFiscalYear ??= (int) FiscalYear::query()->findOrFail(getActiveFiscalYear())->year;
        [$currentYear, $currentMonth, $currentDay] = array_map('intval', explode('-', jdate('Y-n-j', now()->timestamp, '', 'Asia/Tehran', 'en')));
        $isCurrentMonth = $fiscalYear === $currentYear && $month === $currentMonth;
        $dayLimit = $isCurrentMonth ? $currentDay : null;
        $current = $this->invoiceMetricsForMonth($month, $dayLimit);
        $previous = $month > 1 ? $this->invoiceMetricsForMonth($month - 1, $dayLimit) : null;

        $current['grossProfit'] = $current['sales'] - $current['cogs'];
        if ($previous !== null) {
            $previous['grossProfit'] = $previous['sales'] - $previous['cogs'];
        }

        $current['comparisons'] = collect(['sales', 'purchases', 'cogs', 'grossProfit'])
            ->mapWithKeys(fn (string $metric) => [$metric => $this->comparison($current[$metric], $previous[$metric] ?? null)])
            ->all();
        $current['grossMargin'] = $current['sales'] != 0 ? $current['grossProfit'] / $current['sales'] * 100 : null;
        $current['isComplete'] = $fiscalYear < $currentYear || ($fiscalYear === $currentYear && $month < $currentMonth);
        $current['isCurrentMonth'] = $isCurrentMonth;
        $current['elapsedDay'] = $dayLimit;
        $current['isFuture'] = $fiscalYear > $currentYear || ($fiscalYear === $currentYear && $month > $currentMonth);
        $current['comparisonUnavailable'] = $month === 1;
        $previousProducts = collect($previous['products'] ?? [])->keyBy('id');
        $current['products'] = collect($current['products'])->sortByDesc('grossProfit')->values()
            ->map(function (array $product) use ($previousProducts, $previous) {
                $prior = $previousProducts->get($product['id']);
                $product['salesChange'] = is_null($previous) ? null : $product['sales'] - (float) ($prior['sales'] ?? 0);
                $product['grossProfitChange'] = is_null($previous) ? null : $product['grossProfit'] - (float) ($prior['grossProfit'] ?? 0);

                return $product;
            });

        return $current;
    }

    /** @return array{previous: ?float, change: ?float, percent: ?float} */
    private function comparison(?float $current, ?float $previous): array
    {
        return [
            'previous' => $previous,
            'change' => is_null($current) || is_null($previous) ? null : $current - $previous,
            'percent' => is_null($current) || is_null($previous) || $previous <= 0 ? null : (($current - $previous) / $previous) * 100,
        ];
    }

    private function invoiceMetricsForMonth(int $month, ?int $dayLimit = null): array
    {
        [$startDate, $endDate] = $this->monthDateRange($month, $dayLimit);
        $invoices = Invoice::query()->with(['voidedInvoice', 'items' => fn ($query) => $query->where('itemable_type', Product::class)->with('itemable.productGroup')])
            ->whereHas('document', fn ($query) => $query->whereNotNull('approved_at')->whereBetween('date', [$startDate, $endDate]))
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_SELL, InvoiceType::BUY, InvoiceType::RETURN_BUY, InvoiceType::VOID])
            ->get();
        $totals = ['sales' => 0.0, 'purchases' => 0.0, 'cogs' => 0.0];
        $products = [];

        foreach ($invoices as $invoice) {
            $originalType = $invoice->invoice_type;
            $reversedType = $originalType;
            if ($originalType === InvoiceType::VOID) {
                $reversedType = $invoice->voidedInvoice?->invoice_type;
            }
            $direction = match ($reversedType) {
                InvoiceType::SELL, InvoiceType::BUY => 1,
                InvoiceType::RETURN_SELL, InvoiceType::RETURN_BUY => -1,
                default => 0,
            };
            $direction *= $originalType === InvoiceType::VOID ? -1 : 1;

            foreach ($invoice->items as $item) {
                $product = $item->itemable;
                if (! $product) {
                    continue;
                }
                $itemSales = ((float) $item->unit_price * (float) $item->quantity) - (float) $item->unit_discount;
                $itemCogs = (float) $item->cog_after * (float) $item->quantity;
                $salesSign = $reversedType?->isSell() ? $direction : 0;
                $purchaseSign = $reversedType?->isBuy() ? $direction : 0;
                $cogsSign = $salesSign;
                $totals['sales'] += $itemSales * $salesSign;
                $totals['purchases'] += $itemSales * $purchaseSign;
                $totals['cogs'] += $itemCogs * $cogsSign;

                if ($salesSign !== 0) {
                    $productId = (int) $product->id;
                    $products[$productId] ??= [
                        'id' => $productId,
                        'name' => $product->name,
                        'group' => $product->productGroup?->name,
                        'sales' => 0.0,
                        'cogs' => 0.0,
                        'grossProfit' => 0.0,
                        'missingCost' => false,
                    ];
                    $products[$productId]['sales'] += $itemSales * $salesSign;
                    $products[$productId]['cogs'] += $itemCogs * $cogsSign;
                    $products[$productId]['missingCost'] = $products[$productId]['missingCost'] || $item->cog_after <= 0;
                }
            }
        }

        foreach ($products as &$product) {
            $product['grossProfit'] = $product['sales'] - $product['cogs'];
            $product['grossMargin'] = $product['sales'] != 0 ? $product['grossProfit'] / $product['sales'] * 100 : null;
        }
        unset($product);

        return [...$totals, 'products' => array_values($products)];
    }

    public function fullYearAnalysis(): array
    {
        $this->prefetchedManualBudgetsByMonth = $this->manualBudgetsByMonth(
            collect($this->forecastableSubjectIds())
        );

        try {
            $monthly = collect(array_keys(self::MONTHS))->mapWithKeys(fn (int $month) => [$month => $this->analysis($month)]);
        } finally {
            $this->prefetchedManualBudgetsByMonth = null;
        }

        return [
            'monthly' => $monthly,
            'chart' => [
                'labels' => array_values($this->translatedMonths()),
                'forecastIncome' => $monthly->pluck('forecastIncome')->values()->all(),
                'forecastExpense' => $monthly->pluck('forecastExpense')->values()->all(),
                'actualIncome' => $monthly->pluck('actualIncome')->values()->all(),
                'actualExpense' => $monthly->pluck('actualExpense')->values()->all(),
                'documentCounts' => $monthly->pluck('documentCount')->values()->all(),
            ],
            'totals' => [
                'forecastIncome' => (float) $monthly->sum('forecastIncome'),
                'forecastExpense' => (float) $monthly->sum('forecastExpense'),
                'actualIncome' => (float) $monthly->where('hasDocuments', true)->sum('actualIncome'),
                'actualExpense' => (float) $monthly->where('hasDocuments', true)->sum('actualExpense'),
            ],
        ];
    }

    public function translatedMonths(): array
    {
        return array_map(fn (string $month) => __($month), self::MONTHS);
    }

    private function forecastableSubjects(): Collection
    {
        $subjects = $this->allTemporarySubjects();
        $rootIds = $subjects->whereNull('parent_id')->pluck('id');

        return $subjects->filter(fn (Subject $subject) => is_null($subject->parent_id) || $rootIds->contains($subject->parent_id))->values();
    }

    private function allTemporarySubjects(): Collection
    {
        return $this->temporarySubjects ??= Subject::query()->where('is_permanent', false)->orderBy('code')->get(['id', 'code', 'name', 'parent_id', 'type']);
    }

    /** Every subject of the active company, including permanent descendants of temporary roots. */
    private function allSubjects(): Collection
    {
        return $this->allSubjects ??= Subject::query()->orderBy('code')->get(['id', 'code', 'name', 'parent_id', 'type', 'is_permanent']);
    }

    private function manualBudgetsByMonth(Collection $forecastableSubjectIds): Collection
    {
        return MonthlyBudget::query()->with('subject:id,code,name,parent_id,type')->orderBy('month')->orderBy('subject_id')->get()
            ->filter(fn (MonthlyBudget $budget) => $forecastableSubjectIds->contains((int) $budget->subject_id))
            ->groupBy(fn (MonthlyBudget $budget) => (int) $budget->month)
            ->map(fn (Collection $budgets) => $budgets->keyBy(fn (MonthlyBudget $budget) => (int) $budget->subject_id));
    }

    /** A completed month uses its actual balance; an open month inherits the previous month's applied forecast. */
    private function systemForecastForSubject(int $subjectId, array $subjectIds, int $month, string $type, Collection $manualBudgetsByMonth, array &$cache): float
    {
        if ($this->monthIsCompleted($month)) {
            return $this->signedBalance($subjectIds, $month);
        }

        if ($month === 1) {
            return $this->documentCountForMonth(1) > 0
                ? $this->signedBalance($subjectIds, 1)
                : 0.0;
        }

        return $this->appliedForecastForSubject($subjectId, $subjectIds, $month - 1, $type, $manualBudgetsByMonth, $cache);
    }

    /** Carry a root forecast for only the subjects left after current manual child subtrees are split out. */
    private function systemForecastForRemainder(int $subjectId, array $subjectIds, array $separatedChildren, int $month, string $type, Collection $manualBudgetsByMonth, array &$cache): float
    {
        if ($this->monthIsCompleted($month)) {
            return $this->signedBalance($subjectIds, $month);
        }

        if ($month === 1) {
            return $this->documentCountForMonth(1) > 0
                ? $this->signedBalance($subjectIds, 1)
                : 0.0;
        }

        return $this->appliedForecastForRemainder($subjectId, $subjectIds, $separatedChildren, $month - 1, $type, $manualBudgetsByMonth, $cache);
    }

    private function appliedForecastForRemainder(int $subjectId, array $subjectIds, array $separatedChildren, int $month, string $type, Collection $manualBudgetsByMonth, array &$cache): float
    {
        $cacheKey = 'remainder:'.$subjectId.':'.$month.':'.$type.':'.implode(',', $subjectIds);

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $manualBudget = $manualBudgetsByMonth->get($month, collect())->get($subjectId);

        if (! $manualBudget) {
            return $cache[$cacheKey] = $this->systemForecastForRemainder(
                $subjectId,
                $subjectIds,
                $separatedChildren,
                $month,
                $type,
                $manualBudgetsByMonth,
                $cache
            );
        }

        $remainder = $this->signedManualForecast($manualBudget);

        foreach ($separatedChildren as $child) {
            $remainder -= $this->appliedForecastForSubject(
                $child['subjectId'],
                $child['subjectIds'],
                $month,
                $child['type'],
                $manualBudgetsByMonth,
                $cache
            );
        }

        return $cache[$cacheKey] = $remainder;
    }

    /** Resolve the month sequentially so a prior manual value naturally carries into later open months. */
    private function appliedForecastForSubject(int $subjectId, array $subjectIds, int $month, string $type, Collection $manualBudgetsByMonth, array &$cache): float
    {
        $cacheKey = $subjectId.':'.$month.':'.$type.':'.implode(',', $subjectIds);

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $manualBudget = $manualBudgetsByMonth->get($month, collect())->get($subjectId);

        return $cache[$cacheKey] = $manualBudget
            ? $this->signedManualForecast($manualBudget)
            : $this->systemForecastForSubject($subjectId, $subjectIds, $month, $type, $manualBudgetsByMonth, $cache);
    }

    private function signedManualForecast(MonthlyBudget $budget): float
    {
        return $budget->budget_type === 'expense'
            ? -1 * (float) $budget->forecast_amount
            : (float) $budget->forecast_amount;
    }

    /** Determine direction from the approved balance of the subject and all descendants; use normal balance only when the total is zero. */
    private function subjectDirection(Subject $subject, float $classificationBalance, float $fallbackAmount = 0.0): string
    {
        if ($classificationBalance != 0.0) {
            return $classificationBalance < 0 ? 'expense' : 'income';
        }

        if ($fallbackAmount != 0.0) {
            return $fallbackAmount < 0 ? 'expense' : 'income';
        }

        return $subject->type === SubjectType::DEBTOR ? 'expense' : 'income';
    }

    /** Map a single-direction normal balance; a BOTH subject keeps its previously resolved direction. */
    private function directionFromSubjectType(Subject $subject, string $fallbackDirection): string
    {
        return match ($subject->type) {
            SubjectType::DEBTOR => 'expense',
            SubjectType::CREDITOR => 'income',
            SubjectType::BOTH => $fallbackDirection,
        };
    }

    private function monthIsCompleted(int $month): bool
    {
        [$currentYear, $currentMonth] = $this->currentJalaliYearMonth();
        $fiscalYear = $this->activeFiscalYear ??= (int) FiscalYear::query()->findOrFail(getActiveFiscalYear())->year;

        return $fiscalYear < $currentYear || ($fiscalYear === $currentYear && $month < $currentMonth);
    }

    private function currentJalaliYearMonth(): array
    {
        return $this->currentJalaliYearMonth ??= array_map('intval', explode('-', jdate('Y-n', now()->timestamp, '', 'Asia/Tehran', 'en')));
    }

    private function signedBalance(array $subjectIds, int $month): float
    {
        $balances = $this->subjectBalancesForMonth($month, $subjectIds);

        return (float) collect($subjectIds)->sum(fn (int $subjectId) => (float) ($balances[$subjectId] ?? 0));
    }

    /** Approved balance of every subject across the active fiscal company, used only to determine income/expense direction. */
    private function approvedSubjectBalances(): Collection
    {
        return $this->approvedSubjectBalances ??= Transaction::query()
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('documents.fiscal_year_id', getActiveFiscalYear())
            ->whereNotNull('documents.approved_at')
            ->selectRaw('transactions.subject_id, SUM(transactions.value) as balance')
            ->groupBy('transactions.subject_id')
            ->pluck('balance', 'subject_id');
    }

    /**
     * Calculate actual income and expense from each temporary root's complete transaction balance, including permanent descendants (for example customer or bank detail accounts).
     * Positive balances are income; negative balances are expense.
     * This matches the Home profit/loss calculation and avoids using SubjectType as a proxy for transaction direction.
     */
    private function monthlyIncomeAndExpenseForMonth(int $month): array
    {
        if (isset($this->monthlyIncomeAndExpense[$month])) {
            return $this->monthlyIncomeAndExpense[$month];
        }

        $subjects = $this->allTemporarySubjects();
        $roots = $subjects->whereNull('parent_id');
        $descendantIdsBySubject = $this->descendantIdsBySubject($roots->pluck('id')->all(), $this->allSubjects());
        $allSubjectIds = collect($descendantIdsBySubject)->flatten()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $balances = $this->subjectBalancesForMonth($month, $allSubjectIds);
        $income = 0.0;
        $expense = 0.0;

        foreach ($roots as $root) {
            $subjectIds = $descendantIdsBySubject[(int) $root->id] ?? [(int) $root->id];
            $rawBalance = (float) collect($subjectIds)
                ->sum(fn (int $subjectId) => (float) ($balances[$subjectId] ?? 0));
            $classificationBalance = (float) collect($subjectIds)
                ->sum(fn (int $subjectId) => (float) ($this->approvedSubjectBalances()[$subjectId] ?? 0));
            $type = $this->subjectDirection($root, $classificationBalance, $rawBalance);

            if ($type === 'expense') {
                $expense += $rawBalance;
            } else {
                $income += $rawBalance;
            }
        }

        return $this->monthlyIncomeAndExpense[$month] = compact('income', 'expense');
    }

    /** Resolve each forecast subject to itself and every descendant. */
    private function descendantIdsBySubject(array $subjectIds, Collection $subjects): array
    {
        $childrenByParent = $subjects->groupBy(fn (Subject $subject) => (int) ($subject->parent_id ?? 0));

        return collect($subjectIds)->mapWithKeys(function ($subjectId) use ($childrenByParent) {
            $subjectId = (int) $subjectId;
            $ids = [];
            $visited = [];
            $stack = [$subjectId];

            while ($stack !== []) {
                $currentId = (int) array_pop($stack);
                if (isset($visited[$currentId])) {
                    continue;
                }

                $visited[$currentId] = true;
                $ids[] = $currentId;
                foreach ($childrenByParent->get($currentId, collect()) as $child) {
                    $stack[] = (int) $child->id;
                }
            }

            return [$subjectId => $ids];
        })->all();
    }

    private function subjectBalancesForMonth(int $month, ?array $subjectIds = null): Collection
    {
        $subjectIds ??= $this->allTemporarySubjects()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($subjectIds)) {
            return collect();
        }

        if (! isset($this->monthlyBalances[$month])) {
            [$startDate, $endDate] = $this->monthDateRange($month);

            $this->monthlyBalances[$month] = Transaction::query()
                ->join('documents', 'documents.id', '=', 'transactions.document_id')
                ->where('documents.fiscal_year_id', getActiveFiscalYear())
                ->whereNotNull('documents.approved_at')
                ->whereBetween('documents.date', [$startDate, $endDate])
                ->whereIn('transactions.subject_id', $this->balanceSubjectIds())
                ->selectRaw('transactions.subject_id, SUM(transactions.value) as balance')
                ->groupBy('transactions.subject_id')
                ->pluck('balance', 'subject_id');
        }

        return $this->monthlyBalances[$month]->only($subjectIds);
    }

    /** Subjects whose balances can contribute to a temporary profit-and-loss root. */
    private function balanceSubjectIds(): array
    {
        if (! is_null($this->balanceSubjectIds)) {
            return $this->balanceSubjectIds;
        }

        $rootIds = $this->allTemporarySubjects()->whereNull('parent_id')->pluck('id')->all();

        return $this->balanceSubjectIds = collect(
            $this->descendantIdsBySubject($rootIds, $this->allSubjects())
        )->flatten()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function documentCountForMonth(int $month): int
    {
        if (isset($this->monthlyDocumentCounts[$month])) {
            return $this->monthlyDocumentCounts[$month];
        }

        [$startDate, $endDate] = $this->monthDateRange($month);

        return $this->monthlyDocumentCounts[$month] = Document::query()->where('fiscal_year_id', getActiveFiscalYear())->whereNotNull('approved_at')->whereBetween('date', [$startDate, $endDate])->count();
    }

    private function monthDateRange(int $month, ?int $dayLimit = null): array
    {
        $fiscalYear = $this->activeFiscalYear ??= (int) FiscalYear::query()->findOrFail(getActiveFiscalYear())->year;
        $lastDay = match (true) {
            $month <= 6 => 31,
            $month <= 11 => 30,
            jcheckdate(12, 30, $fiscalYear) => 30,
            default => 29,
        };
        $lastDay = min($lastDay, $dayLimit ?? $this->actualDayLimits[$month] ?? $lastDay);

        return [
            jalali_to_gregorian($fiscalYear, $month, 1, '-'),
            jalali_to_gregorian($fiscalYear, $month, $lastDay, '-'),
        ];
    }
}
