<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Subject;
use App\Models\Transaction;
use Carbon\Carbon;

class HomeService
{
    public function __construct(private readonly SubjectService $subjectService) {}

    public function incomeData()
    {
        $incomeSubjectIds = [
            'service_revenue' => config('amir.service_revenue'),
            'sales_revenue' => config('amir.sales_revenue'),
        ];

        $incomes = [];
        foreach ($incomeSubjectIds as $index => $incomeSubjectId) {
            $subject = Subject::find($incomeSubjectId);
            $incomes[$index] = $this->subjectService->sumSubject($subject);
        }

        return [$incomes['service_revenue'], $incomes['sales_revenue']];
    }

    public function costsData()
    {
        $wagesCostSubject = Subject::find(config('amir.wage'));
        $wagesCost = 0;
        if (! is_null($wagesCostSubject)) {
            $wagesCost = $this->subjectService->sumSubject($wagesCostSubject);
        }

        $productCogSubjectIds = Product::pluck('cogs_subject_id')->all();
        $productsCogCost = 0;
        foreach ($productCogSubjectIds as $productCogSubjectId) {
            $productSubject = Subject::find($productCogSubjectId);
            $productsCogCost += $this->subjectService->sumSubject($productSubject);
        }

        return [$wagesCost, $productsCogCost];
    }

    public function cashAndBanksBalances(string $type, int $duration)
    {
        if ($type === 'cash_book') {
            $subjectIds = Subject::where('parent_id', config('amir.cash_book'))->pluck('id')->all();
        } elseif ($type === 'bank') {
            $subjectIds = Subject::where('parent_id', config('amir.bank'))->pluck('id')->all();
        } elseif ($type === 'both') {
            $bankAccountSubjectIds = Subject::where('parent_id', config('amir.bank'))->pluck('id')->all();
            $cashBookSubjectIds = Subject::where('parent_id', config('amir.cash_book'))->pluck('id')->all();

            $subjectIds = array_merge($bankAccountSubjectIds, $cashBookSubjectIds);
        } else {
            return response()->json([]);
        }

        return $this->balanceForSubjectIds($subjectIds, $duration);
    }

    public function getMonthlyCost()
    {
        $subject = Subject::find(config('amir.cost'));

        return $this->mapMonths($this->subjectService->sumSubjectWithDateRange($subject));
    }

    public function getMonthlyIncome()
    {
        $incomeSubjectIds = [
            'service_revenue' => config('amir.service_revenue'),
            'sales_revenue' => config('amir.sales_revenue'),
            'income' => config('amir.income'), // other_icnome = income - (service_revenue + sales_revenue)
        ];

        $monthlyIncomes = [];
        foreach ($incomeSubjectIds as $index => $incomeSubjectId) {
            $subject = Subject::find($incomeSubjectId);
            $monthlyIncomes[$index] = $this->mapMonths($this->subjectService->sumSubjectWithDateRange($subject));
        }

        $other_income = [];
        foreach ($monthlyIncomes['income'] as $month => $total) {
            $other_income[$month] = $total - ($monthlyIncomes['service_revenue'][$month] + $monthlyIncomes['sales_revenue'][$month]);
        }

        foreach ($monthlyIncomes['income'] as $month => $total) {
            $monthlyIncomes['total'][$month] = $monthlyIncomes['service_revenue'][$month] + $monthlyIncomes['sales_revenue'][$month] + $other_income[$month];
        }

        return $monthlyIncomes['total'];
    }

    public function getMonthlyProductsStat($countOnly = false)
    {
        $productInventorySubjectIds = Product::pluck('inventory_subject_id')->all();
        $monthlyProductsStat = [];

        foreach ($productInventorySubjectIds as $productInventorySubjectId) {
            $productSubject = Subject::find($productInventorySubjectId);
            $monthlyProductsStat[] = $this->subjectService->sumSubjectWithDateRange($productSubject, $countOnly);
        }

        $productsStat = [];
        foreach ($monthlyProductsStat as $monthlyProductStat) {
            if (empty($monthlyProductStat)) {
                continue;
            }

            foreach ($monthlyProductStat as $month => $stat) {
                if (! isset($productsStat[$month])) {
                    $productsStat[$month] = 0;
                }

                $productsStat[$month] += ($productsStat[$month] ?? 0) + $stat;
            }
        }

        return $this->mapMonths($productsStat);
    }

