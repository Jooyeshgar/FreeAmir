<?php

namespace App\Services;

use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Document;
use App\Models\MonthlyBudget;
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

    public function saveForecast(int $month, array $data): MonthlyBudget
    {
        return DB::transaction(function () use ($month, $data) {
            $this->validateHierarchyForecast($month, $data);

            return MonthlyBudget::updateOrCreate(
                [
                    'company_id' => getActiveCompany(),
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
                'company_id' => getActiveCompany(),
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

            if ($budget) {
                $systemIncomeForecast = 0.0;
                $systemExpenseForecast = 0.0;
                $forecast = $manualForecast;
                $source = 'manual';
            } else {
                $forecast = $systemForecast;
                $systemIncomeForecast = $type === 'income' ? $systemForecast : 0.0;
                $systemExpenseForecast = $type === 'expense' ? $systemForecast : 0.0;
                $source = 'system';
            }

            // The annual balance determines the bucket, while the monthly balance keeps its accounting sign.
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

    private function monthIsCompleted(int $month): bool
    {
        [$currentYear, $currentMonth] = $this->currentJalaliYearMonth();
        $fiscalYear = $this->activeFiscalYear ??= (int) Company::query()->findOrFail(getActiveCompany())->fiscal_year;

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
            ->where('documents.company_id', getActiveCompany())
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
                ->where('documents.company_id', getActiveCompany())
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

        return $this->monthlyDocumentCounts[$month] = Document::query()->where('company_id', getActiveCompany())->whereNotNull('approved_at')->whereBetween('date', [$startDate, $endDate])->count();
    }

    private function monthDateRange(int $month): array
    {
        $fiscalYear = $this->activeFiscalYear ??= (int) Company::query()->findOrFail(getActiveCompany())->fiscal_year;
        $lastDay = match (true) {
            $month <= 6 => 31,
            $month <= 11 => 30,
            jcheckdate(12, 30, $fiscalYear) => 30,
            default => 29,
        };

        return [
            jalali_to_gregorian($fiscalYear, $month, 1, '-'),
            jalali_to_gregorian($fiscalYear, $month, $lastDay, '-'),
        ];
    }
}
