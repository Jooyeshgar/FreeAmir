@php
    $period = $filters['period'] ?? 'year';
    $mixTotal = collect($salesMix)->sum('amount');
@endphp

<x-app-layout :title="__('Invoice Dashboard')">
    <x-show-message-bags />

    <main class="mt-8 space-y-4" data-invoice-dashboard>
        <section class="overflow-hidden rounded-xl border border-base-300 bg-base-100 shadow-sm">
            <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="max-w-3xl">
                    <div class="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        {{ __('Sales and purchasing control room') }}
                    </div>
                    <h1 class="text-2xl font-black tracking-tight text-base-content sm:text-3xl">
                        {{ __('Invoice Dashboard') }}
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-base-content/60">
                        {{ __('Compare product and service sales, purchases, and returns for :period.', ['period' => $periodLabel]) }}
                    </p>
                </div>

                <form action="{{ route('invoices.dashboard') }}" method="GET" class="flex flex-wrap items-end gap-2">
                    <label class="form-control w-44">
                        <span class="label-text mb-1 text-xs">{{ __('Time Period') }}</span>
                        <select name="period" class="select select-sm select-bordered">
                            @foreach ($periodOptions as $value => $label)
                                <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Apply') }}</button>
                    <a href="{{ route('invoices.dashboard') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>
                </form>
            </div>

            <div class="grid border-t border-base-300 sm:grid-cols-3">
                <div class="border-b border-base-300 p-4 sm:border-b-0 sm:border-e">
                    <div class="text-xs text-base-content/50">{{ __('Net sales') }}</div>
                    <div class="mt-1 text-xl font-black tabular-nums text-emerald-700 dark:text-emerald-300">
                        {{ formatNumber($summary['net_sales']) }} <span class="text-xs font-normal">{{ __('Rial') }}</span>
                    </div>
                </div>
                <div class="border-b border-base-300 p-4 sm:border-b-0 sm:border-e">
                    <div class="text-xs text-base-content/50">{{ __('Net purchases') }}</div>
                    <div class="mt-1 text-xl font-black tabular-nums text-sky-700 dark:text-sky-300">
                        {{ formatNumber($summary['net_purchases']) }} <span class="text-xs font-normal">{{ __('Rial') }}</span>
                    </div>
                </div>
                <div class="p-4">
                    <div class="text-xs text-base-content/50">{{ __('Sales minus purchases') }}</div>
                    <div class="mt-1 text-xl font-black tabular-nums {{ $summary['trade_balance'] >= 0 ? 'text-violet-700 dark:text-violet-300' : 'text-rose-700 dark:text-rose-300' }}">
                        {{ formatNumber($summary['trade_balance']) }} <span class="text-xs font-normal">{{ __('Rial') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-metric-card
                :title="__('Sales invoices')"
                :value="$summary['sales_count']"
                :suffix="__('Invoices')"
                :detail="__('Average: :amount Rial', ['amount' => formatNumber($summary['average_sale'])])"
                :series="$productTrend['sell']"
                tone="success"
                icon="sales" />
            <x-metric-card
                :title="__('Purchase invoices')"
                :value="$summary['purchase_count']"
                :suffix="__('Invoices')"
                :detail="__('Average: :amount Rial', ['amount' => formatNumber($summary['average_purchase'])])"
                :series="$productTrend['buy']"
                tone="info"
                icon="buy" />
            <x-metric-card
                :title="__('Sales returns and voids')"
                :value="$summary['sales_returns']"
                :suffix="__('Rial')"
                :detail="__('Deducted from gross sales')"
                tone="error" />
            <x-metric-card
                :title="__('Purchase returns')"
                :value="$summary['purchase_returns']"
                :suffix="__('Rial')"
                :detail="__('Total returns in selected period')"
                tone="warning" />
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <div>
                        <h2 class="card-title text-base">{{ __('Product sell and buy trend') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Net invoice-item amount after returns and voids') }}</p>
                    </div>
                    <div class="mt-3 h-72">
                        <canvas id="productInvoiceTrendChart" class="h-full w-full"></canvas>
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <div>
                        <h2 class="card-title text-base">{{ __('Service sell and buy trend') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Net service invoice-item amount after returns') }}</p>
                    </div>
                    <div class="mt-3 h-72">
                        <canvas id="serviceInvoiceTrendChart" class="h-full w-full"></canvas>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(20rem,0.8fr)_minmax(0,1.4fr)]">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Sales mix') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Product and service share of net sales') }}</p>
                    @if ($mixTotal > 0)
                        <div class="mt-3 h-72">
                            <canvas id="invoiceSalesMixChart" class="h-full w-full"></canvas>
                        </div>
                    @else
                        <div class="mt-3 flex h-72 items-center justify-center rounded-lg border border-dashed border-base-300 text-sm text-base-content/55">
                            {{ __('No approved sales in this period') }}
                        </div>
                    @endif
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-0">
                    <div class="border-b border-base-300 p-4">
                        <h2 class="card-title text-base">{{ __('Top selling products and services') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Ranked by net sales amount') }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th class="text-end">{{ __('Quantity') }}</th>
                                    <th class="text-end">{{ __('Sales') }} ({{ __('Rial') }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topSales as $row)
                                    <tr>
                                        <td class="font-medium">{{ $row['name'] }}</td>
                                        <td><span class="badge badge-ghost badge-sm">{{ $row['kind'] }}</span></td>
                                        <td class="text-end tabular-nums">{{ formatNumber($row['quantity']) }}</td>
                                        <td class="text-end tabular-nums">{{ formatNumber($row['amount']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-base-content/60">{{ __('No data') }}</td></tr>
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
                        <h2 class="card-title text-base">{{ __('Recent invoices') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Latest approved and settled invoices in this period') }}</p>
                    </div>
                    @can('invoices.index')
                        <a href="{{ route('invoices.index', ['invoice_type' => 'sell']) }}" class="btn btn-sm btn-ghost">{{ __('Open invoice list') }}</a>
                    @endcan
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Invoice Number') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th class="text-end">{{ __('Amount') }} ({{ __('Rial') }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentInvoices as $invoice)
                                <tr class="hover">
                                    <td>
                                        @can('invoices.show')
                                            <a href="{{ route('invoices.show', $invoice) }}" class="link link-hover">{{ localizeNumber($invoice->number) }}</a>
                                        @else
                                            {{ localizeNumber($invoice->number) }}
                                        @endcan
                                    </td>
                                    <td>{{ $invoice->invoice_type->label() }}</td>
                                    <td>{{ $invoice->customer?->name ?? '—' }}</td>
                                    <td>{{ formatDate($invoice->date) }}</td>
                                    <td class="text-end tabular-nums">{{ formatNumber($invoice->amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-base-content/60">{{ __('No invoices found') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const productTrend = @json($productTrend);
                const serviceTrend = @json($serviceTrend);
                const salesMix = @json($salesMix);
                const charts = {};

                const palette = {
                    sell: '#10b981',
                    buy: '#0ea5e9',
                    product: '#8b5cf6',
                    service: '#f59e0b',
                };

                const money = (value) => Number(value || 0).toLocaleString();

                const renderTrend = (id, data) => {
                    const canvas = document.getElementById(id);
                    if (!canvas || !window.Chart) return;

                    charts[id]?.destroy();
                    const theme = window.getFreeAmirChartTheme
                        ? window.getFreeAmirChartTheme()
                        : { mutedTextColor: '#64748b', gridColor: 'rgba(148,163,184,0.24)' };

                    charts[id] = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                {
                                    label: @json(__('Sell')),
                                    data: data.sell,
                                    borderColor: palette.sell,
                                    backgroundColor: 'rgba(16,185,129,0.12)',
                                    fill: true,
                                    tension: 0.32,
                                    pointRadius: 2,
                                },
                                {
                                    label: @json(__('Buy')),
                                    data: data.buy,
                                    borderColor: palette.buy,
                                    backgroundColor: 'rgba(14,165,233,0.08)',
                                    fill: true,
                                    tension: 0.32,
                                    pointRadius: 2,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                datalabels: { display: false },
                                tooltip: { callbacks: { label: (context) => `${context.dataset.label}: ${money(context.parsed.y)}` } },
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { color: theme.mutedTextColor, maxRotation: 0 } },
                                y: { beginAtZero: true, grid: { color: theme.gridColor }, ticks: { color: theme.mutedTextColor, callback: money } },
                            },
                        },
                    });
                };

                const renderMix = () => {
                    const canvas = document.getElementById('invoiceSalesMixChart');
                    if (!canvas || !window.Chart) return;

                    charts.mix?.destroy();
                    charts.mix = new Chart(canvas, {
                        type: 'doughnut',
                        data: {
                            labels: salesMix.map((row) => row.name),
                            datasets: [{
                                data: salesMix.map((row) => row.amount),
                                backgroundColor: [palette.product, palette.service],
                                borderWidth: 0,
                                hoverOffset: 6,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '67%',
                            plugins: {
                                datalabels: { display: false },
                                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 18 } },
                                tooltip: { callbacks: { label: (context) => `${context.label}: ${money(context.parsed)} ${@json(__('Rial'))}` } },
                            },
                        },
                    });
                };

                const renderCharts = () => {
                    renderTrend('productInvoiceTrendChart', productTrend);
                    renderTrend('serviceInvoiceTrendChart', serviceTrend);
                    renderMix();
                };

                renderCharts();
                window.addEventListener('theme:changed', renderCharts);
            });
        </script>
    @endpush
</x-app-layout>
