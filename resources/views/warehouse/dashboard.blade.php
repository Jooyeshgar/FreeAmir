@php
    $selectedCategoryId = $filters['category_id'] ?? null;
    $period = $filters['period'] ?? 'year';
    $statusFilter = $filters['status'] ?? null;
    $categoryLinkParams = array_filter([
        'group_name' => $selectedCategoryId
            ? optional($productGroups->firstWhere('id', $selectedCategoryId))->name
            : null,
    ]);

    $flatLine = [1, 1];

    $summaryCards = [
        [
            'title' => __('Total Inventory Value'),
            'value' => $summary['total_inventory_value'],
            'suffix' => __('Rial'),
            'detail' => __('Based on average cost'),
            'tone' => 'info',
            'series' => collect($monthlyMovement['in'] ?? [])
                ->zip($monthlyMovement['out'] ?? [])
                ->map(fn ($pair) => ($pair[0] ?? 0) - ($pair[1] ?? 0))
                ->all(),
        ],
        [
            'title' => __('Items in Stock'),
            'value' => $summary['total_item_count'],
            'suffix' => __('Items'),
            'detail' => __('Total quantity: :qty', ['qty' => formatNumber($summary['total_stock_quantity'])]),
            'tone' => 'primary',
            'series' => $flatLine,
        ],
        [
            'title' => __('Below Reorder Point'),
            'value' => $summary['below_reorder_count'],
            'suffix' => __('Products'),
            'detail' => __('Needs replenishment'),
            'tone' => $summary['below_reorder_count'] > 0 ? 'warning' : 'success',
            'series' => $flatLine,
        ],
        [
            'title' => __('Stagnant Items'),
            'value' => $summary['stagnant_count'],
            'suffix' => __('Products'),
            'detail' => __('No movement in :days days', ['days' => formatNumber($stagnant_days)]),
            'tone' => $summary['stagnant_count'] > 0 ? 'error' : 'success',
            'series' => $flatLine,
        ],
        [
            'title' => __('Inventory Turnover'),
            'value' => formatNumber(round($summary['avg_turnover_ratio'], 2)),
            'suffix' => __('Times'),
            'detail' => __('Avg holding: :days days', ['days' => formatNumber($summary['avg_holding_days'])]),
            'tone' => 'secondary',
            'series' => $monthlyMovement['out'] ?? [],
        ],
        [
            'title' => __('Period Outflow'),
            'value' => array_sum($monthlyMovement['out'] ?? [0]),
            'suffix' => __('Items'),
            'detail' => __('Period Inflow: :qty', ['qty' => formatNumber(array_sum($monthlyMovement['in'] ?? [0]))]),
            'tone' => 'success',
            'series' => $monthlyMovement['out'] ?? [],
        ],
    ];
@endphp

