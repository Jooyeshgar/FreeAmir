<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Document;
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
            'sales_revenue' => config(key: 'amir.sales_revenue'),
            'income' => config('amir.income'), // other_icnome = income - (service_revenue + sales_revenue)
        ];

        $incomes = [];
        foreach ($incomeSubjectIds as $subject => $incomeSubjectId) {
            $incomes[$subject] = $this->subjectService->sumSubject(Subject::find($incomeSubjectId));
        }

        $incomes['other_income'] = $incomes['income'] - ($incomes['service_revenue'] + $incomes['sales_revenue']);

        return [$incomes['income'], $incomes['service_revenue'], $incomes['sales_revenue'], $incomes['other_income']];
    }

    public function costData()
    {
        $costSubjects = Subject::where('parent_id', config('amir.cost'))->select('id', 'name')->get();

        $costs = [];
        foreach ($costSubjects as $costSubject) {
            $costs[$costSubject->name] = $this->subjectService->sumSubject(Subject::find($costSubject->id));
        }

        $totalCosts = collect($costs)->sum();

        $wagesCostSubject = Subject::where('id', config('amir.wage'))->get();
        $wagesCost = $this->subjectService->sumSubject($wagesCostSubject);

        $cogProductSubjectIds = Product::pluck('cogs_subject_id')->all();
        $cogProductsCost = 0;
        foreach ($cogProductSubjectIds as $cogProductSubjectId) {
            $cogProductsCost += $this->subjectService->sumSubject(Subject::find($cogProductSubjectId));
        }

        $otherCost = $totalCosts - ($wagesCost + $cogProductsCost);

        return [$totalCosts, $wagesCost, $cogProductsCost, $otherCost];
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

            $subjectIds = array_values(array_unique(array_merge($bankAccountSubjectIds, $cashBookSubjectIds)));
        } else {
            return response()->json([]);
        }

        return $this->balanceForSubjectIds($subjectIds, $duration);
    }

    private function getMonthlyCost(array $months, int $year)
    {
        return $this->mapMonths($this->subjectService->sumSubjectWithDateRange(Subject::find(config('amir.cost')), $year, $months));
    }

    private function getMonthlyIncome(array $months, int $year)
    {
        $incomeSubjectIds = [
            'service_revenue' => config('amir.service_revenue'),
            'sales_revenue' => config(key: 'amir.sales_revenue'),
            'income' => config('amir.income'), // other_icnome = income - (service_revenue + sales_revenue)
        ];

        $incomes = [];
        foreach ($incomeSubjectIds as $subject => $incomeSubjectId) {
            $incomes[$subject] = $this->mapMonths($this->subjectService->sumSubjectWithDateRange(Subject::find($incomeSubjectId), $year, $months));
        }

        $other_income = [];
        foreach ($incomes['income'] as $month => $total) {
            $other_income[$month] = $total - ($incomes['service_revenue'][$month] + $incomes['sales_revenue'][$month]);
        }

        foreach ($incomes['income'] as $month => $total) {
            $incomes['total'][$month] = $incomes['service_revenue'][$month] + $incomes['sales_revenue'][$month] + $other_income[$month];
        }

        return $incomes['total'];
    }

    private function getMonthlyProductstat(array $months, int $year, $countOnly = false)
    {
        $productInventorySubjectIds = Product::pluck('inventory_subject_id')->all();
        $monthlySellAmountPerProducts = [];

        foreach ($productInventorySubjectIds as $productInventorySubjectId) {
            $monthlySellAmountPerProducts[] = $this->subjectService->sumSubjectWithDateRange(Subject::find($productInventorySubjectId), $year, $months, $countOnly);
        }

        $result = [];
        foreach ($monthlySellAmountPerProducts as $monthlySellAmountPerProduct) {
            if (empty($monthlySellAmountPerProduct)) {
                continue;
            }

            foreach ($monthlySellAmountPerProduct as $month => $total) {
                if (! isset($result[$month])) {
                    $result[$month] = 0;
                }

                $result[$month] += ($result[$month] ?? 0) + $total;
            }
        }

        return $this->mapMonths($result);
    }

    public function monthlyData()
    {
        $months = [
            1 => [1, 31],
            2 => [1, 31],
            3 => [1, 31],
            4 => [1, 31],
            5 => [1, 31],
            6 => [1, 31],
            7 => [1, 30],
            8 => [1, 30],
            9 => [1, 30],
            10 => [1, 30],
            11 => [1, 30],
            12 => [1, 29],
        ];

        $year = (int) jdate('Y', tr_num: 'en');

        $monthlyIncome = $this->getMonthlyIncome($months, $year);
        $monthlyCost = $this->getMonthlyCost($months, $year);

        $monthlySellAmount = $this->getMonthlyProductstat($months, $year);
        $monthlyWarehouse = $this->getMonthlyProductstat($months, $year, true);

        return [$monthlyIncome, $monthlyCost, $monthlySellAmount, $monthlyWarehouse];
    }

    public function balanceForSubjectIds(array $subjectIds, int $duration)
    {
        $transactionQuery = Transaction::query()->whereIn('subject_id', $subjectIds);

        $lastTransaction = (clone $transactionQuery)
            ->with('document')
            ->orderByDesc(
                Document::query()
                    ->select('date')
                    ->whereColumn('documents.id', 'transactions.document_id')
                    ->limit(1)
            )
            ->first();

        $endDate = $lastTransaction?->document?->date ?? now();
        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse((string) $endDate);

        $startDate = (clone $endDate)->subMonths($duration * 3);

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

        return response()->json([
            'labels' => array_keys($dailyBalances),
            'datas' => array_values($dailyBalances),
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
