<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Subject;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CostIncomeService
{
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
     * a subject balance > 0 is income (credit), < 0 is cost (debit).
     */
    public function summary(): array
    {
        $roots = Subject::where('is_permanent', false)->whereIsRoot()->orderBy('code')->get();

        $totalIncome = 0;
        $totalCost = 0;
        $incomeBreakdown = [];
        $costBreakdown = [];

        foreach ($roots as $root) {
            $rootBalance = (int) $this->subjectService->sumSubject($root);

            if ($rootBalance > 0) {
                $totalIncome += $rootBalance;
            } elseif ($rootBalance < 0) {
                $totalCost += abs($rootBalance);
            }

            $children = $root->children;

            if ($children->isNotEmpty()) {
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
     * Monthly income vs cost across the active fiscal year, keyed by Jalali month name.
     */
    public function monthlyIncomeAndCost(): array
    {
        $income = array_fill_keys(self::MONTHS, 0);
        $cost = array_fill_keys(self::MONTHS, 0);

        $nonPermanentSubjects = Subject::where('is_permanent', false)->whereIsRoot()->get();

        foreach ($nonPermanentSubjects as $subject) {
            $monthly = $this->subjectService->sumSubjectWithDateRange($subject);

            foreach (self::MONTHS as $number => $name) {
                $amount = (int) ($monthly[$number] ?? 0);

                if ($amount > 0) {
                    $income[$name] += $amount;
                } elseif ($amount < 0) {
                    $cost[$name] += abs($amount);
                }
            }
        }

        return compact('income', 'cost');
    }

    /**
     * Sales and purchases derived from invoices for the active fiscal year.
     */
    public function invoiceSummary(): array
    {
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

    public function bankAccounts(): Collection
    {
        return Subject::query()->where('parent_id', config('amir.bank'))->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Cash and bank balance history for the requested rolling quarter range.
     */
    public function cashAndBanksBalances(string $type, int $duration): array
    {
        $subjectIds = match ($type) {
            'cash_book' => Subject::where('parent_id', config('amir.cash_book'))->pluck('id')->all(),
            'bank' => Subject::where('parent_id', config('amir.bank'))->pluck('id')->all(),
            'both' => Subject::whereIn('parent_id', [config('amir.bank'), config('amir.cash_book')])->pluck('id')->all(),
        };

        return $this->balanceForSubjectIds($subjectIds, $duration);
    }

    /**
     * Running balance for the selected subjects, constrained to the active fiscal year. Asset balances are inverted for display.
     */
    public function balanceForSubjectIds(array $subjectIds, int $duration, bool $inverse = true): array
    {
        $year = (int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y')));
        $fiscalStart = Carbon::parse(jalali_to_gregorian($year, 1, 1, '/'))->startOfDay();
        $fiscalEnd = Carbon::parse(jalali_to_gregorian($year + 1, 1, 1, '/'))->subDay()->endOfDay();
        $endDate = Carbon::now()->min($fiscalEnd)->max($fiscalStart);
        $startDate = $endDate->copy()->subMonths($duration * 3)->max($fiscalStart);

        $transactionQuery = Transaction::query()->whereIn('subject_id', $subjectIds);
        $initialBalance = (int) (clone $transactionQuery)->whereHas('document', fn ($query) => $query->where('date', '<', $startDate))->sum('value');

        $dailyTransactions = (clone $transactionQuery)
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->whereBetween('documents.date', [$startDate, $endDate])
            ->selectRaw('DATE(documents.date) as date, SUM(transactions.value) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($value) => (int) $value);

        $dailyBalances = [formatDate($startDate) => $initialBalance];
        $runningBalance = $initialBalance;

        foreach ($dailyTransactions as $date => $dailyChange) {
            $runningBalance += $dailyChange;
            $dailyBalances[formatDate($date)] = $runningBalance;
        }

        $dailyBalances[formatDate($endDate)] = $runningBalance;
        $values = array_values($dailyBalances);

        if ($inverse) {
            $values = array_map(fn (int $value) => -$value, $values);
        }

        return [
            'labels' => array_keys($dailyBalances),
            'datas' => $values,
            'sum' => $inverse ? -$runningBalance : $runningBalance,
            'start_date' => jdate('Y/m/d', $startDate->timestamp, tr_num: 'en'),
            'end_date' => jdate('Y/m/d', $endDate->timestamp, tr_num: 'en'),
        ];
    }
}
