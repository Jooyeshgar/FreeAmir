<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class InvoiceDashboardService
{
    public function dashboard(array $rawFilters = []): array
    {
        [$fiscalStart, $fiscalEnd] = $this->activeFiscalYearRange();
        $filters = $this->normalizeFilters($rawFilters, $fiscalStart, $fiscalEnd);
        $from = Carbon::parse($filters['start_date'])->startOfDay();
        $to = Carbon::parse($filters['end_date'])->endOfDay();

        $items = $this->itemsBetween($from, $to);

        return [
            'filters' => $filters,
            'fiscalYearStart' => $fiscalStart,
            'fiscalYearEnd' => $fiscalEnd,
            'periodLabel' => $this->periodLabel($from, $to),
            'summary' => $this->summary($items),
            'productTrend' => $this->trend($items, Product::class, $from, $to),
            'serviceTrend' => $this->trend($items, Service::class, $from, $to),
            'productSalesBreakdown' => $this->salesBreakdown($items, Product::class),
            'serviceSalesBreakdown' => $this->salesBreakdown($items, Service::class),
            'topSales' => $this->topSales($items),
            'recentInvoices' => $this->recentInvoices($from, $to)
                ->sortByDesc(fn (Invoice $invoice) => $invoice->date->format('Y-m-d').'-'.str_pad((string) $invoice->id, 12, '0', STR_PAD_LEFT))
                ->take(8)
                ->values(),
        ];
    }

    private function activeFiscalYearRange(): array
    {
        return Company::withoutGlobalScopes()->findOrFail(getActiveCompany())->fiscalYearRange();
    }

    private function normalizeFilters(array $raw, Carbon $fiscalStart, Carbon $fiscalEnd): array
    {
        $from = isset($raw['start_date']) ? Carbon::parse($raw['start_date'])->startOfDay() : $fiscalStart->copy();
        $to = isset($raw['end_date']) ? Carbon::parse($raw['end_date'])->endOfDay() : $fiscalEnd->copy();

        $from = $from->max($fiscalStart);
        $to = $to->min($fiscalEnd);

        if ($from->gt($to)) {
            throw new \InvalidArgumentException('Invoice dashboard start date must not be after end date.');
        }

        return [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ];
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
                'invoice:id,date,amount,invoice_type,status',
                'itemable:id,name',
            ])
            ->get();
    }

    private function summary(Collection $items): array
    {
        $byInvoice = $items->groupBy('invoice_id');
        $netSales = $this->netInvoicesByType($byInvoice, InvoiceType::SELL, [InvoiceType::RETURN_SELL, InvoiceType::VOID]);
        $netPurchases = $this->netInvoicesByType($byInvoice, InvoiceType::BUY, [InvoiceType::RETURN_BUY]);
        $sales = $byInvoice->filter(fn (Collection $group) => $group->first()->invoice->invoice_type === InvoiceType::SELL);
        $purchases = $byInvoice->filter(fn (Collection $group) => $group->first()->invoice->invoice_type === InvoiceType::BUY);
        $salesReturns = $this->invoiceAmountForTypes($byInvoice, [InvoiceType::RETURN_SELL, InvoiceType::VOID]);
        $purchaseReturns = $this->invoiceAmountForTypes($byInvoice, [InvoiceType::RETURN_BUY]);

        return [
            'net_sales' => round($netSales, 2),
            'net_purchases' => round($netPurchases, 2),
            'trade_balance' => round($netSales - $netPurchases, 2),
            'sales_count' => $sales->count(),
            'purchase_count' => $purchases->count(),
            'average_sale' => round((float) $sales->avg(fn (Collection $group) => (float) $group->first()->invoice->amount), 2),
            'average_purchase' => round((float) $purchases->avg(fn (Collection $group) => (float) $group->first()->invoice->amount), 2),
            'sales_returns' => round($salesReturns, 2),
            'purchase_returns' => round($purchaseReturns, 2),
            ...$this->productProfitSummary($items),
        ];
    }

    private function productProfitSummary(Collection $items): array
    {
        $totals = $items
            ->where('itemable_type', Product::class)
            ->filter(fn (InvoiceItem $item) => in_array($item->invoice->invoice_type, [
                InvoiceType::SELL,
                InvoiceType::RETURN_SELL,
                InvoiceType::VOID,
            ], true))
            ->reduce(function (array $totals, InvoiceItem $item) {
                $sign = $item->invoice->invoice_type === InvoiceType::SELL ? 1 : -1;
                $revenue = $this->itemRevenue($item) * $sign;
                $cost = (float) $item->cog_after * (float) $item->quantity * $sign;

                $totals['revenue'] += $revenue;
                $totals['cogs'] += $cost;

                return $totals;
            }, ['revenue' => 0.0, 'cogs' => 0.0]);

        $profit = $totals['revenue'] - $totals['cogs'];
        $margin = $totals['revenue'] != 0.0 ? ($profit / $totals['revenue']) * 100 : 0.0;

        return [
            'product_revenue' => round($totals['revenue'], 2),
            'product_cogs' => round($totals['cogs'], 2),
            'product_profit' => round($profit, 2),
            'product_profit_margin' => round($margin, 2),
        ];
    }

    private function netInvoicesByType(Collection $byInvoice, InvoiceType $positive, array $negative): float
    {
        return (float) $byInvoice->sum(function (Collection $group) use ($positive, $negative) {
            $invoice = $group->first()->invoice;

            if ($invoice->invoice_type === $positive) {
                return (float) $invoice->amount;
            }

            return in_array($invoice->invoice_type, $negative, true) ? -(float) $invoice->amount : 0.0;
        });
    }

    private function invoiceAmountForTypes(Collection $byInvoice, array $types): float
    {
        return (float) $byInvoice
            ->filter(fn (Collection $group) => in_array($group->first()->invoice->invoice_type, $types, true))
            ->sum(fn (Collection $group) => (float) $group->first()->invoice->amount);
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

    private function salesBreakdown(Collection $items, string $itemableType): Collection
    {
        $sales = $items
            ->where('itemable_type', $itemableType)
            ->groupBy('itemable_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'id' => $first->itemable_id,
                    'name' => $first->itemable?->name ?? __('Unknown'),
                    'amount' => round($this->netItemSales($group), 2),
                ];
            })
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->values();

        $topSales = $sales->take(5)->values();
        $otherAmount = (float) $sales->skip(5)->sum('amount');

        if ($otherAmount > 0) {
            $topSales->push([
                'id' => null,
                'name' => __('Other'),
                'amount' => round($otherAmount, 2),
            ]);
        }

        return $topSales;
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
                $revenue = $this->netItemRevenue($group);
                $profitMargin = null;

                if ($first->itemable_type === Product::class) {
                    $cost = $this->netItemCost($group);
                    $profitMargin = $revenue != 0.0 ? round((($revenue - $cost) / $revenue) * 100, 2) : 0.0;
                }

                return [
                    'id' => $first->itemable_id,
                    'itemable_type' => $first->itemable_type,
                    'name' => $first->itemable?->name ?? __('Unknown'),
                    'kind' => $first->itemable_type === Product::class ? __('Product') : __('Service'),
                    'quantity' => (float) $group->sum(function (InvoiceItem $item) {
                        $sign = $item->invoice->invoice_type === InvoiceType::SELL ? 1 : -1;

                        return $sign * (float) $item->quantity;
                    }),
                    'amount' => $this->netItemSales($group),
                    'profit_margin' => $profitMargin,
                ];
            })
            ->filter(fn (array $row) => $row['amount'] > 0)
            ->sortByDesc('amount')
            ->take(8)
            ->values();
    }

    private function netItemRevenue(Collection $items): float
    {
        return (float) $items->sum(function (InvoiceItem $item) {
            $sign = $item->invoice->invoice_type === InvoiceType::SELL ? 1 : -1;

            return $this->itemRevenue($item) * $sign;
        });
    }

    private function netItemCost(Collection $items): float
    {
        return (float) $items->sum(function (InvoiceItem $item) {
            $sign = $item->invoice->invoice_type === InvoiceType::SELL ? 1 : -1;

            return (float) $item->cog_after * (float) $item->quantity * $sign;
        });
    }

    private function itemRevenue(InvoiceItem $item): float
    {
        return (float) $item->amount - (float) $item->vat;
    }

    private function monthlyBuckets(Carbon $from, Carbon $to): array
    {
        [$year, $month] = array_map('intval', explode('/', $this->monthKey($from)));
        [$endYear, $endMonth] = array_map('intval', explode('/', $this->monthKey($to)));
        $buckets = [];

        while ($year < $endYear || ($year === $endYear && $month <= $endMonth)) {
            $buckets[sprintf('%04d/%02d', $year, $month)] = ['sell' => 0.0, 'buy' => 0.0];
            $month++;
            if ($month === 13) {
                $month = 1;
                $year++;
            }
        }

        return $buckets;
    }

    private function monthKey(Carbon|string $date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return toEnglish(jdate('Y/m', $carbon->timestamp));
    }

    private function periodLabel(Carbon $from, Carbon $to): string
    {
        return toEnglish(jdate('Y/m/d', $from->timestamp))
            .' - '.toEnglish(jdate('Y/m/d', $to->timestamp));
    }
}
