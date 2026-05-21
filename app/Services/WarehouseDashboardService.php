<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WarehouseDashboardService
{
    private const PERIOD_MONTH = 'month';

    private const PERIOD_QUARTER = 'quarter';

    private const PERIOD_YEAR = 'year';

    private const STATUS_BELOW_REORDER = 'below_reorder';

    private const STATUS_STAGNANT = 'stagnant';

    private const STATUS_NORMAL = 'normal';

    private const STAGNANT_DAYS = 60;

    private const STOCK_IN_TYPES = [
        InvoiceType::BUY,
        InvoiceType::RETURN_SELL,
        InvoiceType::VOID,
    ];

    private const STOCK_OUT_TYPES = [
        InvoiceType::SELL,
        InvoiceType::RETURN_BUY,
    ];

    public function dashboard(array $rawFilters = []): array
    {
        $filters = $this->normalizeFilters($rawFilters);
        [$from, $to] = $this->periodRange($filters['period']);

        $productGroups = ProductGroup::orderBy('name')->get(['id', 'name']);
        $products = $this->productsQuery($filters)
            ->with('productGroup:id,name')
            ->get();

        $itemsInPeriod = $this->invoiceItemsBetween($from, $to, $filters['category_ids']);
        $movementMap = $this->aggregateMovement($itemsInPeriod);
        $lastMovementByProduct = $this->lastMovementDates($filters['category_ids']);

        $categoryBuckets = $this->bucketByCategory($products, $movementMap, $productGroups);

        $totalInventoryValue = $products->sum(fn (Product $p) => (float) $p->quantity * (float) $p->average_cost);
        $belowReorder = $products->filter(fn (Product $p) => $this->isBelowReorder($p));
        $stagnantStandalone = $this->stagnantProducts($products, $lastMovementByProduct);

        $statusFiltered = $this->applyStatusFilter($products, $belowReorder, $stagnantStandalone, $filters['status']);

        $topSellers = $this->topSellers($itemsInPeriod, 10);
        $monthlyMovement = $this->monthlyMovement($itemsInPeriod, $from, $to);
        $monthlyMovementByCategory = $this->monthlyMovementByCategory($itemsInPeriod, $from, $to, $categoryBuckets);

        $overallTurnover = $this->turnoverRatio(
            $products->sum(fn (Product $p) => (float) $p->quantity * (float) $p->average_cost),
            $categoryBuckets->sum('cogs_period')
        );

        return [
            'filters' => $filters,
            'periodLabel' => $this->periodLabel($filters['period'], $from, $to),
            'periodRange' => ['from' => $from->copy(), 'to' => $to->copy()],
            'productGroups' => $productGroups,
            'periodOptions' => $this->periodOptions(),
            'statusOptions' => $this->statusOptions(),
            'summary' => [
                'total_inventory_value' => (float) $totalInventoryValue,
                'total_item_count' => $products->count(),
                'total_stock_quantity' => (float) $products->sum(fn (Product $p) => (float) $p->quantity),
                'below_reorder_count' => $belowReorder->count(),
                'stagnant_count' => $stagnantStandalone->count(),
                'avg_turnover_ratio' => (float) $overallTurnover,
                'avg_holding_days' => $this->holdingDays($overallTurnover, $from, $to),
            ],
            'categoryBreakdown' => $categoryBuckets->values(),
            'monthlyMovement' => $monthlyMovement,
            'monthlyMovementByCategory' => $monthlyMovementByCategory,
            'belowReorderItems' => $this->mapProductRows($belowReorder->sortBy(fn (Product $p) => (float) $p->quantity)->take(15)),
            'stagnantItems' => $this->mapStagnantRows($stagnantStandalone->take(15), $lastMovementByProduct),
            'topSellers' => $topSellers,
            'statusFilteredItems' => $this->mapProductRows($statusFiltered->take(15)),
            'alerts' => $this->alerts($belowReorder, $stagnantStandalone, $itemsInPeriod->isEmpty()),
            'stagnant_days' => self::STAGNANT_DAYS,
        ];
    }

    private function normalizeFilters(array $raw): array
    {
        $period = in_array($raw['period'] ?? null, [self::PERIOD_MONTH, self::PERIOD_QUARTER, self::PERIOD_YEAR], true)
            ? $raw['period']
            : self::PERIOD_YEAR;

        $categoryIds = collect($raw['category_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $status = in_array($raw['status'] ?? null, [self::STATUS_BELOW_REORDER, self::STATUS_STAGNANT, self::STATUS_NORMAL], true)
            ? $raw['status']
            : null;

        return [
            'period' => $period,
            'category_ids' => $categoryIds,
            'status' => $status,
        ];
    }

    private function periodRange(string $period): array
    {
        $to = Carbon::now()->endOfDay();
        $from = match ($period) {
            self::PERIOD_MONTH => Carbon::now()->subDays(30)->startOfDay(),
            self::PERIOD_QUARTER => Carbon::now()->subDays(90)->startOfDay(),
            default => Carbon::now()->subDays(365)->startOfDay(),
        };

        return [$from, $to];
    }

    private function productsQuery(array $filters): Builder
    {
        return Product::query()
            ->when(! empty($filters['category_ids']), fn (Builder $q) => $q->whereIn('group', $filters['category_ids']))
            ->orderBy('code');
    }

    private function invoiceItemsBetween(Carbon $from, Carbon $to, array $categoryIds): Collection
    {
        return InvoiceItem::query()
            ->where('itemable_type', Product::class)
            ->whereHas('invoice', function (Builder $q) use ($from, $to) {
                $q->where('status', InvoiceStatus::APPROVED)
                    ->whereIn('invoice_type', array_merge(self::STOCK_IN_TYPES, self::STOCK_OUT_TYPES))
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            })
            ->when(! empty($categoryIds), function (Builder $q) use ($categoryIds) {
                $q->whereHasMorph('itemable', Product::class, fn (Builder $p) => $p->whereIn('group', $categoryIds));
            })
            ->with([
                'invoice:id,date,invoice_type,status,number',
                'itemable:id,code,name,group,quantity,quantity_warning,average_cost,selling_price',
                'itemable.productGroup:id,name',
            ])
            ->get();
    }

    private function aggregateMovement(Collection $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $productId = (int) $item->itemable_id;
            $type = $item->invoice->invoice_type;
            $qty = (float) $item->quantity;
            $cogs = $qty * (float) ($item->cog_after ?? $item->itemable->average_cost ?? 0);
            $rev = (float) $item->amount - (float) ($item->vat ?? 0);

            if (! isset($map[$productId])) {
                $map[$productId] = ['in' => 0.0, 'out' => 0.0, 'cogs' => 0.0, 'revenue' => 0.0];
            }

            if (in_array($type, self::STOCK_IN_TYPES, true)) {
                $map[$productId]['in'] += $qty;
            } elseif (in_array($type, self::STOCK_OUT_TYPES, true)) {
                $map[$productId]['out'] += $qty;
            }

            if ($type === InvoiceType::SELL) {
                $map[$productId]['cogs'] += $cogs;
                $map[$productId]['revenue'] += $rev;
            } elseif ($type === InvoiceType::RETURN_SELL) {
                $map[$productId]['cogs'] -= $cogs;
                $map[$productId]['revenue'] -= $rev;
            }
        }

        return $map;
    }

    private function lastMovementDates(array $categoryIds): array
    {
        return InvoiceItem::query()
            ->selectRaw('invoice_items.itemable_id as product_id, MAX(invoices.date) as last_date')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.itemable_type', Product::class)
            ->where('invoices.status', InvoiceStatus::APPROVED->value)
            ->whereIn('invoices.invoice_type', array_map(fn (InvoiceType $t) => $t->value, array_merge(self::STOCK_IN_TYPES, self::STOCK_OUT_TYPES)))
            ->where('invoices.company_id', getActiveCompany())
            ->when(! empty($categoryIds), function ($q) use ($categoryIds) {
                $q->join('products', 'products.id', '=', 'invoice_items.itemable_id')
                    ->whereIn('products.group', $categoryIds);
            })
            ->groupBy('invoice_items.itemable_id')
            ->pluck('last_date', 'product_id')
            ->all();
    }

    private function bucketByCategory(Collection $products, array $movementMap, Collection $productGroups): Collection
    {
        $byGroupId = $products->groupBy(fn (Product $p) => (int) ($p->group ?? 0));
        $groupNames = $productGroups->keyBy('id');

        return $byGroupId
            ->map(function (Collection $groupProducts, int $groupId) use ($movementMap, $groupNames) {
                $value = (float) $groupProducts->sum(fn (Product $p) => (float) $p->quantity * (float) $p->average_cost);
                $cogsPeriod = 0.0;
                $unitsOut = 0.0;
                $unitsIn = 0.0;

                foreach ($groupProducts as $p) {
                    $m = $movementMap[$p->id] ?? null;
                    if ($m === null) {
                        continue;
                    }
                    $cogsPeriod += $m['cogs'];
                    $unitsOut += $m['out'];
                    $unitsIn += $m['in'];
                }

                $turnover = $this->turnoverRatio($value, $cogsPeriod);

                return [
                    'id' => $groupId,
                    'name' => $groupId === 0 ? __('Uncategorized') : ($groupNames->get($groupId)?->name ?? __('Unknown')),
                    'item_count' => $groupProducts->count(),
                    'inventory_value' => $value,
                    'units_in' => $unitsIn,
                    'units_out' => $unitsOut,
                    'cogs_period' => $cogsPeriod,
                    'turnover_ratio' => $turnover,
                ];
            })
            ->sortByDesc('inventory_value');
    }

    private function turnoverRatio(float $inventoryValue, float $cogsInPeriod): float
    {
        if ($inventoryValue <= 0) {
            return 0.0;
        }

        return round($cogsInPeriod / $inventoryValue, 2);
    }

    private function holdingDays(float $turnover, Carbon $from, Carbon $to): float
    {
        if ($turnover <= 0) {
            return 0.0;
        }

        $days = max(1, $from->diffInDays($to));

        return round($days / $turnover, 1);
    }

    private function isBelowReorder(Product $product): bool
    {
        $warning = $product->quantity_warning;
        if ($warning === null || (float) $warning <= 0) {
            return false;
        }

        return (float) $product->quantity <= (float) $warning;
    }

    private function stagnantProducts(Collection $products, array $lastMovementByProduct): Collection
    {
        $threshold = Carbon::now()->subDays(self::STAGNANT_DAYS);

        return $products->filter(function (Product $product) use ($lastMovementByProduct, $threshold) {
            if ((float) $product->quantity <= 0) {
                return false;
            }

            $lastRaw = $lastMovementByProduct[$product->id] ?? null;
            if ($lastRaw === null) {
                return true;
            }

            return Carbon::parse($lastRaw)->lt($threshold);
        })->values();
    }

    private function applyStatusFilter(Collection $products, Collection $belowReorder, Collection $stagnant, ?string $status): Collection
    {
        return match ($status) {
            self::STATUS_BELOW_REORDER => $belowReorder->values(),
            self::STATUS_STAGNANT => $stagnant->values(),
            self::STATUS_NORMAL => $products
                ->reject(fn (Product $p) => $this->isBelowReorder($p) || $stagnant->contains('id', $p->id))
                ->values(),
            default => collect(),
        };
    }

    private function monthlyMovement(Collection $items, Carbon $from, Carbon $to): array
    {
        $buckets = $this->monthlyBuckets($from, $to);

        foreach ($items as $item) {
            $key = $this->monthKey($item->invoice->date);
            if (! isset($buckets[$key])) {
                continue;
            }

            $qty = (float) $item->quantity;
            $type = $item->invoice->invoice_type;
            if (in_array($type, self::STOCK_IN_TYPES, true)) {
                $buckets[$key]['in'] += $qty;
            } elseif (in_array($type, self::STOCK_OUT_TYPES, true)) {
                $buckets[$key]['out'] += $qty;
            }
        }

        return [
            'labels' => array_keys($buckets),
            'in' => array_map(fn ($b) => round($b['in'], 2), array_values($buckets)),
            'out' => array_map(fn ($b) => round($b['out'], 2), array_values($buckets)),
        ];
    }

    private function monthlyMovementByCategory(Collection $items, Carbon $from, Carbon $to, Collection $categoryBuckets): array
    {
        $topCategories = $categoryBuckets->take(5)->pluck('id')->all();
        if (empty($topCategories)) {
            return ['labels' => array_keys($this->monthlyBuckets($from, $to)), 'datasets' => []];
        }

        $monthBuckets = $this->monthlyBuckets($from, $to);
        $datasets = [];

        foreach ($topCategories as $groupId) {
            $datasets[$groupId] = [
                'name' => $categoryBuckets->firstWhere('id', $groupId)['name'],
                'in' => array_fill_keys(array_keys($monthBuckets), 0.0),
                'out' => array_fill_keys(array_keys($monthBuckets), 0.0),
            ];
        }

        foreach ($items as $item) {
            $product = $item->itemable;
            if (! $product) {
                continue;
            }
            $groupId = (int) ($product->group ?? 0);
            if (! isset($datasets[$groupId])) {
                continue;
            }

            $monthKey = $this->monthKey($item->invoice->date);
            if (! isset($monthBuckets[$monthKey])) {
                continue;
            }

            $qty = (float) $item->quantity;
            $type = $item->invoice->invoice_type;
            if (in_array($type, self::STOCK_IN_TYPES, true)) {
                $datasets[$groupId]['in'][$monthKey] += $qty;
            } elseif (in_array($type, self::STOCK_OUT_TYPES, true)) {
                $datasets[$groupId]['out'][$monthKey] += $qty;
            }
        }

        return [
            'labels' => array_keys($monthBuckets),
            'datasets' => collect($datasets)->map(fn ($d) => [
                'name' => $d['name'],
                'in' => array_map(fn ($v) => round($v, 2), array_values($d['in'])),
                'out' => array_map(fn ($v) => round($v, 2), array_values($d['out'])),
            ])->values()->all(),
        ];
    }

    private function monthlyBuckets(Carbon $from, Carbon $to): array
    {
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        $buckets = [];

        while ($cursor->lte($end)) {
            $key = $this->jalaliMonthKey($cursor);
            $buckets[$key] = ['in' => 0.0, 'out' => 0.0];
            $cursor->addMonthNoOverflow();
        }

        return $buckets;
    }

    private function monthKey($date): string
    {
        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $this->jalaliMonthKey($carbon);
    }

    private function jalaliMonthKey(Carbon $date): string
    {
        return toEnglish(jdate('Y/m', $date->timestamp));
    }

    private function topSellers(Collection $items, int $limit): Collection
    {
        return $items
            ->filter(fn (InvoiceItem $i) => in_array($i->invoice->invoice_type, [InvoiceType::SELL, InvoiceType::RETURN_SELL], true))
            ->groupBy('itemable_id')
            ->map(function (Collection $group) {
                $first = $group->first();
                $product = $first->itemable;
                $units = $group->sum(function (InvoiceItem $i) {
                    $sign = $i->invoice->invoice_type === InvoiceType::SELL ? 1 : -1;

                    return $sign * (float) $i->quantity;
                });
                $revenue = $group->sum(function (InvoiceItem $i) {
                    $sign = $i->invoice->invoice_type === InvoiceType::SELL ? 1 : -1;

                    return $sign * ((float) $i->amount - (float) ($i->vat ?? 0));
                });

                return [
                    'id' => (int) $first->itemable_id,
                    'code' => $product?->code ?? '-',
                    'name' => $product?->name ?? __('Unknown'),
                    'group' => $product?->productGroup?->name ?? '-',
                    'units' => (float) $units,
                    'revenue' => (float) $revenue,
                ];
            })
            ->filter(fn (array $row) => $row['units'] > 0)
            ->sortByDesc('units')
            ->take($limit)
            ->values();
    }

    private function mapProductRows(Collection $products): Collection
    {
        return $products->map(fn (Product $p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'group' => $p->productGroup?->name ?? '-',
            'quantity' => (float) $p->quantity,
            'quantity_warning' => (float) ($p->quantity_warning ?? 0),
            'average_cost' => (float) $p->average_cost,
            'inventory_value' => (float) $p->quantity * (float) $p->average_cost,
        ])->values();
    }

    private function mapStagnantRows(Collection $products, array $lastMovementByProduct): Collection
    {
        return $products->map(function (Product $p) use ($lastMovementByProduct) {
            $last = $lastMovementByProduct[$p->id] ?? null;
            $lastCarbon = $last ? Carbon::parse($last) : null;

            return [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'group' => $p->productGroup?->name ?? '-',
                'quantity' => (float) $p->quantity,
                'inventory_value' => (float) $p->quantity * (float) $p->average_cost,
                'last_movement' => $lastCarbon,
                'days_idle' => $lastCarbon ? $lastCarbon->diffInDays(Carbon::now()) : null,
            ];
        })->values();
    }

    private function alerts(Collection $belowReorder, Collection $stagnant, bool $noMovement): array
    {
        return [
            [
                'title' => $belowReorder->isNotEmpty()
                    ? __(':count item(s) are at or below their reorder point', ['count' => formatNumber($belowReorder->count())])
                    : __('All stock levels are above their reorder points'),
                'description' => $belowReorder->isNotEmpty()
                    ? __('Review the below-reorder table and trigger purchase orders.')
                    : __('Nothing needs replenishment right now.'),
                'tone' => $belowReorder->isNotEmpty() ? 'warning' : 'success',
            ],
            [
                'title' => $stagnant->isNotEmpty()
                    ? __(':count item(s) have had no movement in the last :days days', ['count' => formatNumber($stagnant->count()), 'days' => formatNumber(self::STAGNANT_DAYS)])
                    : __('No stagnant inventory detected'),
                'description' => $stagnant->isNotEmpty()
                    ? __('Consider discounts, bundles, or write-offs for these items.')
                    : __('Items are turning regularly across the selected categories.'),
                'tone' => $stagnant->isNotEmpty() ? 'info' : 'success',
            ],
            [
                'title' => $noMovement
                    ? __('No approved warehouse movement in the selected period')
                    : __('Warehouse movement data is up to date'),
                'description' => $noMovement
                    ? __('Approve pending invoices or widen the time range to see trends.')
                    : __('Charts reflect approved buy, sell, and return invoices.'),
                'tone' => $noMovement ? 'placeholder' : 'success',
            ],
        ];
    }

    private function periodOptions(): array
    {
        return [
            self::PERIOD_MONTH => __('Last 30 days'),
            self::PERIOD_QUARTER => __('Last quarter'),
            self::PERIOD_YEAR => __('All time'),
        ];
    }

    private function statusOptions(): array
    {
        return [
            self::STATUS_BELOW_REORDER => __('Below reorder point'),
            self::STATUS_STAGNANT => __('Stagnant'),
            self::STATUS_NORMAL => __('Normal'),
        ];
    }

    private function periodLabel(string $period, Carbon $from, Carbon $to): string
    {
        $label = $this->periodOptions()[$period] ?? $period;
        $fromJ = toEnglish(jdate('Y/m/d', $from->timestamp));
        $toJ = toEnglish(jdate('Y/m/d', $to->timestamp));

        return $label.' ('.$fromJ.' - '.$toJ.')';
    }
}
