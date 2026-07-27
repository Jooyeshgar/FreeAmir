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

    /**
     * Forecasted subjects, their descendants, and their ancestors cannot be
     * forecast again in the same month because that would double count totals.
     */
    public function subjectSelectionRestrictions(int $month): array
    {
        return $this->buildSubjectSelectionRestrictions($month);
    }

    private function buildSubjectSelectionRestrictions(int $month): array
    {
        $forecastSubjectIds = MonthlyBudget::query()->where('month', $month)->pluck('subject_id')->map(fn ($id) => (int) $id)->all();

        if (empty($forecastSubjectIds)) {
            return [];
        }

        $subjects = Subject::query()->get(['id', 'parent_id']);
        $parentBySubject = $subjects->mapWithKeys(fn (Subject $subject) => [(int) $subject->id => $subject->parent_id ? (int) $subject->parent_id : null]);
        $childrenByParent = $subjects->groupBy(fn (Subject $subject) => (int) ($subject->parent_id ?? 0))
            ->map(fn (Collection $children) => $children->pluck('id')->map(fn ($id) => (int) $id)->all());
        $unavailable = [];

        foreach ($forecastSubjectIds as $subjectId) {
            $stack = [$subjectId];

            while ($stack !== []) {
                $currentId = array_pop($stack);

                if (isset($unavailable[$currentId])) {
                    continue;
                }

                $unavailable[$currentId] = true;
                array_push($stack, ...$childrenByParent->get($currentId, []));
            }

            $parentId = $parentBySubject->get($subjectId);
            $visitedParents = [];
            while ($parentId && ! isset($visitedParents[$parentId])) {
                $visitedParents[$parentId] = true;
                $unavailable[$parentId] = true;
                $parentId = $parentBySubject->get($parentId);
            }
        }

        return array_map('intval', array_keys($unavailable));
    }

    /**
     * Only roots and their direct children are exposed in the monthly selector.
     */
    public function selectableSubjectsForMonth(int $month, ?array $unavailableSubjectIds = null): Collection
    {
        $subjects = $this->forecastableSubjects();
        $subjectsById = $subjects->keyBy(fn (Subject $subject) => (int) $subject->id);
        $unavailable = array_fill_keys($unavailableSubjectIds ?? $this->subjectSelectionRestrictions($month), true);

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

    public function subjectIsAvailableForForecast(int $month, int $subjectId): bool
    {
        return in_array($subjectId, $this->forecastableSubjectIds(), true)
            && ! in_array($subjectId, $this->subjectSelectionRestrictions($month), true);
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
     * Build manual and system-generated subject forecasts for one Jalali month.
     * A manual line wins; uncovered first-level branches use prior documented
     * months' actual average.
     */
    public function analysis(int $selectedMonth): array
    {
        return $this->buildAnalysis($selectedMonth);
    }

    private function buildAnalysis(int $selectedMonth): array
    {
        $manualBudgets = MonthlyBudget::query()->with('subject:id,code,name,parent_id,type')->where('month', $selectedMonth)
            ->orderBy('budget_type')->orderBy('subject_id')->get();
        $manualBySubject = $manualBudgets->keyBy(fn (MonthlyBudget $budget) => (int) $budget->subject_id);
        $subjects = $this->forecastableSubjects();
        $roots = $subjects->filter(fn (Subject $subject) => ! $subject->parent_id);
        $childrenByParent = $subjects->filter(fn (Subject $subject) => (bool) $subject->parent_id)
            ->groupBy(fn (Subject $subject) => (int) $subject->parent_id);
        $lineSubjects = collect();
        $directOnlySubjectIds = [];

        foreach ($roots as $root) {
            $rootBudget = $manualBySubject->get((int) $root->id);

            if ($rootBudget) {
                $lineSubjects->push($root);

                continue;
            }

            $children = $childrenByParent->get((int) $root->id, collect());
            if ($children->isEmpty()) {
                $lineSubjects->push($root);
            } else {
                // Keep direct postings on the root as their own system item; child
                // rows cover their respective subtrees without double counting.
                $lineSubjects->push($root);
                $directOnlySubjectIds[] = (int) $root->id;
                $lineSubjects->push(...$children);
            }
        }

        // Keep legacy/manual rows visible even if they are deeper than the new selector limit.
        foreach ($manualBudgets as $budget) {
            if ($budget->subject && ! $lineSubjects->contains('id', $budget->subject_id)) {
                $lineSubjects->push($budget->subject);
            }
        }

        $hasDocuments = $this->documentCountForMonth($selectedMonth) > 0;
        $balances = $hasDocuments ? $this->subjectBalancesForMonth($selectedMonth) : collect();
        $allSubjects = $this->allTemporarySubjects();
        $descendantIdsBySubject = $this->descendantIdsBySubject($lineSubjects->pluck('id')->all(), $allSubjects);

        $lines = $lineSubjects->unique('id')->map(function (Subject $subject) use ($selectedMonth, $manualBySubject, $manualBudgets, $hasDocuments, $balances, $descendantIdsBySubject, $directOnlySubjectIds) {
            $budget = $manualBySubject->get((int) $subject->id);
            $subjectIds = ! $budget && in_array((int) $subject->id, $directOnlySubjectIds, true)
                ? [(int) $subject->id]
                : ($descendantIdsBySubject[(int) $subject->id] ?? [(int) $subject->id]);
            $currentBalance = (float) collect($subjectIds)->sum(fn (int $subjectId) => (float) ($balances[$subjectId] ?? 0));

            if ($budget) {
                $type = $this->manualForecastType($budget);
                $systemForecast = $this->averageActual($subjectIds, $selectedMonth, $type);
                $forecast = (float) $budget->forecast_amount;
                $source = 'manual';
            } else {
                $signedAverage = $this->averageSignedActual($subjectIds, $selectedMonth);
                $type = $this->forecastTypeForSubject($subject, $signedAverage);
                $systemForecast = abs($signedAverage);
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
                'includedInSummary' => ! $budget || ! $manualBudgets->contains(function (MonthlyBudget $candidate) use ($budget, $descendantIdsBySubject) {
                    return $candidate->id !== $budget->id
                        && $this->manualForecastType($candidate) === $this->manualForecastType($budget)
                        && in_array((int) $budget->subject_id, $descendantIdsBySubject[(int) $candidate->subject_id] ?? [], true);
                }),
            ];
        })->sortBy([
            fn (array $line) => $line['type'] === 'income' ? 0 : 1,
            fn (array $line) => $line['subject']->code,
        ])->values();

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

    private function averageSignedActual(array $subjectIds, int $selectedMonth): float
    {
        $documentedMonths = $this->previousDocumentedMonths($selectedMonth);

        if ($documentedMonths->isEmpty()) {
            return 0.0;
        }

        return (float) $documentedMonths->avg(fn (int $month) => $this->signedBalance($subjectIds, $month));
    }

    private function averageActual(array $subjectIds, int $selectedMonth, string $type): float
    {
        $documentedMonths = $this->previousDocumentedMonths($selectedMonth);

        if ($documentedMonths->isEmpty()) {
            return 0.0;
        }

        return (float) $documentedMonths->avg(fn (int $month) => $this->amountForType($this->signedBalance($subjectIds, $month), $type));
    }

    private function previousDocumentedMonths(int $selectedMonth): Collection
    {
        if ($selectedMonth <= 1) {
            return collect();
        }

        return collect(range(1, $selectedMonth - 1))->filter(fn (int $month) => $this->documentCountForMonth($month) > 0);
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

        return $this->monthlyDocumentCounts[$month] = Document::query()->where('company_id', getActiveCompany())->whereBetween('date', [$startDate, $endDate])->count();
    }

    private function monthDateRange(int $month): array
    {
        $fiscalYear = (int) Company::query()->findOrFail(getActiveCompany())->fiscal_year;
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
