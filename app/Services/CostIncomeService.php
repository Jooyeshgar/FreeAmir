<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Subject;
use App\Models\Transaction;

class CostIncomeService
{
    /**
     * Jalali month names indexed 1..12, used to label monthly series.
     */
    private const MONTHS = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    public function __construct(private readonly SubjectService $subjectService) {}

    /**
     * Headline figures and per-subject breakdown driven by non-permanent
     * (temporary / nominal) subjects for the active fiscal year.
     *
     * Sign convention (see App\Models\Transaction): a subject balance > 0 is
     * income (credit), < 0 is cost (debit).
     *
     * @return array{
     *     totalIncome: int,
     *     totalCost: int,
     *     profit: int,
     *     margin: int,
     *     incomeBreakdown: array<string, int>,
     *     costBreakdown: array<string, int>,
     * }
     */
    public function summary(): array
    {
        // FiscalYearScope (global scope) keeps this to the active fiscal year.
        $nonPermanentSubjects = Subject::where('is_permanent', false)->whereIsRoot()->get();

        $totalIncome = 0;
        $totalCost = 0;
        $incomeBreakdown = [];
        $costBreakdown = [];

        /** @var Subject $subject */
        foreach ($nonPermanentSubjects as $subject) {
            $balance = (int) $this->subjectService->sumSubject($subject);

            if ($balance === 0) {
                continue;
            }

            if ($balance > 0) {
                $totalIncome += $balance;
                $incomeBreakdown[$subject->name] = ($incomeBreakdown[$subject->name] ?? 0) + $balance;
            } else {
                $cost = abs($balance);
                $totalCost += $cost;
                $costBreakdown[$subject->name] = ($costBreakdown[$subject->name] ?? 0) + $cost;
            }
        }

        arsort($incomeBreakdown);
        arsort($costBreakdown);

        $profit = $totalIncome - $totalCost;
        $margin = $totalIncome > 0 ? (int) round($profit / $totalIncome * 100) : 0;

        return compact('totalIncome', 'totalCost', 'profit', 'margin', 'incomeBreakdown', 'costBreakdown');
    }

    /**
     * Monthly income vs cost across the active fiscal year, keyed by Jalali
     * month name. Each non-permanent root subject is classified as income or
     * cost by its overall balance sign, then its monthly amounts are bucketed.
     *
     * @return array{income: array<string, int>, cost: array<string, int>}
     */
    public function monthlyIncomeAndCost(): array
    {
        $income = array_fill_keys(self::MONTHS, 0);
        $cost = array_fill_keys(self::MONTHS, 0);

        $nonPermanentSubjects = Subject::where('is_permanent', false)->whereIsRoot()->get();

        /** @var Subject $subject */
        foreach ($nonPermanentSubjects as $subject) {
            $balance = (int) $this->subjectService->sumSubject($subject);

            if ($balance === 0) {
                continue;
            }

            $monthly = $this->subjectService->sumSubjectWithDateRange($subject);
            $bucket = $balance > 0 ? 'income' : 'cost';

            foreach (self::MONTHS as $number => $name) {
                $amount = (int) abs($monthly[$number] ?? 0);

                if ($bucket === 'income') {
                    $income[$name] += $amount;
                } else {
                    $cost[$name] += $amount;
                }
            }
        }

        return compact('income', 'cost');
    }

    /**
     * Top customers ranked by their subject balance.
     *
     * Sign convention: a customer subject balance < 0 means the customer owes
     * the business (debtor / receivable); > 0 means the business owes the
     * customer (creditor / payable). Returned amounts are absolute.
     *
     * @return array{
     *     debtors: array<int, array{subject_id: int, name: string, amount: int}>,
     *     creditors: array<int, array{subject_id: int, name: string, amount: int}>,
     * }
     */
    public function topCustomers(int $limit = 10): array
    {
        $customerSubjects = Subject::where('subjectable_type', Customer::class)->get(['id', 'name']);

        if ($customerSubjects->isEmpty()) {
            return ['debtors' => [], 'creditors' => []];
        }

        $names = $customerSubjects->pluck('name', 'id');

        $balances = Transaction::query()
            ->whereIn('subject_id', $customerSubjects->pluck('id'))
            ->selectRaw('subject_id, SUM(value) as balance')
            ->groupBy('subject_id')
            ->pluck('balance', 'subject_id')
            ->map(fn ($v) => (int) $v);

        $debtors = [];
        $creditors = [];

        foreach ($balances as $subjectId => $balance) {
            if ($balance === 0) {
                continue;
            }

            $row = [
                'subject_id' => (int) $subjectId,
                'name' => $names[$subjectId] ?? '-',
                'amount' => abs($balance),
            ];

            if ($balance < 0) {
                $debtors[] = $row;
            } else {
                $creditors[] = $row;
            }
        }

        usort($debtors, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        usort($creditors, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'debtors' => array_slice($debtors, 0, $limit),
            'creditors' => array_slice($creditors, 0, $limit),
        ];
    }
}
