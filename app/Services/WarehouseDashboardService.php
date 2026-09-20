<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WarehouseDashboardService
{
    public function __construct(private readonly ProductService $productService) {}

    public const OPTIONAL_COLUMNS = [
        'inbound',
        'outbound',
        'stock',
        'category',
        'code',
        'selling_price',
        'cost_of_goods',
        'last_item_cost',
        'sales_profit',
        'revenue_account',
        'cogs_account',
        'inventory_account',
        'sales_return_account',
    ];

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
        $company = Company::withoutGlobalScopes()->findOrFail(getActiveCompany());
        [$fiscalStart, $fiscalEnd] = $company->fiscalYearRange();
        [$from, $to] = $this->periodRange($filters['period'], $fiscalStart, $fiscalEnd);

        $productGroups = ProductGroup::orderBy('name')->get(['id', 'name']);
        $products = $this->productsQuery($filters)
            ->with('productGroup:id,name')
            ->get();
        $inventoryBalances = $this->inventoryBalances($products, $fiscalStart, $to);
        $stockQuantities = $this->stockQuantities($products, $fiscalStart, $to);
        $periodProducts = $products->filter(fn (Product $product) => array_key_exists($product->id, $stockQuantities)
            || $this->inventoryValue($product, $inventoryBalances) != 0.0);

        $itemsInPeriod = $this->invoiceItemsBetween($from, $to, $filters['category_id']);
        $movementMap = $this->aggregateMovement($itemsInPeriod);
        $lastMovementByProduct = $this->lastMovementDates($fiscalStart, $to, $filters['category_id']);

        $categoryBuckets = $this->bucketByCategory($periodProducts, $movementMap, $productGroups, $inventoryBalances, $stockQuantities);

        $totalInventoryValue = $periodProducts->sum(fn (Product $product) => $this->inventoryValue($product, $inventoryBalances));
        $belowReorder = $periodProducts->filter(fn (Product $product) => $this->isBelowReorder($product, $stockQuantities));
        $stagnantStandalone = $this->stagnantProducts($periodProducts, $lastMovementByProduct, $stockQuantities, $to);

        $statusFiltered = $this->applyStatusFilter($periodProducts, $belowReorder, $stagnantStandalone, $filters['status'], $stockQuantities);

        $topSellers = $this->topSellers($itemsInPeriod, 10);
        $monthlyMovement = $this->monthlyMovement($itemsInPeriod, $from, $to);
        $monthlyMovementByCategory = $this->monthlyMovementByCategory($itemsInPeriod, $from, $to, $categoryBuckets);

        $overallTurnover = $this->turnoverRatio(
            $totalInventoryValue,
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
                'total_item_count' => $periodProducts->filter(fn (Product $product) => $this->stockQuantity($product, $stockQuantities) > 0)->count(),
                'total_stock_quantity' => (float) $periodProducts->sum(fn (Product $product) => $this->stockQuantity($product, $stockQuantities)),
                'below_reorder_count' => $belowReorder->count(),
                'stagnant_count' => $stagnantStandalone->count(),
                'avg_turnover_ratio' => (float) $overallTurnover,
                'avg_holding_days' => $this->holdingDays($overallTurnover, $from, $to),
            ],
            'categoryBreakdown' => $categoryBuckets->values(),
            'monthlyMovement' => $monthlyMovement,
            'monthlyMovementByCategory' => $monthlyMovementByCategory,
            'belowReorderItems' => $this->mapProductRows($belowReorder->sortBy(fn (Product $product) => $this->stockQuantity($product, $stockQuantities))->take(15), $inventoryBalances, $stockQuantities),
            'stagnantItems' => $this->mapStagnantRows($stagnantStandalone->take(15), $lastMovementByProduct, $inventoryBalances, $stockQuantities, $to),
            'topSellers' => $topSellers,
            'statusFilteredItems' => $this->mapProductRows($statusFiltered->take(15), $inventoryBalances, $stockQuantities),
            'alerts' => $this->alerts($belowReorder, $stagnantStandalone, $itemsInPeriod->isEmpty()),
            'stagnant_days' => self::STAGNANT_DAYS,
        ];
    }

    private function normalizeFilters(array $raw): array
    {
        $period = in_array($raw['period'] ?? null, [self::PERIOD_MONTH, self::PERIOD_QUARTER, self::PERIOD_YEAR], true)
            ? $raw['period']
            : self::PERIOD_YEAR;

        $categoryId = isset($raw['category_id']) && (int) $raw['category_id'] > 0
            ? (int) $raw['category_id']
            : null;

        $status = in_array($raw['status'] ?? null, [self::STATUS_BELOW_REORDER, self::STATUS_STAGNANT, self::STATUS_NORMAL], true)
            ? $raw['status']
            : null;

        return [
            'period' => $period,
            'category_id' => $categoryId,
            'status' => $status,
        ];
    }

    private function periodRange(string $period, Carbon $fiscalStart, Carbon $fiscalEnd): array
    {
        if ($period === self::PERIOD_YEAR) {
            return [$fiscalStart->copy(), $fiscalEnd->copy()];
        }

        $to = Carbon::now()->endOfDay()->max($fiscalStart)->min($fiscalEnd);
        $from = match ($period) {
            self::PERIOD_MONTH => $to->copy()->subDays(29)->startOfDay(),
            self::PERIOD_QUARTER => $to->copy()->subDays(89)->startOfDay(),
            default => $fiscalStart->copy(),
        };

        return [$from->max($fiscalStart), $to];
    }

    private function productsQuery(array $filters): Builder
    {
        return Product::query()
            ->when($filters['category_id'], fn (Builder $q, int $id) => $q->where('group', $id))
            ->orderBy('code');
    }

    private function invoiceItemsBetween(Carbon $from, Carbon $to, ?int $categoryId): Collection
    {
        return InvoiceItem::query()
            ->where('itemable_type', Product::class)
            ->whereHas('invoice', function (Builder $q) use ($from, $to) {
                $q->whereIn('status', InvoiceStatus::approvedOrSettled())
                    ->whereIn('invoice_type', array_merge(self::STOCK_IN_TYPES, self::STOCK_OUT_TYPES))
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            })
            ->when($categoryId, function (Builder $q, int $id) {
                $q->whereHasMorph('itemable', Product::class, fn (Builder $p) => $p->where('group', $id));
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

    private function lastMovementDates(Carbon $fiscalStart, Carbon $to, ?int $categoryId): array
    {
        return InvoiceItem::query()
            ->selectRaw('invoice_items.itemable_id as product_id, MAX(invoices.date) as last_date')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.itemable_type', Product::class)
            ->whereIn('invoices.status', array_map(fn (InvoiceStatus $s) => $s->value, InvoiceStatus::approvedOrSettled()))
            ->whereIn('invoices.invoice_type', array_map(
                fn (InvoiceType $type) => $type->value,
                [InvoiceType::BEGINNING_INVENTORY, ...self::STOCK_IN_TYPES, ...self::STOCK_OUT_TYPES]
            ))
            ->where('invoices.company_id', getActiveCompany())
            ->whereBetween('invoices.date', [$fiscalStart->toDateString(), $to->toDateString()])
            ->when($categoryId, function ($q, int $id) {
                $q->join('products', 'products.id', '=', 'invoice_items.itemable_id')
                    ->where('products.group', $id);
            })
            ->groupBy('invoice_items.itemable_id')
            ->pluck('last_date', 'product_id')
            ->all();
    }

    private function bucketByCategory(Collection $products, array $movementMap, Collection $productGroups, array $inventoryBalances, array $stockQuantities): Collection
    {
        $byGroupId = $products->groupBy(fn (Product $p) => (int) ($p->group ?? 0));
        $groupNames = $productGroups->keyBy('id');

        return $byGroupId
            ->map(function (Collection $groupProducts, int $groupId) use ($movementMap, $groupNames, $inventoryBalances, $stockQuantities) {
                $value = (float) $groupProducts->sum(fn (Product $product) => $this->inventoryValue($product, $inventoryBalances));
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
                    'item_count' => $groupProducts->filter(fn (Product $product) => $this->stockQuantity($product, $stockQuantities) > 0)->count(),
                    'inventory_value' => $value,
                    'units_in' => $unitsIn,
                    'units_out' => $unitsOut,
                    'cogs_period' => $cogsPeriod,
                    'turnover_ratio' => $turnover,
                ];
            })
            ->filter(fn (array $bucket) => $bucket['item_count'] > 0
                || $bucket['inventory_value'] != 0.0
                || $bucket['units_in'] != 0.0
                || $bucket['units_out'] != 0.0
                || $bucket['cogs_period'] != 0.0)
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

    private function isBelowReorder(Product $product, array $stockQuantities): bool
    {
        $warning = $product->quantity_warning;
        if ($warning === null || (float) $warning <= 0) {
            return false;
        }

        return $this->stockQuantity($product, $stockQuantities) <= (float) $warning;
    }

    private function stagnantProducts(Collection $products, array $lastMovementByProduct, array $stockQuantities, Carbon $to): Collection
    {
        $threshold = $to->copy()->subDays(self::STAGNANT_DAYS);

        return $products->filter(function (Product $product) use ($lastMovementByProduct, $stockQuantities, $threshold) {
            if ($this->stockQuantity($product, $stockQuantities) <= 0) {
                return false;
            }

            $lastRaw = $lastMovementByProduct[$product->id] ?? null;
            if ($lastRaw === null) {
                return true;
            }

            return Carbon::parse($lastRaw)->lt($threshold);
        })->values();
    }

    private function applyStatusFilter(Collection $products, Collection $belowReorder, Collection $stagnant, ?string $status, array $stockQuantities): Collection
    {
        return match ($status) {
            self::STATUS_BELOW_REORDER => $belowReorder->values(),
            self::STATUS_STAGNANT => $stagnant->values(),
            self::STATUS_NORMAL => $products
                ->reject(fn (Product $product) => $this->isBelowReorder($product, $stockQuantities) || $stagnant->contains('id', $product->id))
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
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $buckets = [];

        while ($cursor->lte($end)) {
            $key = $this->jalaliMonthKey($cursor);
            $buckets[$key] ??= ['in' => 0.0, 'out' => 0.0];
            $cursor->addDay();
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

    private function mapProductRows(Collection $products, array $inventoryBalances, array $stockQuantities): Collection
    {
        return $products->map(fn (Product $p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'group' => $p->productGroup?->name ?? '-',
            'quantity' => $this->stockQuantity($p, $stockQuantities),
            'quantity_warning' => (float) ($p->quantity_warning ?? 0),
            'average_cost' => (float) $p->average_cost,
            'inventory_value' => $this->inventoryValue($p, $inventoryBalances),
        ])->values();
    }

    private function mapStagnantRows(Collection $products, array $lastMovementByProduct, array $inventoryBalances, array $stockQuantities, Carbon $to): Collection
    {
        return $products->map(function (Product $p) use ($lastMovementByProduct, $inventoryBalances, $stockQuantities, $to) {
            $last = $lastMovementByProduct[$p->id] ?? null;
            $lastCarbon = $last ? Carbon::parse($last) : null;

            return [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'group' => $p->productGroup?->name ?? '-',
                'quantity' => $this->stockQuantity($p, $stockQuantities),
                'inventory_value' => $this->inventoryValue($p, $inventoryBalances),
                'last_movement' => $lastCarbon,
                'days_idle' => $lastCarbon ? $lastCarbon->diffInDays($to) : null,
            ];
        })->values();
    }

    private function alerts(Collection $belowReorder, Collection $stagnant, bool $noMovement): array
    {
        return [
            [
                'title' => $belowReorder->isNotEmpty()
                    ? __(':count item(s) were at or below their reorder point at period end', ['count' => formatNumber($belowReorder->count())])
                    : __('All stock levels were above their reorder points at period end'),
                'description' => $belowReorder->isNotEmpty()
                    ? __('Review the below-reorder table and trigger purchase orders.')
                    : __('Nothing needed replenishment at the selected period end.'),
                'tone' => $belowReorder->isNotEmpty() ? 'warning' : 'success',
            ],
            [
                'title' => $stagnant->isNotEmpty()
                    ? __(':count item(s) had no movement for :days days as of period end', ['count' => formatNumber($stagnant->count()), 'days' => formatNumber(self::STAGNANT_DAYS)])
                    : __('No stagnant inventory at period end'),
                'description' => $stagnant->isNotEmpty()
                    ? __('Consider discounts, bundles, or write-offs for these items.')
                    : __('No item was stagnant at the selected period end.'),
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
            self::PERIOD_YEAR => __('Fiscal year'),
        ];
    }

    private function inventoryBalances(Collection $products, Carbon $fiscalStart, Carbon $to): array
    {
        $subjectIds = $products->pluck('inventory_subject_id')->filter()->unique()->values()->all();

        if ($subjectIds === []) {
            return [];
        }

        return Transaction::query()
            ->whereIn('subject_id', $subjectIds)
            ->join('documents', 'documents.id', '=', 'transactions.document_id')
            ->whereBetween('documents.date', [$fiscalStart->toDateString(), $to->toDateString()])
            ->selectRaw('subject_id, SUM(value) as total')
            ->groupBy('subject_id')
            ->pluck('total', 'subject_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function inventoryValue(Product $product, array $inventoryBalances): float
    {
        return abs((float) ($inventoryBalances[$product->inventory_subject_id] ?? 0.0));
    }

    private function stockQuantities(Collection $products, Carbon $fiscalStart, Carbon $to): array
    {
        $productIds = $products->pluck('id')->all();

        if ($productIds === []) {
            return [];
        }

        $incomingTypes = [InvoiceType::BEGINNING_INVENTORY, ...self::STOCK_IN_TYPES];
        $movementTypes = [...$incomingTypes, ...self::STOCK_OUT_TYPES];
        $incomingPlaceholders = implode(', ', array_fill(0, count($incomingTypes), '?'));

        return InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoice_items.itemable_type', Product::class)
            ->whereIn('invoice_items.itemable_id', $productIds)
            ->where('invoices.company_id', getActiveCompany())
            ->whereIn('invoices.status', array_map(fn (InvoiceStatus $status) => $status->value, InvoiceStatus::approvedOrSettled()))
            ->whereIn('invoices.invoice_type', array_map(fn (InvoiceType $type) => $type->value, $movementTypes))
            ->whereBetween('invoices.date', [$fiscalStart->toDateString(), $to->toDateString()])
            ->selectRaw(
                "invoice_items.itemable_id as product_id, SUM(CASE WHEN invoices.invoice_type IN ({$incomingPlaceholders}) THEN invoice_items.quantity ELSE -invoice_items.quantity END) as total",
                array_map(fn (InvoiceType $type) => $type->value, $incomingTypes)
            )
            ->groupBy('invoice_items.itemable_id')
            ->pluck('total', 'product_id')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();
    }

    private function stockQuantity(Product $product, array $stockQuantities): float
    {
        return (float) ($stockQuantities[$product->id] ?? 0.0);
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

    public function report(array $rawFilters = []): array
    {
        $name = trim((string) ($rawFilters['name'] ?? ''));
        $groupName = trim((string) ($rawFilters['group_name'] ?? ''));
        $minQuantity = is_numeric($rawFilters['min_quantity'] ?? null) ? (float) $rawFilters['min_quantity'] : null;
        $needOrder = filter_var($rawFilters['need_order'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $columns = $this->normalizeColumns($rawFilters);

        $products = Product::query()->orderBy('code')->when($name !== '', fn (Builder $q) => $q->where('name', 'like', '%'.$name.'%'))
            ->when($groupName !== '', fn (Builder $q) => $q->whereHas(
                'productGroup',
                fn (Builder $g) => $g->where('name', 'like', '%'.$groupName.'%')
            ))
            ->when($minQuantity !== null, fn (Builder $q) => $q->where('quantity', '>=', $minQuantity))
            ->when($needOrder, fn (Builder $q) => $q->where('quantity_warning', '>', 0)->whereColumn('quantity', '<=', 'quantity_warning'))
            ->with(['productGroup:id,name', 'incomeSubject:id', 'cogsSubject:id', 'inventorySubject:id', 'salesReturnsSubject:id'])
            ->get();

        $warehouses = Warehouse::query()->orderBy('name')->get();
        $warehouseStocks = WarehouseProductStock::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->get()
            ->groupBy('product_id');

        [$fyStart, $now] = $this->fiscalYearToDate();
        $movement = $this->fiscalYearMovement($fyStart, $now, $products->pluck('id')->all());
        $subjectTotals = $this->subjectTransactionTotals($products);
        $needsLastCost = in_array('last_item_cost', $columns, true);

        $rows = $products->map(function (Product $p) use ($movement, $subjectTotals, $needsLastCost, $warehouses, $warehouseStocks) {
            $m = $movement[$p->id] ?? ['in' => 0.0, 'out' => 0.0];
            $revenue = abs($subjectTotals[$p->income_subject_id] ?? 0.0);
            $cogs = abs($subjectTotals[$p->cogs_subject_id] ?? 0.0);
            $inventory = abs($subjectTotals[$p->inventory_subject_id] ?? 0.0);
            $salesReturn = abs($subjectTotals[$p->sales_returns_subject_id] ?? 0.0);

            $row = [
                'id' => $p->id,
                'name' => $p->name,
                'inbound' => $m['in'],
                'outbound' => $m['out'],
                'stock' => (float) $p->quantity,
                'category' => $p->productGroup?->name ?? '-',
                'code' => $p->code,
                'selling_price' => (float) $p->selling_price,
                'cost_of_goods' => (float) $p->average_cost,
                'last_item_cost' => $needsLastCost ? (float) $this->productService->lastApprovedBuyInvoiceItemCOG($p) : 0.0,
                'sales_profit' => $revenue - $cogs,
                'revenue_account' => $revenue,
                'cogs_account' => $cogs,
                'inventory_account' => $inventory,
                'sales_return_account' => $salesReturn,
            ];

            foreach ($warehouses as $warehouse) {
                $row['warehouse_'.$warehouse->id] = (float) ($warehouseStocks->get($p->id)?->firstWhere('warehouse_id', $warehouse->id)?->quantity ?? 0);
            }

            return $row;
        })->values();

        $data = [
            'columns' => $columns,
            'columnLabels' => [
                ...$this->columnLabels(),
                ...$warehouses->mapWithKeys(fn (Warehouse $warehouse) => ['warehouse_'.$warehouse->id => $warehouse->name])->all(),
            ],
            'rows' => $rows,
            'filterSummary' => $this->reportFilterSummary($name, $groupName, $minQuantity, $needOrder, $fyStart, $now),
            'company' => Company::find(getActiveCompany()),
            'logo' => $this->reportLogo(),
            'generatedAtDate' => toEnglish(jdate('Y/m/d', $now->timestamp)),
            'generatedAtTime' => toEnglish(jdate('H:i', $now->timestamp)),
        ];

        $layout = $this->reportColumnLayout($data['columns'], $warehouses->pluck('id')->all());
        $data = array_merge($data, $layout);
        $totals = $this->reportTotals($data['rows'], $layout['numeric']);
        $data['totalRow'] = $this->reportTotalRow($layout['visible'], $totals, $layout['addDesc']);

        return $data;
    }

    private function reportColumnLayout(array $columns, array $warehouseIds = []): array
    {
        $order = [
            'name', 'code', 'category', 'inbound', 'outbound', 'stock',
            'selling_price', 'cost_of_goods', 'last_item_cost', 'sales_profit',
            'revenue_account', 'cogs_account', 'inventory_account', 'sales_return_account',
        ];
        $fixed = ['name'];
        $numeric = [
            'inbound', 'outbound', 'stock', 'selling_price', 'cost_of_goods',
            'last_item_cost', 'sales_profit', 'revenue_account', 'cogs_account',
            'inventory_account', 'sales_return_account',
        ];

        $visible = array_values(array_filter(
            $order,
            fn ($c) => in_array($c, $fixed, true) || in_array($c, $columns, true),
        ));

        $warehouseColumns = array_map(fn (int $id) => 'warehouse_'.$id, $warehouseIds);
        $visible = array_merge($visible, $warehouseColumns);
        $numeric = array_merge($numeric, $warehouseColumns);

        $count = count($visible);
        $totalTriggerColumns = ['sales_profit', 'revenue_account', 'cogs_account', 'inventory_account', 'sales_return_account'];
        $showTotalRow = ! empty(array_intersect($totalTriggerColumns, $visible));

        return [
            'showTotalRow' => $showTotalRow,
            'visible' => $visible,
            'numeric' => $numeric,
            'addDesc' => $count < 9,
            'portrait' => $count < 6,
        ];
    }

    private function reportTotals(Collection $rows, array $numeric): array
    {
        $perUnit = ['selling_price', 'cost_of_goods', 'last_item_cost'];
        $totals = [];

        foreach ($numeric as $col) {
            if (in_array($col, $perUnit, true)) {
                continue;
            }

            $totals[$col] = (float) $rows->sum($col);
        }

        return $totals;
    }

    private function reportTotalRow(array $visible, array $totals, bool $addDesc): array
    {
        $slots = array_merge(['index'], $visible, $addDesc ? ['desc'] : []);

        $segments = [];
        $emptyRun = 0;
        $labelUsed = false;

        $flush = function () use (&$segments, &$emptyRun, &$labelUsed) {
            if ($emptyRun === 0) {
                return;
            }

            $segments[] = [
                'type' => 'merge',
                'colspan' => $emptyRun,
                'label' => $labelUsed ? '' : __('Total'),
            ];
            $labelUsed = true;
            $emptyRun = 0;
        };

        foreach ($slots as $slot) {
            if (array_key_exists($slot, $totals)) {
                $flush();
                $segments[] = ['type' => 'value', 'col' => $slot, 'value' => $totals[$slot]];
            } else {
                $emptyRun++;
            }
        }

        $flush();

        return $segments;
    }

    private function normalizeColumns(array $raw): array
    {
        $available = [...self::OPTIONAL_COLUMNS, ...$this->warehouseColumnKeys()];
        if (! isset($raw['cols_submitted'])) {
            return $available;
        }

        $requested = (array) ($raw['columns'] ?? []);

        return array_values(array_intersect($available, $requested));
    }

    private function warehouseColumnKeys(): array
    {
        return Warehouse::query()->orderBy('name')->pluck('id')->map(fn (int $id) => 'warehouse_'.$id)->all();
    }

    private function columnLabels(): array
    {
        return [
            'name' => __('Product name'),
            'inbound' => __('Inbound'),
            'outbound' => __('Outbound'),
            'stock' => __('Stock'),
            'category' => __('Category'),
            'code' => __('Product code'),
            'selling_price' => __('Sale price'),
            'cost_of_goods' => __('Cost of goods'),
            'last_item_cost' => __('Last item cost'),
            'sales_profit' => __('Sales profit'),
            'revenue_account' => __('Revenue account amount'),
            'cogs_account' => __('COGS account amount'),
            'inventory_account' => __('Inventory account amount'),
            'sales_return_account' => __('Sales return account amount'),
        ];
    }

    private function fiscalYearToDate(): array
    {
        $company = Company::withoutGlobalScopes()->findOrFail(getActiveCompany());
        [$from, $fiscalEnd] = $company->fiscalYearRange();
        $to = Carbon::now()->endOfDay();

        if ($to->greaterThan($fiscalEnd)) {
            $to = $fiscalEnd;
        }

        return [$from, $to];
    }

    private function fiscalYearMovement(Carbon $from, Carbon $to, array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $items = InvoiceItem::query()->where('itemable_type', Product::class)->whereIn('itemable_id', $productIds)
            ->whereHas('invoice', function (Builder $q) use ($from, $to) {
                $q->where('status', InvoiceStatus::APPROVED)
                    ->whereIn('invoice_type', array_merge(self::STOCK_IN_TYPES, self::STOCK_OUT_TYPES))
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            })->with('invoice:id,invoice_type')->get(['id', 'invoice_id', 'itemable_id', 'quantity']);

        $map = [];

        foreach ($items as $item) {
            $productId = (int) $item->itemable_id;
            $map[$productId] ??= ['in' => 0.0, 'out' => 0.0];

            $type = $item->invoice->invoice_type;
            $qty = (float) $item->quantity;

            if (in_array($type, self::STOCK_IN_TYPES, true)) {
                $map[$productId]['in'] += $qty;
            } elseif (in_array($type, self::STOCK_OUT_TYPES, true)) {
                $map[$productId]['out'] += $qty;
            }
        }

        return $map;
    }

    private function subjectTransactionTotals(Collection $products): array
    {
        $subjectIds = $products->flatMap(fn (Product $p) => [
            $p->income_subject_id,
            $p->cogs_subject_id,
            $p->inventory_subject_id,
            $p->sales_returns_subject_id,
        ])->filter()->unique()->values()->all();

        if (empty($subjectIds)) {
            return [];
        }

        return Transaction::query()->whereIn('subject_id', $subjectIds)->selectRaw('subject_id, SUM(value) as total')
            ->groupBy('subject_id')->pluck('total', 'subject_id')->map(fn ($value) => (float) $value)->all();
    }

    private function reportFilterSummary(string $name, string $groupName, ?float $minQuantity, bool $needOrder, Carbon $from, Carbon $to): array
    {
        $fromJ = toEnglish(jdate('Y/m/d', $from->timestamp));
        $toJ = toEnglish(jdate('Y/m/d', $to->timestamp));

        $summary = [
            ['label' => __('Product Name'), 'value' => $name !== '' ? $name : __('All Products')],
            ['label' => __('Product Group Name'), 'value' => $groupName !== '' ? $groupName : __('All Groups')],
        ];

        if ($minQuantity !== null) {
            $summary[] = ['label' => __('Min quantity'), 'value' => formatNumber($minQuantity)];
        }

        if ($needOrder) {
            $summary[] = ['label' => __('Need Order'), 'value' => __('Yes')];
        }

        $summary[] = ['label' => __('Period'), 'value' => localizeNumber($fromJ).' - '.localizeNumber($toJ)];

        return $summary;
    }

    private function reportLogo(): ?string
    {
        $company = Company::find(getActiveCompany());

        $candidates = [];
        if ($company?->logo) {
            $candidates[] = storage_path('app/public/'.$company->logo);
        }
        $candidates[] = public_path('images/logo.png');

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $mime = str_ends_with(strtolower($path), '.svg') ? 'image/svg+xml' : 'image/png';

                return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
            }
        }

        return null;
    }
}
