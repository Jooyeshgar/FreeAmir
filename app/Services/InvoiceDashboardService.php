<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoiceDashboardService
{
    private const PERIOD_MONTH = 'month';

    private const PERIOD_QUARTER = 'quarter';

    private const PERIOD_YEAR = 'year';

    public function dashboard(array $rawFilters = []): array
    {
        $filters = $this->normalizeFilters($rawFilters);
        [$from, $to] = $this->periodRange($filters['period']);

        $items = $this->itemsBetween($from, $to);

        return [
            'filters' => $filters,
            'periodOptions' => $this->periodOptions(),
            'periodLabel' => $this->periodLabel($filters['period'], $from, $to),
            'summary' => $this->summary($items),
            'productTrend' => $this->trend($items, Product::class, $from, $to),
            'serviceTrend' => $this->trend($items, Service::class, $from, $to),
            'salesMix' => $this->salesMix($items),
            'topSales' => $this->topSales($items),
            'recentInvoices' => $this->recentInvoices($from, $to)
                ->sortByDesc(fn (Invoice $invoice) => $invoice->date->format('Y-m-d').'-'.str_pad((string) $invoice->id, 12, '0', STR_PAD_LEFT))
                ->take(8)
                ->values(),
        ];
    }

    private function normalizeFilters(array $raw): array
    {
        $period = in_array($raw['period'] ?? null, [self::PERIOD_MONTH, self::PERIOD_QUARTER, self::PERIOD_YEAR], true)
            ? $raw['period']
            : self::PERIOD_YEAR;

        return ['period' => $period];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(string $period): array
    {
        $to = Carbon::today()->endOfDay();
        $from = match ($period) {
            self::PERIOD_MONTH => Carbon::today()->subDays(30)->startOfDay(),
            self::PERIOD_QUARTER => Carbon::today()->subDays(90)->startOfDay(),
            default => Carbon::parse(jalali_to_gregorian(
                (int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y'))),
                1,
                1,
                '/'
            ))->startOfDay(),
        };

        return [$from, $to];
    }

    private function recentInvoices(Carbon $from, Carbon $to): Collection
    {
        return Invoice::query()
            ->whereIn('status', InvoiceStatus::approvedOrSettled())
            ->whereIn('invoice_type', [
                InvoiceType::SELL,
                InvoiceType::BUY,
                InvoiceType::RETURN_SELL,
                InvoiceType::RETURN_BUY,
                InvoiceType::VOID,
            ])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('customer:id,name')
            ->get(['id', 'number', 'date', 'amount', 'invoice_type', 'status', 'customer_id']);
    }

    private function itemsBetween(Carbon $from, Carbon $to): Collection
    {
        return InvoiceItem::query()
            ->whereHas('invoice', function (Builder $query) use ($from, $to) {
                $query->whereIn('status', InvoiceStatus::approvedOrSettled())
                    ->whereIn('invoice_type', [
                        InvoiceType::SELL,
                        InvoiceType::BUY,
                        InvoiceType::RETURN_SELL,
                        InvoiceType::RETURN_BUY,
                        InvoiceType::VOID,
                    ])
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            })
            ->with([
                'invoice:id,date,invoice_type,status',
                'itemable:id,name',
            ])
            ->get();
    }

    private function summary(Collection $items): array
    {
        $byInvoice = $items->groupBy('invoice_id');
        $netSales = $this->netItemsByInvoiceType($items, InvoiceType::SELL, [InvoiceType::RETURN_SELL, InvoiceType::VOID]);
        $netPurchases = $this->netItemsByInvoiceType($items, InvoiceType::BUY, [InvoiceType::RETURN_BUY]);
        $sales = $byInvoice->filter(fn (Collection $group) => $group->first()->invoice->invoice_type === InvoiceType::SELL);
        $purchases = $byInvoice->filter(fn (Collection $group) => $group->first()->invoice->invoice_type === InvoiceType::BUY);
        $salesReturns = (float) $items
            ->filter(fn (InvoiceItem $item) => in_array($item->invoice->invoice_type, [InvoiceType::RETURN_SELL, InvoiceType::VOID], true))
            ->sum('amount');
        $purchaseReturns = (float) $items
            ->filter(fn (InvoiceItem $item) => $item->invoice->invoice_type === InvoiceType::RETURN_BUY)
            ->sum('amount');

        return [
            'net_sales' => round($netSales, 2),
            'net_purchases' => round($netPurchases, 2),
            'trade_balance' => round($netSales - $netPurchases, 2),
            'sales_count' => $sales->count(),
            'purchase_count' => $purchases->count(),
            'average_sale' => round((float) $sales->avg(fn (Collection $group) => (float) $group->sum('amount')), 2),
            'average_purchase' => round((float) $purchases->avg(fn (Collection $group) => (float) $group->sum('amount')), 2),
            'sales_returns' => round($salesReturns, 2),
            'purchase_returns' => round($purchaseReturns, 2),
        ];
    }

    private function netItemsByInvoiceType(Collection $items, InvoiceType $positive, array $negative): float
    {
        return (float) $items->sum(function (InvoiceItem $item) use ($positive, $negative) {
            $type = $item->invoice->invoice_type;

            if ($type === $positive) {
                return (float) $item->amount;
            }

            return in_array($type, $negative, true) ? -(float) $item->amount : 0.0;
        });
    }

    private function trend(Collection $items, string $itemableType, Carbon $from, Carbon $to): array
    {
        $buckets = $this->monthlyBuckets($from, $to);

        foreach ($items->where('itemable_type', $itemableType) as $item) {
            $key = $this->monthKey($item->invoice->date);
            if (! isset($buckets[$key])) {
                continue;
            }

            $amount = (float) $item->amount;
            $type = $item->invoice->invoice_type;

            if ($type === InvoiceType::SELL) {
                $buckets[$key]['sell'] += $amount;
            } elseif (in_array($type, [InvoiceType::RETURN_SELL, InvoiceType::VOID], true)) {
                $buckets[$key]['sell'] -= $amount;
            } elseif ($type === InvoiceType::BUY) {
                $buckets[$key]['buy'] += $amount;
            } elseif ($type === InvoiceType::RETURN_BUY) {
                $buckets[$key]['buy'] -= $amount;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'sell' => array_map(fn (array $bucket) => round($bucket['sell'], 2), array_values($buckets)),
            'buy' => array_map(fn (array $bucket) => round($bucket['buy'], 2), array_values($buckets)),
        ];
    }

    private function salesMix(Collection $items): array
    {
        $productSales = $this->netItemSales($items->where('itemable_type', Product::class));
        $serviceSales = $this->netItemSales($items->where('itemable_type', Service::class));

        return [
            ['name' => __('Products'), 'amount' => round(max(0, $productSales), 2)],
            ['name' => __('Services'), 'amount' => round(max(0, $serviceSales), 2)],
        ];
    }

    private function netItemSales(Collection $items): float
    {
        return (float) $items->sum(function (InvoiceItem $item) {
            return match ($item->invoice->invoice_type) {
                InvoiceType::SELL => (float) $item->amount,
                InvoiceType::RETURN_SELL, InvoiceType::VOID => -(float) $item->amount,
                default => 0.0,
            };
        });
    }

    private function topSales(Collection $items): Collection
    {
        return $items
            ->filter(fn (InvoiceItem $item) => in_array($item->invoice->invoice_type, [
                InvoiceType::SELL,
                InvoiceType::RETURN_SELL,
                InvoiceType::VOID,
            ], true))
            ->groupBy(fn (InvoiceItem $item) => $item->itemable_type.'-'.$item->itemable_id)
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'name' => $first->itemable?->name ?? __('Unknown'),
                    'kind' => $first->itemable_type === Product::class ? __('Product') : __('Service'),
                    'quantity' => (float) $group->sum(function (InvoiceItem $item) {
                        $sign = $item->invoice->invoice_type === InvoiceType::SELL ? 1 : -1;

                        return $sign * (float) $item->quantity;
                    }),
                    'amount' => $this->netItemSales($group),
                ];
            })
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->take(8)
            ->values();
    }

    private function monthlyBuckets(Carbon $from, Carbon $to): array
    {
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        $buckets = [];

        while ($cursor->lte($end)) {
            $buckets[$this->monthKey($cursor)] = ['sell' => 0.0, 'buy' => 0.0];
            $cursor->addMonthNoOverflow();
        }

        return $buckets;
    }

    private function monthKey(Carbon|string $date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return toEnglish(jdate('Y/m', $carbon->timestamp));
    }

    private function periodOptions(): array
    {
        return [
            self::PERIOD_MONTH => __('Last 30 days'),
            self::PERIOD_QUARTER => __('Last quarter'),
            self::PERIOD_YEAR => __('Fiscal year to date'),
        ];
    }

    private function periodLabel(string $period, Carbon $from, Carbon $to): string
    {
        return ($this->periodOptions()[$period] ?? $period)
            .' ('.toEnglish(jdate('Y/m/d', $from->timestamp))
            .' - '.toEnglish(jdate('Y/m/d', $to->timestamp)).')';
    }
}
