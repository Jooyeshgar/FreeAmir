<?php

namespace App\Services;

use App\Models\Company;
use App\Models\MonthlyBudget;
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
     * Replace the current month's complete forecast line set with the prior month's set.
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
     * Build subject-level actuals and favorable variances for one Jalali month.
     */
    public function analysis(int $selectedMonth, bool $calculateExpenses = false): array
    {
        $budgetLines = MonthlyBudget::query()->with('subject:id,code,name')->where('month', $selectedMonth)->orderBy('budget_type')->orderBy('subject_id')->get();

        $balances = $this->subjectBalancesForMonth($selectedMonth, $budgetLines->pluck('subject_id')->all());

        $lines = $budgetLines->map(function (MonthlyBudget $budget) use ($balances, $calculateExpenses) {
            $balance = (float) ($balances[$budget->subject_id] ?? 0);
            $forecast = (float) $budget->forecast_amount;
            $isExpense = $budget->budget_type === 'expense';
            $actual = $isExpense ? ($calculateExpenses ? abs(min($balance, 0)) : null) : max($balance, 0);
            $variance = is_null($actual) ? null : ($isExpense ? $forecast - $actual : $actual - $forecast);

            return [
                'budget' => $budget,
                'subject' => $budget->subject,
                'type' => $budget->budget_type,
                'forecast' => $forecast,
                'actual' => $actual,
                'variance' => $variance,
                'variancePercent' => is_null($variance) ? null : round($variance / $forecast * 100, 2),
            ];
        });

        $incomeLines = $lines->where('type', 'income');
        $expenseLines = $lines->where('type', 'expense');
        $forecastIncome = (float) $incomeLines->sum('forecast');
        $forecastExpense = (float) $expenseLines->sum('forecast');
        $actualIncome = (float) $incomeLines->sum('actual');
        $actualExpense = $calculateExpenses ? (float) $expenseLines->sum('actual') : null;

        return [
            'months' => collect(self::MONTHS)->mapWithKeys(fn (string $translationKey, int $month) => [$month => __($translationKey)])->all(),
            'selectedMonth' => $selectedMonth,
            'selectedMonthLabel' => __(self::MONTHS[$selectedMonth]),
            'budgetLines' => $lines,
            'forecastIncome' => $forecastIncome,
            'forecastExpense' => $forecastExpense,
            'actualIncome' => $actualIncome,
            'actualExpense' => $actualExpense,
            'incomeVariance' => $actualIncome - $forecastIncome,
            'expenseVariance' => is_null($actualExpense) ? null : $forecastExpense - $actualExpense,
            'expensesCalculated' => $calculateExpenses,
            'hasPreviousForecast' => $selectedMonth > 1 && MonthlyBudget::query()->where('month', $selectedMonth - 1)->exists(),
        ];
    }

    /**
     * Sum all transaction values on the requested subjects from documents in the selected month.
     */
    private function subjectBalancesForMonth(int $month, array $subjectIds): Collection
    {
        if (empty($subjectIds)) {
            return collect();
        }

        [$startDate, $endDate] = $this->monthDateRange($month);

        return Transaction::query()->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->where('documents.company_id', getActiveCompany())
            ->whereBetween('documents.date', [$startDate, $endDate])
            ->whereIn('transactions.subject_id', $subjectIds)
            ->selectRaw('transactions.subject_id, SUM(transactions.value) as balance')
            ->groupBy('transactions.subject_id')
            ->get()
            ->pluck('balance', 'subject_id');
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
