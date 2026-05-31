<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Invoice;
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
     * Headline figures plus a per-subject breakdown for the active fiscal year.
     *
     * Sign convention (see App\Models\Transaction): a subject balance > 0 is
     * income (credit), < 0 is cost (debit).
     *
     * Totals are taken from the non-permanent root (1st level) subjects so the
     * net figures stay accurate, while the breakdown drills one level down: when
     * a root has children, each child (2nd level) becomes its own slice so the
     * chart is meaningful even when income/cost sits under a single parent. Roots
     * without children fall back to charting the root itself.
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
        $roots = Subject::where('is_permanent', false)->whereIsRoot()->orderBy('code')->get();

        $totalIncome = 0;
        $totalCost = 0;
        $incomeBreakdown = [];
        $costBreakdown = [];

        /** @var Subject $root */
        foreach ($roots as $root) {
            $rootBalance = (int) $this->subjectService->sumSubject($root);

            if ($rootBalance > 0) {
                $totalIncome += $rootBalance;
            } elseif ($rootBalance < 0) {
                $totalCost += abs($rootBalance);
            }

            $children = $root->children;

            if ($children->isNotEmpty()) {
                /** @var Subject $child */
                foreach ($children as $child) {
                    $balance = (int) $this->subjectService->sumSubject($child);
                    $this->placeBreakdown($balance, $child->name, $incomeBreakdown, $costBreakdown);
                }
            } else {
                $this->placeBreakdown($rootBalance, $root->name, $incomeBreakdown, $costBreakdown);
            }
        }

        arsort($incomeBreakdown);
        arsort($costBreakdown);

        $profit = $totalIncome - $totalCost;
        $margin = $totalIncome > 0 ? (int) round($profit / $totalIncome * 100) : 0;

        return compact('totalIncome', 'totalCost', 'profit', 'margin', 'incomeBreakdown', 'costBreakdown');
    }

    /**
     * Route a signed balance into the income or cost breakdown bucket by sign.
     *
     * @param  array<string, int>  $income
     * @param  array<string, int>  $cost
     */
    private function placeBreakdown(int $balance, string $name, array &$income, array &$cost): void
    {
        if ($balance > 0) {
            $income[$name] = ($income[$name] ?? 0) + $balance;
        } elseif ($balance < 0) {
            $cost[$name] = ($cost[$name] ?? 0) + abs($balance);
        }
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
     * Sales and purchases derived from invoices for the active fiscal year.
     *
     * Net figures subtract returns; void invoices are excluded. The trading
     * margin is the gross result of buying and selling goods (net sales minus
     * net purchases) and complements the ledger-driven profit figure.
     *
     * @return array{
     *     netSales: int,
     *     netPurchases: int,
     *     tradingMargin: int,
     *     tradingMarginPercent: int,
     *     sellCount: int,
     *     buyCount: int,
     * }
     */
    public function invoiceSummary(): array
    {
        // FiscalYearScope (global scope) keeps every query to the active fiscal year.
        $sell = (int) Invoice::where('invoice_type', InvoiceType::SELL)->sum('amount');
        $returnSell = (int) Invoice::where('invoice_type', InvoiceType::RETURN_SELL)->sum('amount');
        $buy = (int) Invoice::where('invoice_type', InvoiceType::BUY)->sum('amount');
        $returnBuy = (int) Invoice::where('invoice_type', InvoiceType::RETURN_BUY)->sum('amount');

        $sellCount = (int) Invoice::where('invoice_type', InvoiceType::SELL)->count();
        $buyCount = (int) Invoice::where('invoice_type', InvoiceType::BUY)->count();

        $netSales = $sell - $returnSell;
        $netPurchases = $buy - $returnBuy;
        $tradingMargin = $netSales - $netPurchases;
        $tradingMarginPercent = $netSales > 0 ? (int) round($tradingMargin / $netSales * 100) : 0;

        return compact('netSales', 'netPurchases', 'tradingMargin', 'tradingMarginPercent', 'sellCount', 'buyCount');
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
