<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CrmDashboardService
{
    private const AGING_BUCKETS = [30, 60, 90];

    public function dashboard(): array
    {
        $fiscalYear = (int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y')));
        $currentJalaliMonth = (int) toEnglish(jdate('m'));

        [$monthStart, $monthEnd] = $this->jalaliMonthRange($fiscalYear, $currentJalaliMonth);
        [$yearStart, $yearEnd] = $this->fiscalYearRange($fiscalYear);

        // Net sell amount per customer (sell - return_sell) for the whole fiscal year.
        $yearSellByCustomer = $this->netSellByCustomer($yearStart, $yearEnd);
        $monthSellByCustomer = $this->netSellByCustomer($monthStart, $monthEnd);

        $customers = Customer::query()
            ->with('group:id,name')
            ->get(['id', 'name', 'group_id', 'subject_id']);

        $ledger = $this->customerLedger($customers->pluck('subject_id')->filter()->all());

        $receivables = $this->receivables($customers, $ledger);

        return [
            'fiscalYear' => $fiscalYear,
            'currentMonthLabel' => $this->jalaliMonthName($currentJalaliMonth),
            'metrics' => [
                'salesThisMonth' => array_sum($monthSellByCustomer),
                'paidThisMonth' => $this->receiptsBetween($ledger->keys()->all(), $monthStart, $monthEnd),
                'totalUnpaid' => $receivables['total'],
                'unpaidCustomersCount' => $receivables['count'],
            ],
            'aging' => $this->aging($receivables['perCustomer'], $yearStart, $yearEnd),
            'topBuyersMonth' => $this->topBuyers($monthSellByCustomer, $customers, 5),
            'topBuyersYear' => $this->topBuyers($yearSellByCustomer, $customers, 5),
            'salesByCategory' => $this->salesByCategory($yearSellByCustomer, $customers),
            'salesTrend' => $this->salesTrend($yearStart, $yearEnd),
            'recentInvoices' => $this->recentInvoices(),
        ];
    }

    /**
     * Net sell amount (sell minus return_sell) keyed by customer id, between two dates.
     *
     * @return array<int, float>
     */
    private function netSellByCustomer(Carbon $from, Carbon $to): array
    {
        $rows = Invoice::query()
            ->whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_SELL])
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereBetween('date', [$from, $to])
            ->selectRaw('customer_id, invoice_type, SUM(amount) as total')
            ->groupBy('customer_id', 'invoice_type')
            ->get();

        $perCustomer = [];
        foreach ($rows as $row) {
            $sign = $row->invoice_type === InvoiceType::RETURN_SELL ? -1 : 1;
            $perCustomer[$row->customer_id] = ($perCustomer[$row->customer_id] ?? 0) + $sign * (float) $row->total;
        }

        return $perCustomer;
    }

    /**
     * Per-subject ledger aggregates keyed by subject id: balance and total credit.
     *
     * @param  array<int, int>  $subjectIds
     * @return Collection<int, object{balance: float, credit: float}>
     */
    private function customerLedger(array $subjectIds): Collection
    {
        if (empty($subjectIds)) {
            return collect();
        }

        return Transaction::query()
            ->whereIn('subject_id', $subjectIds)
            ->selectRaw('subject_id, SUM(value) as balance, SUM(CASE WHEN value > 0 THEN value ELSE 0 END) as credit')
            ->groupBy('subject_id')
            ->get()
            ->keyBy('subject_id')
            ->map(fn ($row) => (object) [
                'balance' => (float) $row->balance,
                'credit' => (float) $row->credit,
            ]);
    }

    /**
     * Outstanding receivable per customer. For a sell relationship the customer
     * ledger goes negative when they owe money, so outstanding = -balance.
     */
    private function receivables(Collection $customers, Collection $ledger): array
    {
        $perCustomer = [];
        $total = 0.0;

        foreach ($customers as $customer) {
            $entry = $ledger->get($customer->subject_id);
            $balance = $entry->balance ?? 0.0;

            if ($balance >= 0) {
                continue; // no receivable (settled or in credit)
            }

            $outstanding = -$balance;
            $perCustomer[] = [
                'customer' => $customer,
                'outstanding' => $outstanding,
                'credit' => $entry->credit ?? 0.0,
            ];
            $total += $outstanding;
        }

        return [
            'perCustomer' => $perCustomer,
            'total' => $total,
            'count' => count($perCustomer),
        ];
    }

    /**
     * Money received from customers (positive postings to their ledgers) within a period.
     *
     * @param  array<int, int>  $subjectIds
     */
    private function receiptsBetween(array $subjectIds, Carbon $from, Carbon $to): float
    {
        if (empty($subjectIds)) {
            return 0.0;
        }

        return (float) Transaction::query()
            ->whereIn('subject_id', $subjectIds)
            ->where('value', '>', 0)
            ->whereHas('document', fn ($q) => $q->whereBetween('date', [$from, $to]))
            ->sum('value');
    }

    /**
     * Aging breakdown of outstanding receivables.
     *
     * @param  array<int, array{customer: Customer, outstanding: float, credit: float}>  $receivables
     */
    private function aging(array $receivables, Carbon $yearStart, Carbon $yearEnd): array
    {
        $buckets = $this->emptyBuckets();
        $today = Carbon::today();

        $customerIds = collect($receivables)->pluck('customer.id')->all();
        $invoicesByCustomer = $this->openSellInvoicesByCustomer($customerIds);

        foreach ($receivables as $row) {
            $invoices = $invoicesByCustomer->get($row['customer']->id, collect());
            $credit = $row['credit'];
            $allocatedToBuckets = 0.0;

            foreach ($invoices as $invoice) {
                $open = (float) $invoice->amount;

                if ($credit > 0) {
                    $applied = min($credit, $open);
                    $open -= $applied;
                    $credit -= $applied;
                }

                if ($open <= 0) {
                    continue;
                }

                $ageDays = $invoice->date->diffInDays($today);
                $buckets[$this->bucketIndex($ageDays)]['amount'] += $open;
                $allocatedToBuckets += $open;
            }

            // Any outstanding not explained by open invoices (e.g. opening balances) falls into the oldest bucket so totals still reconcile.
            $remainder = $row['outstanding'] - $allocatedToBuckets;
            if ($remainder > 0.01) {
                $buckets[count($buckets) - 1]['amount'] += $remainder;
            }
        }

        return $buckets;
    }

    /**
     * @param  array<int, int>  $customerIds
     * @return Collection<int, Collection<int, Invoice>>
     */
    private function openSellInvoicesByCustomer(array $customerIds): Collection
    {
        if (empty($customerIds)) {
            return collect();
        }

        return Invoice::query()
            ->whereIn('customer_id', $customerIds)
            ->where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'customer_id', 'date', 'amount'])
            ->groupBy('customer_id');
    }

    /**
     * @param  array<int, float>  $sellByCustomer
     * @return array<int, array{name: string, amount: float}>
     */
    private function topBuyers(array $sellByCustomer, Collection $customers, int $limit): array
    {
        $byId = $customers->keyBy('id');

        return collect($sellByCustomer)
            ->filter(fn ($amount) => $amount > 0)
            ->sortDesc()
            ->take($limit)
            ->map(fn ($amount, $id) => [
                'name' => $byId->get($id)?->name ?? __('Unknown'),
                'amount' => (float) $amount,
            ])
            ->values()
            ->all();
    }

    /**
     * Net sell amount grouped by customer category (customer group), for the pie chart.
     *
     * @param  array<int, float>  $sellByCustomer
     * @return array<int, array{name: string, amount: float, type: string}>
     */
    private function salesByCategory(array $sellByCustomer, Collection $customers): array
    {
        $byId = $customers->keyBy('id');
        $groups = [];

        foreach ($sellByCustomer as $customerId => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $name = $byId->get($customerId)?->group?->name ?? __('Uncategorized');
            $groups[$name] = ($groups[$name] ?? 0) + $amount;
        }

        arsort($groups);

        return collect($groups)
            ->map(fn ($amount, $name) => [
                'name' => $name,
                'amount' => round($amount, 2),
                'type' => __('Customer Category'),
            ])
            ->values()
            ->all();
    }

    /**
     * Net sell amount per Jalali month across the fiscal year.
     */
    private function salesTrend(Carbon $yearStart, Carbon $yearEnd): array
    {
        $rows = Invoice::query()
            ->whereIn('invoice_type', [InvoiceType::SELL, InvoiceType::RETURN_SELL])
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereBetween('date', [$yearStart, $yearEnd])
            ->get(['date', 'amount', 'invoice_type']);

        $totals = array_fill(1, 12, 0.0);
        foreach ($rows as $row) {
            $month = (int) toEnglish(jdate('m', $row->date->timestamp));
            $sign = $row->invoice_type === InvoiceType::RETURN_SELL ? -1 : 1;
            $totals[$month] += $sign * (float) $row->amount;
        }

        $labels = [];
        $values = [];
        foreach (range(1, 12) as $month) {
            $labels[] = $this->jalaliMonthName($month);
            $values[] = round($totals[$month], 2);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * @return Collection<int, Invoice>
     */
    private function recentInvoices(): Collection
    {
        return Invoice::query()
            ->where('invoice_type', InvoiceType::SELL)
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->with('customer:id,name')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'number', 'date', 'amount', 'customer_id']);
    }

    private function emptyBuckets(): array
    {
        return [
            ['label' => __('0-30 days'), 'amount' => 0.0],
            ['label' => __('31-60 days'), 'amount' => 0.0],
            ['label' => __('61-90 days'), 'amount' => 0.0],
            ['label' => __('Over 90 days'), 'amount' => 0.0],
        ];
    }

    private function bucketIndex(int $ageDays): int
    {
        foreach (self::AGING_BUCKETS as $index => $upperBound) {
            if ($ageDays <= $upperBound) {
                return $index;
            }
        }

        return count(self::AGING_BUCKETS); // last, open-ended bucket
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function jalaliMonthRange(int $jYear, int $jMonth): array
    {
        $start = Carbon::parse(jalali_to_gregorian($jYear, $jMonth, 1, '/'))->startOfDay();

        $nextYear = $jMonth === 12 ? $jYear + 1 : $jYear;
        $nextMonth = $jMonth === 12 ? 1 : $jMonth + 1;
        $end = Carbon::parse(jalali_to_gregorian($nextYear, $nextMonth, 1, '/'))->subDay()->endOfDay();

        return [$start, $end];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function fiscalYearRange(int $jYear): array
    {
        $start = Carbon::parse(jalali_to_gregorian($jYear, 1, 1, '/'))->startOfDay();
        // Esfand has 30 days in Jalali leap years, so derive the last day from the
        // first day of the next fiscal year rather than assuming a fixed Esfand 29.
        $end = Carbon::parse(jalali_to_gregorian($jYear + 1, 1, 1, '/'))->subDay()->endOfDay();

        return [$start, $end];
    }

    private function jalaliMonthName(int $month): string
    {
        $names = [
            1 => __('Farvardin'), 2 => __('Ordibehesht'), 3 => __('Khordad'),
            4 => __('Tir'), 5 => __('Mordad'), 6 => __('Shahrivar'),
            7 => __('Mehr'), 8 => __('Aban'), 9 => __('Azar'),
            10 => __('Dey'), 11 => __('Bahman'), 12 => __('Esfand'),
        ];

        return $names[$month] ?? (string) $month;
    }
}