    public function balanceForSubjectIds(array $subjectIds, int $duration, bool $inverse = true)
    {
        $year = session('active-company-fiscal-year');

        $endDate = now();
        $lastDayOfFiscalYear = Carbon::parse(jalali_to_gregorian($year, 12, 29, '/'));
        if ($endDate->isAfter($lastDayOfFiscalYear)) {
            $endDate = $lastDayOfFiscalYear;
        }

        $transactionQuery = Transaction::query()->whereIn('subject_id', $subjectIds);

        $startDate = (clone $endDate)->subMonths($duration * 3);

        $firstDayOfFiscalYear = Carbon::parse(jalali_to_gregorian($year, 1, 1, '/'));

        if ($startDate->isBefore($firstDayOfFiscalYear)) {
            $startDate = $firstDayOfFiscalYear;
        }

        $initialBalance = (int) (clone $transactionQuery)
            ->whereHas('document', fn ($q) => $q->where('date', '<=', $startDate))
            ->sum('value');

        $dailyTransactions = (clone $transactionQuery)
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->whereBetween('documents.date', [$startDate, $endDate])
            ->selectRaw('DATE(documents.date) as date, SUM(transactions.value) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date')
            ->map(fn ($v) => (int) $v);

        $dailyBalances = [formatDate($startDate) => $initialBalance];
        $runningBalance = $initialBalance;

        foreach ($dailyTransactions as $date => $dailyChange) {
            $runningBalance += $dailyChange;
            $dailyBalances[(string) $date] = $runningBalance;
        }

        $dailyBalances[formatDate($endDate)] = $runningBalance;
        $values = array_values($dailyBalances);

        if ($inverse) {
            $values = array_map(fn ($value) => $value * -1, $values);
        }

        return response()->json([
            'labels' => array_keys($dailyBalances),
            'datas' => $values,
            'sum' => end($dailyBalances) ? end($dailyBalances) : $initialBalance,
            'start_date' => jdate('Y/m/d', $startDate->timestamp, tr_num: 'en'),
            'end_date' => jdate('Y/m/d', $endDate->timestamp, tr_num: 'en'),
        ]);
    }

    public function popularProductsAndServices()
    {
        return InvoiceItem::whereHas('invoice', fn ($q) => $q->where('invoice_type', InvoiceType::SELL)
            ->where('status', InvoiceStatus::APPROVED)
        )->with('itemable')
            ->selectRaw('itemable_type, itemable_id, SUM(quantity) as total_quantity')
            ->groupBy('itemable_type', 'itemable_id')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->itemable_id,
                'name' => $item->itemable->name ?? 'unknown',
                'code' => $item->itemable->code ?? '-',
                'selling_price' => $item->itemable->selling_price ?? null,
                'average_cost' => $item->itemable->average_cost ?? null,
                'quantity' => (int) $item->total_quantity,
                'type' => $item->itemable_type === Product::class ? 'products' : 'services',
            ]);
    }

    public function topTenBanksAccountBalances()
    {
        $bankAccounts = Subject::where('parent_id', config('amir.bank'))->get();

        $bankAccountBalances = Transaction::query()
            ->whereIn('subject_id', $bankAccounts->pluck('id'))
            ->selectRaw('subject_id, SUM(value) as balance')
            ->groupBy('subject_id')
            ->pluck('balance', 'subject_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        foreach ($bankAccounts as $bankAccount) {
            $bankAccountBalances[$bankAccount->id] = $bankAccountBalances[$bankAccount->id] ?? 0;
        }

        arsort($bankAccountBalances);

        return [$bankAccounts, array_slice($bankAccountBalances, 0, 10, true)];
    }

    private function mapMonths(array $data): array
    {
        $months = [
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

        $result = [];

        foreach ($months as $number => $name) {
            $result[$name] = (int) abs($data[$number] ?? 0);
        }

        return $result;
    }
}