<x-app-layout :title="__('Warehouse Dashboard')">
    <x-show-message-bags />

    <main class="mt-8 space-y-4">
        <section class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-base-content">{{ __('Warehouse Dashboard') }}</h1>
                <p class="mt-1 text-sm text-base-content/60">
                    {{ __('Inventory, movement, and performance KPIs - :period', ['period' => $periodLabel]) }}
                </p>
            </div>

            <form action="{{ route('warehouse.dashboard') }}" method="GET" class="flex flex-wrap items-end gap-2">
                <label class="form-control w-40">
                    <span class="label-text mb-1 text-xs">{{ __('Time Period') }}</span>
                    <select name="period" class="select select-sm select-bordered">
                        @foreach ($periodOptions as $value => $label)
                            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control w-56">
                    <span class="label-text mb-1 text-xs">{{ __('Product Category') }}</span>
                    <select name="category_id" class="select select-sm select-bordered">
                        <option value="">{{ __('All Categories') }}</option>
                        @foreach ($productGroups as $group)
                            <option value="{{ $group->id }}" @selected((int) $selectedCategoryId === (int) $group->id)>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control w-44">
                    <span class="label-text mb-1 text-xs">{{ __('Inventory Status') }}</span>
                    <select name="status" class="select select-sm select-bordered">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <button type="submit" class="btn btn-sm btn-neutral">{{ __('Apply') }}</button>
                <a href="{{ route('warehouse.dashboard') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>

                @can('products.index')
                    <a href="{{ route('products.index') }}" class="btn btn-sm btn-info">{{ __('Products List') }}</a>
                @endcan
            </form>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @foreach ($summaryCards as $card)
                <x-metric-card :card="$card" />
            @endforeach
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Inventory Value by Category') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Share of total stock value (at average cost)') }}</p>
                    <div class="mt-3 h-64">
                        <canvas id="inventoryValueChart" class="h-full w-full"></canvas>
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Turnover Ratio by Category') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Period COGS divided by current inventory value') }}</p>
                    <div class="mt-3 h-64">
                        <canvas id="turnoverChart" class="h-full w-full"></canvas>
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Items per Category') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('How many distinct products live in each category') }}</p>
                    <div class="mt-3 h-64">
                        <canvas id="itemsPerCategoryChart" class="h-full w-full"></canvas>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(20rem,0.8fr)]">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="card-title text-base">{{ __('Inbound / Outbound Trend') }}</h2>
                            <p class="text-xs text-base-content/55">{{ __('Monthly approved warehouse movement') }}</p>
                        </div>
                    </div>
                    <div class="mt-3 h-72">
                        <canvas id="movementTrendChart" class="h-full w-full"></canvas>
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Alerts & Reminders') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Items that need attention') }}</p>
                    <div class="mt-3 space-y-3">
                        @foreach ($alerts as $alert)
                            @php
                                $alertClass = match ($alert['tone']) {
                                    'warning' => 'bg-warning/10 text-warning',
                                    'info' => 'bg-info/10 text-info',
                                    'success' => 'bg-success/10 text-success',
                                    'error' => 'bg-error/10 text-error',
                                    default => 'bg-base-200 text-base-content/70',
                                };
                            @endphp
                            <div class="flex items-center gap-3 rounded-lg bg-base-200/70 p-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $alertClass }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                                    </svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold">{{ $alert['title'] }}</div>
                                    <div class="truncate text-xs text-base-content/55">{{ $alert['description'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="card-title text-base">{{ __('Inbound / Outbound by Main Categories') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Top categories tracked across the selected period') }}</p>
                    </div>
                </div>
                <div class="mt-3 h-80">
                    <canvas id="categoryTrendChart" class="h-full w-full"></canvas>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-0">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 p-4">
                        <div>
                            <h2 class="card-title text-base">{{ __('Below Reorder Point') }}</h2>
                            <p class="text-xs text-base-content/55">{{ __('Lowest stock items first') }}</p>
                        </div>
                        @can('products.index')
                            <a href="{{ route('products.index', $categoryLinkParams) }}" class="btn btn-xs btn-outline">{{ __('Open in Products') }}</a>
                        @endcan
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-right">{{ __('Quantity') }}</th>
                                    <th class="text-right">{{ __('Reorder Point') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($belowReorderItems as $row)
                                    <tr>
                                        <td>{{ convertToFarsi($row['code']) }}</td>
                                        <td>
                                            @can('products.index')
                                                <a class="link link-primary" href="{{ route('products.show', $row['id']) }}">{{ $row['name'] }}</a>
                                            @else
                                                {{ $row['name'] }}
                                            @endcan
                                        </td>
                                        <td>
                                            @can('products.index')
                                                <a class="link link-hover" href="{{ route('products.index', ['group_name' => $row['group']]) }}">{{ $row['group'] }}</a>
                                            @else
                                                {{ $row['group'] }}
                                            @endcan
                                        </td>
                                        <td class="text-right font-medium {{ $row['quantity'] < 0 ? 'text-error' : 'text-warning' }}">{{ formatNumber($row['quantity']) }}</td>
                                        <td class="text-right">{{ formatNumber($row['quantity_warning']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-base-content/55">{{ __('All items are above their reorder points.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-0">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 p-4">
                        <div>
                            <h2 class="card-title text-base">{{ __('Stagnant Items') }}</h2>
                            <p class="text-xs text-base-content/55">{{ __('No movement for at least :days days', ['days' => formatNumber($stagnant_days)]) }}</p>
                        </div>
                        @can('products.index')
                            <a href="{{ route('products.index', $categoryLinkParams) }}" class="btn btn-xs btn-outline">{{ __('Open in Products') }}</a>
                        @endcan
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-right">{{ __('Quantity') }}</th>
                                    <th class="text-right">{{ __('Days Idle') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($stagnantItems as $row)
                                    <tr>
                                        <td>{{ convertToFarsi($row['code']) }}</td>
                                        <td>
                                            @can('products.index')
                                                <a class="link link-primary" href="{{ route('products.show', $row['id']) }}">{{ $row['name'] }}</a>
                                            @else
                                                {{ $row['name'] }}
                                            @endcan
                                        </td>
                                        <td>{{ $row['group'] }}</td>
                                        <td class="text-right font-medium">{{ formatNumber($row['quantity']) }}</td>
                                        <td class="text-right">{{ $row['days_idle'] === null ? __('Never') : formatNumber($row['days_idle']) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-base-content/55">{{ __('No stagnant inventory in the selected scope.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body p-0">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 p-4">
                    <div>
                        <h2 class="card-title text-base">{{ __('Top 10 Best Sellers') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Approved sells minus returns, within the selected period') }}</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('Category') }}</th>
                                <th class="text-right">{{ __('Net Units') }}</th>
                                <th class="text-right">{{ __('Net Revenue') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topSellers as $row)
                                <tr>
                                    <td>{{ convertToFarsi($row['code']) }}</td>
                                    <td>
                                        @can('products.index')
                                            <a class="link link-primary" href="{{ route('products.show', $row['id']) }}">{{ $row['name'] }}</a>
                                        @else
                                            {{ $row['name'] }}
                                        @endcan
                                    </td>
                                    <td>
                                        @can('products.index')
                                            <a class="link link-hover" href="{{ route('products.index', ['group_name' => $row['group']]) }}">{{ $row['group'] }}</a>
                                        @else
                                            {{ $row['group'] }}
                                        @endcan
                                    </td>
                                    <td class="text-right font-medium">{{ formatNumber($row['units']) }}</td>
                                    <td class="text-right">{{ formatNumber($row['revenue']) }} {{ __('Rial') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-base-content/55">{{ __('No sales found in the selected period.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @if ($statusFilter)
            <section class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-0">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 p-4">
                        <div>
                            <h2 class="card-title text-base">{{ __('Filtered: :label', ['label' => $statusOptions[$statusFilter] ?? $statusFilter]) }}</h2>
                            <p class="text-xs text-base-content/55">{{ __('Items matching the current inventory status filter') }}</p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>{{ __('Code') }}</th>
                                    <th>{{ __('Product') }}</th>
                                    <th>{{ __('Category') }}</th>
                                    <th class="text-right">{{ __('Quantity') }}</th>
                                    <th class="text-right">{{ __('Inventory Value') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($statusFilteredItems as $row)
                                    <tr>
                                        <td>{{ convertToFarsi($row['code']) }}</td>
                                        <td>
                                            @can('products.index')
                                                <a class="link link-primary" href="{{ route('products.show', $row['id']) }}">{{ $row['name'] }}</a>
                                            @else
                                                {{ $row['name'] }}
                                            @endcan
                                        </td>
                                        <td>{{ $row['group'] }}</td>
                                        <td class="text-right font-medium">{{ formatNumber($row['quantity']) }}</td>
                                        <td class="text-right">{{ formatNumber($row['inventory_value']) }} {{ __('Rial') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-base-content/55">{{ __('No items match the selected status.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    </main>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const breakdown = @json($categoryBreakdown);
                const movement = @json($monthlyMovement);
                const categoryMovement = @json($monthlyMovementByCategory);
                const palette = ['#38bdf8', '#34d399', '#fbbf24', '#fb7185', '#a78bfa', '#22d3ee', '#f97316', '#10b981'];
                const numberFormatter = (value) => {
                    const locale = document.documentElement.lang === 'fa' ? 'fa-IR' : 'en-US';
                    return new Intl.NumberFormat(locale, { maximumFractionDigits: 0 }).format(value);
                };

                let charts = {};

                const render = () => {
                    if (!window.Chart) return;

                    const theme = window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                        textColor: '#475569',
                        mutedTextColor: '#64748b',
                        gridColor: 'rgba(148, 163, 184, 0.24)',
                    };

                    Object.values(charts).forEach((c) => c?.destroy());
                    charts = {};

                    const inventoryCanvas = document.getElementById('inventoryValueChart');
                    if (inventoryCanvas && breakdown.length > 0) {
                        charts.inventory = new Chart(inventoryCanvas, {
                            type: 'doughnut',
                            data: {
                                labels: breakdown.map((b) => b.name),
                                datasets: [{
                                    data: breakdown.map((b) => b.inventory_value),
                                    backgroundColor: breakdown.map((_, i) => palette[i % palette.length]),
                                    borderColor: 'transparent',
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '60%',
                                plugins: {
                                    legend: { position: 'bottom', labels: { color: theme.textColor, boxWidth: 10 } },
                                    tooltip: {
                                        callbacks: {
                                            label: (ctx) => `${ctx.label}: ${numberFormatter(ctx.parsed)}`,
                                        },
                                    },
                                },
                            },
                        });
                    }

                    const turnoverCanvas = document.getElementById('turnoverChart');
                    if (turnoverCanvas && breakdown.length > 0) {
                        charts.turnover = new Chart(turnoverCanvas, {
                            type: 'bar',
                            data: {
                                labels: breakdown.map((b) => b.name),
                                datasets: [{
                                    label: @json(__('Turnover Ratio')),
                                    data: breakdown.map((b) => b.turnover_ratio),
                                    backgroundColor: '#34d399',
                                    borderRadius: 6,
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { ticks: { color: theme.mutedTextColor }, grid: { display: false } },
                                    y: { beginAtZero: true, ticks: { color: theme.mutedTextColor }, grid: { color: theme.gridColor } },
                                },
                            },
                        });
                    }

                    const itemsCanvas = document.getElementById('itemsPerCategoryChart');
                    if (itemsCanvas && breakdown.length > 0) {
                        charts.itemsPer = new Chart(itemsCanvas, {
                            type: 'bar',
                            data: {
                                labels: breakdown.map((b) => b.name),
                                datasets: [{
                                    label: @json(__('Items')),
                                    data: breakdown.map((b) => b.item_count),
                                    backgroundColor: '#38bdf8',
                                    borderRadius: 6,
                                }],
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { beginAtZero: true, ticks: { color: theme.mutedTextColor }, grid: { color: theme.gridColor } },
                                    y: { ticks: { color: theme.mutedTextColor }, grid: { display: false } },
                                },
                            },
                        });
                    }

                    const trendCanvas = document.getElementById('movementTrendChart');
                    if (trendCanvas && (movement.labels || []).length > 0) {
                        charts.movement = new Chart(trendCanvas, {
                            type: 'line',
                            data: {
                                labels: movement.labels,
                                datasets: [
                                    {
                                        label: @json(__('Inbound')),
                                        data: movement.in,
                                        borderColor: '#34d399',
                                        backgroundColor: 'rgba(52, 211, 153, 0.18)',
                                        fill: true,
                                        tension: 0.3,
                                    },
                                    {
                                        label: @json(__('Outbound')),
                                        data: movement.out,
                                        borderColor: '#fb7185',
                                        backgroundColor: 'rgba(251, 113, 133, 0.18)',
                                        fill: true,
                                        tension: 0.3,
                                    },
                                ],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'top', align: 'end', labels: { color: theme.textColor } } },
                                scales: {
                                    x: { ticks: { color: theme.mutedTextColor }, grid: { display: false } },
                                    y: { beginAtZero: true, ticks: { color: theme.mutedTextColor }, grid: { color: theme.gridColor } },
                                },
                            },
                        });
                    }

                    const categoryTrendCanvas = document.getElementById('categoryTrendChart');
                    if (categoryTrendCanvas && (categoryMovement.labels || []).length > 0 && (categoryMovement.datasets || []).length > 0) {
                        const datasets = categoryMovement.datasets.flatMap((d, idx) => {
                            const color = palette[idx % palette.length];
                            return [
                                {
                                    label: `${d.name} - ${@json(__('Inbound'))}`,
                                    data: d.in,
                                    borderColor: color,
                                    backgroundColor: 'transparent',
                                    borderDash: [],
                                    tension: 0.3,
                                },
                                {
                                    label: `${d.name} - ${@json(__('Outbound'))}`,
                                    data: d.out,
                                    borderColor: color,
                                    backgroundColor: 'transparent',
                                    borderDash: [4, 4],
                                    tension: 0.3,
                                },
                            ];
                        });

                        charts.categoryTrend = new Chart(categoryTrendCanvas, {
                            type: 'line',
                            data: { labels: categoryMovement.labels, datasets },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'bottom', labels: { color: theme.textColor, boxWidth: 10 } } },
                                scales: {
                                    x: { ticks: { color: theme.mutedTextColor }, grid: { display: false } },
                                    y: { beginAtZero: true, ticks: { color: theme.mutedTextColor }, grid: { color: theme.gridColor } },
                                },
                            },
                        });
                    }
                };

                render();
                window.addEventListener('theme:changed', render);
            });
        </script>
    @endpush
</x-app-layout>
