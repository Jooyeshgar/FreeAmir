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

    private array $monthlyBalances = [];

    private array $monthlyDocumentCounts = [];

    private ?int $activeFiscalYear = null;

    private ?array $currentJalaliYearMonth = null;

    public function saveForecast(int $month, array $data): MonthlyBudget
    {
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
    }

    public function saveForecastForMonths(array $months, array $data): Collection
    {
        $months = collect($months)->map(fn ($month) => (int) $month)->unique()->sort()->values();
        $unavailableMonths = $months->reject(fn (int $month) => $this->subjectIsAvailableForForecast($month, (int) $data['subject_id'], allowExistingSubject: true));

        if ($unavailableMonths->isNotEmpty()) {
            $monthLabels = $unavailableMonths->map(fn (int $month) => __(self::MONTHS[$month]))->join(config('app.locale') === 'fa' ? '، ' : ', ');

            throw ValidationException::withMessages([
                'subject_id' => __('The selected subject overlaps an existing forecast in: :months.', ['months' => $monthLabels]),
            ]);
        }

        return DB::transaction(fn () => $months->map(fn (int $month) => $this->saveForecast($month, $data)));
    }

    /**
     * Convert the signed amount entered by the user into the stored forecast
     * direction and magnitude, while respecting the subject's normal balance.
     */
    public function normalizeSignedForecast(Subject $subject, int|float|string $signedAmount): array
    {
        $amount = trim((string) $signedAmount);
        $type = str_starts_with($amount, '-') ? 'expense' : 'income';

        if (($subject->type === SubjectType::CREDITOR && $type !== 'income')
            || ($subject->type === SubjectType::DEBTOR && $type !== 'expense')) {
            throw ValidationException::withMessages([
                'forecast_amount' => __('The forecast sign does not match the selected subject type.'),
            ]);
        }

        return [
            'budget_type' => $type,
            'forecast_amount' => ltrim($amount, '-+'),
        ];
    }

    /** The same subject cannot be selected twice; roots and their direct children may coexist. */
    public function subjectSelectionRestrictions(int $month, ?int $exceptSubjectId = null): array
    {
        return $this->buildSubjectSelectionRestrictions($month, $exceptSubjectId);
    }

    private function buildSubjectSelectionRestrictions(int $month, ?int $exceptSubjectId = null): array
    {
        return MonthlyBudget::query()->where('month', $month)
            ->when($exceptSubjectId, fn ($query) => $query->where('subject_id', '!=', $exceptSubjectId))
            ->pluck('subject_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Only roots and their direct children are exposed in the monthly selector.
     */
    public function selectableSubjectsForMonth(int $month, ?array $unavailableSubjectIds = null): Collection
    {
        return $this->selectableSubjects($unavailableSubjectIds ?? $this->subjectSelectionRestrictions($month));
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

    /**
     * Replace the current month's complete manual forecast set with the prior month's set.
     */
    public function rollover(int $month): Collection
    {
        if ($month === 1) {
            throw ValidationException::withMessages([
                'month' => __('The first month has no previous month in the active fiscal year.'),
            ]);
        }

        return DB::transaction(function () use ($month) {
            $previousLines = MonthlyBudget::query()->where('month', $month - 1)->get();

            if ($previousLines->isEmpty()) {
                throw ValidationException::withMessages([
                    'month' => __('No forecast exists for the previous month.'),
                ]);
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
     */
    public function analysis(int $selectedMonth): array
    {
        return $this->buildAnalysis($selectedMonth);
    }

    private function buildAnalysis(int $selectedMonth): array
    {
        $manualBudgets = MonthlyBudget::query()->with('subject:id,code,name,parent_id,type')->where('month', $selectedMonth)
            ->orderBy('budget_type')->orderBy('subject_id')->get();
        $subjects = $this->forecastableSubjects();
        $forecastableSubjectIds = $subjects->pluck('id')->map(fn ($id) => (int) $id);
        $manualBudgets = $manualBudgets->filter(fn (MonthlyBudget $budget) => $forecastableSubjectIds->contains((int) $budget->subject_id))->values();
        $manualBySubject = $manualBudgets->keyBy(fn (MonthlyBudget $budget) => (int) $budget->subject_id);
        $roots = $subjects->filter(fn (Subject $subject) => ! $subject->parent_id);
        $childrenByParent = $subjects->filter(fn (Subject $subject) => (bool) $subject->parent_id)
            ->groupBy(fn (Subject $subject) => (int) $subject->parent_id);
        $lineSubjects = collect();
        $manualChildIdsByRoot = [];

        foreach ($roots as $root) {
            $rootId = (int) $root->id;
            $manualChildren = $childrenByParent->get($rootId, collect())->filter(fn (Subject $child) => $manualBySubject->has((int) $child->id));

            $lineSubjects->push($root, ...$manualChildren);
            $manualChildIdsByRoot[$rootId] = $manualChildren->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $hasDocuments = $this->documentCountForMonth($selectedMonth) > 0;
        $balances = $hasDocuments ? $this->subjectBalancesForMonth($selectedMonth) : collect();
        $allSubjects = $this->allTemporarySubjects();
        $descendantIdsBySubject = $this->descendantIdsBySubject($lineSubjects->pluck('id')->all(), $allSubjects);

        $lines = $lineSubjects->unique('id')->map(function (Subject $subject) use ($selectedMonth, $manualBySubject, $hasDocuments, $balances, $descendantIdsBySubject, $manualChildIdsByRoot) {
            $budget = $manualBySubject->get((int) $subject->id);
            $isChild = (bool) $subject->parent_id;
            $subjectIds = $descendantIdsBySubject[(int) $subject->id] ?? [(int) $subject->id];
            $separatelyForecastSubjectIds = [];

            if (! $isChild && ! $budget) {
                $separatelyForecastSubjectIds = collect($manualChildIdsByRoot[(int) $subject->id] ?? [])
                    ->flatMap(fn (int $childId) => $descendantIdsBySubject[$childId] ?? [$childId])->unique()->values()->all();
                $subjectIds = array_values(array_diff($subjectIds, $separatelyForecastSubjectIds));
            }

            $currentBalance = (float) collect($subjectIds)->sum(fn (int $subjectId) => (float) ($balances[$subjectId] ?? 0));

            if ($budget) {
                $type = $this->manualForecastType($budget);
                $systemForecast = $this->systemForecastForType($subjectIds, $selectedMonth, $type);
                $forecast = (float) $budget->forecast_amount;
                $source = 'manual';
            } else {
                $signedSystemForecast = $this->signedSystemForecast($subjectIds, $selectedMonth);
                $type = $this->forecastTypeForSubject($subject, $signedSystemForecast);
                $systemForecast = abs($signedSystemForecast);
                $forecast = $systemForecast;
                $source = 'system';
            }

            $actual = $hasDocuments ? $this->amountForType($currentBalance, $type) : null;
            $variance = is_null($actual) ? null : ($type === 'expense' ? $forecast - $actual : $actual - $forecast);

            return [
                'budget' => $budget,
                'subject' => $subject,
                'type' => $type,
                'source' => $source,
                'forecast' => $forecast,
                'systemForecast' => $systemForecast,
                'actual' => $actual,
                'variance' => $variance,
                'variancePercent' => is_null($variance) ? null : ($forecast != 0 ? round($variance / $forecast * 100, 2) : 0),
                'isChild' => $isChild,
                'isRemainder' => ! $isChild && ! $budget && $separatelyForecastSubjectIds !== [],
                'includedInSummary' => ! $isChild || ! $manualBySubject->has((int) $subject->parent_id),
            ];
        })->sortBy(fn (array $line) => $line['subject']->code)->values();

        $incomeLines = $lines->where('type', 'income');
        $expenseLines = $lines->where('type', 'expense');
        $forecastIncome = (float) $incomeLines->where('includedInSummary', true)->sum('forecast');
        $forecastExpense = (float) $expenseLines->where('includedInSummary', true)->sum('forecast');
        $actualIncome = $hasDocuments ? (float) $incomeLines->where('includedInSummary', true)->sum('actual') : null;
        $actualExpense = $hasDocuments ? (float) $expenseLines->where('includedInSummary', true)->sum('actual') : null;
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
            'expenseVariance' => is_null($actualExpense) ? null : $forecastExpense - $actualExpense,
            'actualsCalculated' => $hasDocuments,
            'hasDocuments' => $hasDocuments,
            'documentCount' => $this->documentCountForMonth($selectedMonth),
            'hasOverlappingForecasts' => $hasOverlappingForecasts,
            'hasPreviousForecast' => $selectedMonth > 1 && MonthlyBudget::query()->where('month', $selectedMonth - 1)->exists(),
        ];
    }

    public function fullYearAnalysis(): array
    {
        return $this->buildFullYearAnalysis();
    }

    private function buildFullYearAnalysis(): array
    {
        $monthly = collect(array_keys(self::MONTHS))->mapWithKeys(fn (int $month) => [
            $month => $this->analysis($month),
        ]);

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
        return collect(self::MONTHS)->mapWithKeys(fn (string $key, int $month) => [$month => __($key)])->all();
    }

    private function forecastableSubjects(): Collection
    {
        $subjects = $this->allTemporarySubjects();
        $rootIds = $subjects->filter(fn (Subject $subject) => ! $subject->parent_id)->pluck('id')->map(fn ($id) => (int) $id);

        return $subjects->filter(fn (Subject $subject) => ! $subject->parent_id || $rootIds->contains((int) $subject->parent_id))->values();
    }

    private function allTemporarySubjects(): Collection
    {
        return $this->temporarySubjects ??= Subject::query()->where('is_permanent', false)->orderBy('code')->get(['id', 'code', 'name', 'parent_id', 'type']);
    }

    private function forecastTypeForSubject(Subject $subject, float $signedAverage): string
    {
        return match ($subject->type) {
            SubjectType::CREDITOR => 'income',
            SubjectType::DEBTOR => 'expense',
            SubjectType::BOTH => $signedAverage < 0 ? 'expense' : 'income',
        };
    }

    private function manualForecastType(MonthlyBudget $budget): string
    {
        return match ($budget->subject->type) {
            SubjectType::CREDITOR => 'income',
            SubjectType::DEBTOR => 'expense',
            SubjectType::BOTH => $budget->budget_type,
        };
    }

    /**
     * Completed months use their own actual balance. For an open/future month,
     * prior completed actuals are averaged over the number of completed months.
     */
    private function signedSystemForecast(array $subjectIds, int $selectedMonth): float
    {
        if ($this->monthIsCompleted($selectedMonth)) {
            return $this->signedBalance($subjectIds, $selectedMonth);
        }

        $completedMonths = $this->completedMonthsBefore($selectedMonth);
        if ($completedMonths->isEmpty()) {
            return 0.0;
        }

        return (float) $completedMonths->avg(fn (int $month) => $this->signedBalance($subjectIds, $month));
    }

    private function systemForecastForType(array $subjectIds, int $selectedMonth, string $type): float
    {
        if ($this->monthIsCompleted($selectedMonth)) {
            return $this->amountForType($this->signedBalance($subjectIds, $selectedMonth), $type);
        }

        $completedMonths = $this->completedMonthsBefore($selectedMonth);
        if ($completedMonths->isEmpty()) {
            return 0.0;
        }

        return (float) $completedMonths->avg(
            fn (int $month) => $this->amountForType($this->signedBalance($subjectIds, $month), $type)
        );
    }

    private function completedMonthsBefore(int $selectedMonth): Collection
    {
        if ($selectedMonth <= 1) {
            return collect();
        }

        return collect(range(1, $selectedMonth - 1))->filter(fn (int $month) => $this->monthIsCompleted($month));
    }

    private function monthIsCompleted(int $month): bool
    {
        [$currentYear, $currentMonth] = $this->currentJalaliYearMonth();
        $fiscalYear = $this->activeFiscalYear();

        return $fiscalYear < $currentYear || ($fiscalYear === $currentYear && $month < $currentMonth);
    }

    private function currentJalaliYearMonth(): array
    {
        if ($this->currentJalaliYearMonth !== null) {
            return $this->currentJalaliYearMonth;
        }

        $timestamp = now()->timestamp;

        return $this->currentJalaliYearMonth = [
            (int) jdate('Y', $timestamp, '', 'Asia/Tehran', 'en'),
            (int) jdate('n', $timestamp, '', 'Asia/Tehran', 'en'),
        ];
    }

    private function activeFiscalYear(): int
    {
        return $this->activeFiscalYear ??= (int) Company::query()->findOrFail(getActiveCompany())->fiscal_year;
    }

    private function signedBalance(array $subjectIds, int $month): float
    {
        $balances = $this->subjectBalancesForMonth($month);

        return (float) collect($subjectIds)->sum(fn (int $subjectId) => (float) ($balances[$subjectId] ?? 0));
    }

    private function amountForType(float $balance, string $type): float
    {
        return $type === 'expense' ? abs(min($balance, 0)) : max($balance, 0);
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

    private function subjectBalancesForMonth(int $month): Collection
    {
        if (isset($this->monthlyBalances[$month])) {
            return $this->monthlyBalances[$month];
        }

        $subjectIds = $this->allTemporarySubjects()->pluck('id');
        if ($subjectIds->isEmpty()) {
            return $this->monthlyBalances[$month] = collect();
        }

        [$startDate, $endDate] = $this->monthDateRange($month);

        return $this->monthlyBalances[$month] = Transaction::query()
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('documents.company_id', getActiveCompany())
            ->whereNotNull('documents.approved_at')
            ->whereBetween('documents.date', [$startDate, $endDate])
            ->whereIn('transactions.subject_id', $subjectIds)
            ->selectRaw('transactions.subject_id, SUM(transactions.value) as balance')
            ->groupBy('transactions.subject_id')
            ->pluck('balance', 'subject_id');
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
        $fiscalYear = $this->activeFiscalYear();
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
