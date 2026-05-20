<x-app-layout :title="__('Warehouse Dashboard')">
    <x-show-message-bags />

    @php
        $localizedPeriodLabel = in_array(app()->getLocale(), ['fa', 'fa_IR'], true) ? convertToFarsi($periodLabel) : $periodLabel;
    @endphp

    <main class="warehouse-dashboard">
        <section class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <h1 class="warehouse-page-title">{{ __('Warehouse Dashboard') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Stock, movement, and sales overview for fiscal year :year', ['year' => $localizedPeriodLabel]) }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @can('products.index')
                    <a href="{{ route('products.index') }}" class="btn btn-sm btn-primary">{{ __('Products') }}</a>
                @endcan
                @can('services.index')
                    <a href="{{ route('services.index') }}" class="btn btn-sm btn-outline">{{ __('Services') }}</a>
                @endcan
                @can('ancillary-costs.index')
                    <a href="{{ route('ancillary-costs.index') }}" class="btn btn-sm btn-outline">{{ __('Ancillary Costs') }}</a>
                @endcan
            </div>
        </section>

        @php
            $salesUnitsSeries = collect($monthlySalesUnits['products'])
                ->map(fn ($units, $month) => $units + ($monthlySalesUnits['services'][$month] ?? 0))
                ->values()
                ->all();

            $operationalCards = [
                [
                    'title' => __('Products'),
                    'value' => $inventory['productsCount'],
                    'suffix' => __('Items'),
                    'detail' => __('Services') . ': ' . formatNumber($inventory['servicesCount']),
                    'tone' => 'info',
                    'series' => [$inventory['servicesCount'], $inventory['productsCount']],
                ],
                [
                    'title' => __('Stock on hand'),
                    'value' => $inventory['totalQuantity'],
                    'suffix' => __('Items'),
                    'detail' => __('Oversell') . ': ' . formatNumber($inventory['oversellEnabledCount']),
                    'tone' => 'success',
                    'series' => array_values($monthlyMovement['net']),
                ],
                [
                    'title' => __('Low stock'),
                    'value' => $inventory['lowStockCount'],
                    'suffix' => __('Products'),
                    'detail' => __('Warning limit'),
                    'tone' => 'warning',
                    'series' => [$inventory['productsCount'], $inventory['lowStockCount']],
                ],
                [
                    'title' => __('Negative stock'),
                    'value' => $inventory['negativeStockCount'],
                    'suffix' => __('Products'),
                    'detail' => __('Needs review'),
                    'tone' => 'error',
                    'series' => [$inventory['lowStockCount'], $inventory['negativeStockCount']],
                ],
                [
                    'title' => __('Net sold units'),
                    'value' => $sales['netProductUnits'] + $sales['netServiceUnits'],
                    'suffix' => __('Items'),
                    'detail' => __('Invoices') . ': ' . formatNumber($sales['approvedSellInvoices']),
                    'tone' => 'secondary',
                    'series' => $salesUnitsSeries,
                ],
                [
                    'title' => __('Pending work'),
                    'value' => $workflow['readyToApproveInvoices'] + $workflow['unapprovedInvoices'] + $workflow['unapprovedAncillaryCosts'],
                    'suffix' => __('Items'),
                    'detail' => __('Invoices and ancillary costs that still need approval'),
                    'tone' => 'primary',
                    'series' => [$workflow['readyToApproveInvoices'], $workflow['unapprovedInvoices'], $workflow['unapprovedAncillaryCosts']],
                ],
            ];
        @endphp

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @foreach ($operationalCards as $card)
                <x-metric-card :card="$card" />
            @endforeach
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article class="warehouse-panel xl:col-span-2">
                <div class="warehouse-panel-header">
                    <div>
                        <h2 class="warehouse-panel-title">{{ __('Warehouse movement') }}</h2>
                        <p class="warehouse-panel-subtitle">{{ __('Incoming Stock') }} / {{ __('Outgoing Stock') }} / {{ __('Net Movement') }}</p>
                    </div>
                </div>
                <div class="p-3">
                    <x-charts.bar-chart chart-id="warehouseMovementChart" heightClass="h-80" :show-legend="true" :datasets="[
                        ['label' => __('Incoming Stock'), 'data' => $monthlyMovement['incoming'], 'backgroundColor' => '#22c55e99', 'borderColor' => '#22c55e'],
                        ['label' => __('Outgoing Stock'), 'data' => $monthlyMovement['outgoing'], 'backgroundColor' => '#f43f5e99', 'borderColor' => '#f43f5e'],
                        ['label' => __('Net Movement'), 'data' => $monthlyMovement['net'], 'backgroundColor' => '#38bdf899', 'borderColor' => '#38bdf8', 'negativeColor' => '#fb718599'],
                    ]" />
                </div>
            </article>

            <article class="warehouse-panel">
                <div class="warehouse-panel-header">
                    <div>
                        <h2 class="warehouse-panel-title">{{ __('Sales flow') }}</h2>
                        <p class="warehouse-panel-subtitle">{{ __('Product units') }} / {{ __('Service units') }}</p>
                    </div>
                </div>
                <div class="p-3">
                    <x-charts.bar-chart chart-id="warehouseSalesUnitsChart" heightClass="h-80" :show-legend="true" :datasets="[
                        ['label' => __('Product units'), 'data' => $monthlySalesUnits['products'], 'backgroundColor' => '#0ea5e999', 'borderColor' => '#0ea5e9'],
                        ['label' => __('Service units'), 'data' => $monthlySalesUnits['services'], 'backgroundColor' => '#a855f799', 'borderColor' => '#a855f7'],
                    ]" />
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article class="warehouse-panel xl:col-span-2">
                <div class="warehouse-panel-header">
                    <div>
                        <h2 class="warehouse-panel-title">{{ __('Top selling items') }}</h2>
                        <p class="warehouse-panel-subtitle">{{ __('Net sold units') }}</p>
                    </div>
                </div>
                <div class="overflow-x-auto p-4">
                    <table class="warehouse-table">
                        <thead>
                            <tr>
                                <th>{{ __('Code') }}</th>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Quantity') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topSellingItems as $item)
                                <tr>
                                    <td>{{ convertToFarsi($item['code']) }}</td>
                                    <td>
                                        <a href="{{ route($item['route'], $item['id']) }}" class="warehouse-link">{{ $item['name'] }}</a>
                                    </td>
                                    <td>{{ $item['type'] }}</td>
                                    <td>{{ formatNumber($item['quantity']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-slate-500">{{ __('No selling activity yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="warehouse-panel">
                <div class="warehouse-panel-header">
                    <div>
                        <h2 class="warehouse-panel-title">{{ __('Low stock watchlist') }}</h2>
                        <p class="warehouse-panel-subtitle">{{ __('Current qty') }} / {{ __('Warning limit') }}</p>
                    </div>
                </div>
                <div class="space-y-3 p-4">
                    @forelse ($lowStockProducts as $product)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/70">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <a href="{{ route('products.show', $product['id']) }}" class="warehouse-link font-semibold">{{ $product['name'] }}</a>
                                    <div class="mt-1 text-xs text-slate-500">{{ convertToFarsi($product['code']) }} - {{ $product['group'] }}</div>
                                </div>
                                <span class="rounded-md bg-amber-500/15 px-2 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                    {{ formatNumber($product['quantity']) }} / {{ formatNumber($product['quantityWarning']) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-sm text-slate-500">{{ __('No low stock products found.') }}</div>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <article class="warehouse-panel">
                <div class="warehouse-panel-header">
                    <div>
                        <h2 class="warehouse-panel-title">{{ __('Workflow queue') }}</h2>
                        <p class="warehouse-panel-subtitle">{{ __('Pending work') }}</p>
                    </div>
                </div>
                <div class="space-y-4 p-4">
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span>{{ __('Ready to approve invoices') }}</span>
                            <span class="font-bold">{{ formatNumber($workflow['readyToApproveInvoices']) }}</span>
                        </div>
                        <progress class="progress progress-info w-full" value="{{ $workflow['readyToApproveInvoices'] }}" max="{{ max(1, $workflow['readyToApproveInvoices'] + $workflow['unapprovedInvoices'] + $workflow['unapprovedAncillaryCosts']) }}"></progress>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span>{{ __('Unapproved invoices') }}</span>
                            <span class="font-bold">{{ formatNumber($workflow['unapprovedInvoices']) }}</span>
                        </div>
                        <progress class="progress progress-warning w-full" value="{{ $workflow['unapprovedInvoices'] }}" max="{{ max(1, $workflow['readyToApproveInvoices'] + $workflow['unapprovedInvoices'] + $workflow['unapprovedAncillaryCosts']) }}"></progress>
                    </div>
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span>{{ __('Unapproved ancillary costs') }}</span>
                            <span class="font-bold">{{ formatNumber($workflow['unapprovedAncillaryCosts']) }}</span>
                        </div>
                        <progress class="progress progress-error w-full" value="{{ $workflow['unapprovedAncillaryCosts'] }}" max="{{ max(1, $workflow['readyToApproveInvoices'] + $workflow['unapprovedInvoices'] + $workflow['unapprovedAncillaryCosts']) }}"></progress>
                    </div>
                </div>
            </article>

            @if ($canViewAccounting && $accounting)
                <article class="warehouse-panel xl:col-span-2">
                    <div class="warehouse-panel-header">
                        <div>
                            <h2 class="warehouse-panel-title">{{ __('Accounting KPIs') }}</h2>
                            <p class="warehouse-panel-subtitle">{{ __('Visible to reports and documents users') }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="warehouse-mini-stat">
                            <span>{{ __('Inventory Value') }}</span>
                            <strong>{{ formatNumber($accounting['inventoryValue']) }}</strong>
                            <small>{{ config('amir.currency') ?? __('Rial') }}</small>
                        </div>
                        <div class="warehouse-mini-stat">
                            <span>{{ __('Net Sales') }}</span>
                            <strong>{{ formatNumber($accounting['netSales']) }}</strong>
                            <small>{{ config('amir.currency') ?? __('Rial') }}</small>
                        </div>
                        <div class="warehouse-mini-stat">
                            <span>{{ __('Product Gross Profit') }}</span>
                            <strong>{{ formatNumber($accounting['productGrossProfit']) }}</strong>
                            <small>{{ config('amir.currency') ?? __('Rial') }}</small>
                        </div>
                        <div class="warehouse-mini-stat">
                            <span>{{ __('Gross Margin') }}</span>
                            <strong>{{ formatNumber($accounting['grossMargin']) }}%</strong>
                            <small>{{ __('Product') }}</small>
                        </div>
                        <div class="warehouse-mini-stat">
                            <span>{{ __('Purchase Value') }}</span>
                            <strong>{{ formatNumber($accounting['purchaseValue']) }}</strong>
                            <small>{{ config('amir.currency') ?? __('Rial') }}</small>
                        </div>
                        <div class="warehouse-mini-stat">
                            <span>{{ __('Approved Ancillary Costs') }}</span>
                            <strong>{{ formatNumber($accounting['approvedAncillaryCosts']) }}</strong>
                            <small>{{ config('amir.currency') ?? __('Rial') }}</small>
                        </div>
                    </div>
                </article>
            @endif
        </section>

        @if ($canViewAccounting && $accounting)
            <section class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <article class="warehouse-panel xl:col-span-2">
                    <div class="warehouse-panel-header">
                        <div>
                            <h2 class="warehouse-panel-title">{{ __('Net Sales') }} / {{ __('Product Gross Profit') }}</h2>
                            <p class="warehouse-panel-subtitle">{{ __('Accounting KPIs') }}</p>
                        </div>
                    </div>
                    <div class="p-3">
                        <x-charts.bar-chart chart-id="warehouseAccountingChart" heightClass="h-80" :show-legend="true" :datasets="[
                            ['label' => __('Net Sales'), 'data' => $accounting['monthlyNetSales'], 'backgroundColor' => '#06b6d499', 'borderColor' => '#06b6d4', 'negativeColor' => '#fb718599'],
                            ['label' => __('Product Gross Profit'), 'data' => $accounting['monthlyProductGrossProfit'], 'backgroundColor' => '#f59e0b99', 'borderColor' => '#f59e0b', 'negativeColor' => '#fb718599'],
                        ]" />
                    </div>
                </article>

                <article class="warehouse-panel">
                    <div class="warehouse-panel-header">
                        <div>
                            <h2 class="warehouse-panel-title">{{ __('Inventory value by product') }}</h2>
                            <p class="warehouse-panel-subtitle">{{ __('Weighted moving average cost') }}</p>
                        </div>
                    </div>
                    <div class="space-y-3 p-4">
                        @forelse ($accounting['topInventoryValueProducts'] as $product)
                            <div>
                                <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                                    <a href="{{ route('products.show', $product['id']) }}" class="warehouse-link">{{ $product['name'] }}</a>
                                    <span class="font-semibold">{{ formatNumber($product['value']) }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-slate-200 dark:bg-slate-800">
                                    <div class="h-2 rounded-full bg-emerald-500" style="width: {{ min(100, max(4, ($product['value'] / max(1, $accounting['inventoryValue'])) * 100)) }}%"></div>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ __('Quantity') }}: {{ formatNumber($product['quantity']) }} - {{ __('Average Cost') }}: {{ formatNumber($product['averageCost']) }}
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-sm text-slate-500">{{ __('No inventory value yet.') }}</div>
                        @endforelse
                    </div>
                </article>
            </section>
        @endif
    </main>
</x-app-layout>
