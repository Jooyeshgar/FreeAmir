<x-app-layout :title="__('CRM Dashboard')">
    <x-show-message-bags />

    <main class="mt-8 space-y-4">
        {{-- Header + shortcuts --}}
        <section class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-base-content">{{ __('CRM Dashboard') }}</h1>
                <p class="text-sm text-base-content/60">
                    {{ __('Customer sales overview for fiscal year :year', ['year' => $fiscalYear]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('customers.create')
                    <a href="{{ route('customers.create') }}" class="btn btn-sm btn-primary">
                        + {{ __('New Customer') }}
                    </a>
                @endcan
                @can('invoices.create')
                    <a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}" class="btn btn-sm btn-neutral">
                        + {{ __('Issue Invoice') }}
                    </a>
                @endcan
            </div>
        </section>

        {{-- KPI cards --}}
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-metric-card
                :title="__('Sales this month') . ' (' . $currentMonthLabel . ')'"
                :value="$metrics['salesThisMonth']"
                :suffix="__('Rial')"
                :detail="__('Approved sales invoices')"
                tone="success" />
            <x-metric-card
                :title="__('Paid this month')"
                :value="$metrics['paidThisMonth']"
                :suffix="__('Rial')"
                :detail="__('Receipts from customers')"
                tone="info" />
            <x-metric-card
                :title="__('Outstanding (unpaid)')"
                :value="$metrics['totalUnpaid']"
                :suffix="__('Rial')"
                :detail="__('Total receivable from customers')"
                tone="error" />
            <x-metric-card
                :title="__('Customers with debt')"
                :value="$metrics['unpaidCustomersCount']"
                :suffix="__('person(s)')"
                :detail="__('Have an open balance')"
                tone="warning" />
        </section>

        {{-- Aging + Sales trend --}}
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(20rem,0.8fr)_minmax(0,1.6fr)]">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Aging of unpaid invoices') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Receivables grouped by age') }}</p>
                    @php $agingTotal = collect($aging)->sum('amount'); @endphp
                    <div class="mt-3 space-y-4">
                        @forelse ($aging as $bucket)
                            @php $percent = $agingTotal > 0 ? round($bucket['amount'] / $agingTotal * 100) : 0; @endphp
                            <div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-medium">{{ $bucket['label'] }}</span>
                                    <span class="text-xs text-base-content/70">{{ formatNumber($bucket['amount']) }} {{ __('Rial') }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <progress class="progress progress-error h-2" value="{{ $percent }}" max="100"></progress>
                                    <span class="w-10 shrink-0 text-xs text-base-content/60">{{ formatNumber($percent) }}{{ __('Percent sign') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-dashed border-base-300 p-4 text-center text-sm text-base-content/60">
                                {{ __('No outstanding receivables') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Sales trend') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Monthly net sales for the fiscal year') }}</p>
                    <div class="mt-3 h-72">
                        <canvas id="crmSalesTrendChart" class="h-full w-full"></canvas>
                    </div>
                </div>
            </article>
        </section>

        {{-- Sales by category + Top buyers (year) --}}
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Sales by customer category') }}</h2>
                    @if (count($salesByCategory))
                        <x-charts.pie-chart
                            :datas="$salesByCategory"
                            metric="amount"
                            :label="__('Sales')"
                            heightClass="h-72" />
                    @else
                        <div class="mt-3 rounded-lg border border-dashed border-base-300 p-4 text-center text-sm text-base-content/60">
                            {{ __('No sales recorded') }}
                        </div>
                    @endif
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-0">
                    <div class="border-b border-base-300 p-4">
                        <h2 class="card-title text-base">{{ __('Top buyers (year)') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Customer') }}</th>
                                    <th class="text-end">{{ __('Sales') }} ({{ __('Rial') }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topBuyersYear as $buyer)
                                    <tr>
                                        <td>{{ $buyer['name'] }}</td>
                                        <td class="text-end">{{ formatNumber($buyer['amount']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center text-base-content/60">{{ __('No data') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </section>

        {{-- Top buyers (month) + Recent invoices --}}
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-0">
                    <div class="border-b border-base-300 p-4">
                        <h2 class="card-title text-base">{{ __('Top buyers this month') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Customer') }}</th>
                                    <th class="text-end">{{ __('Sales') }} ({{ __('Rial') }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topBuyersMonth as $buyer)
                                    <tr>
                                        <td>{{ $buyer['name'] }}</td>
                                        <td class="text-end">{{ formatNumber($buyer['amount']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="2" class="text-center text-base-content/60">{{ __('No data') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-0">
                    <div class="border-b border-base-300 p-4">
                        <h2 class="card-title text-base">{{ __('Recent invoices') }}</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Number') }}</th>
                                    <th>{{ __('Customer') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th class="text-end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentInvoices as $invoice)
                                    <tr class="hover">
                                        <td>
                                            <a class="link link-hover" href="{{ route('invoices.show', $invoice) }}">
                                                {{ formatNumber($invoice->number) }}
                                            </a>
                                        </td>
                                        <td>{{ $invoice->customer?->name ?? '—' }}</td>
                                        <td>{{ formatDate($invoice->date) }}</td>
                                        <td class="text-end">{{ formatNumber($invoice->amount) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-base-content/60">{{ __('No invoices found') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </section>
    </main>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const trend = @json($salesTrend);
                const theme = window.getFreeAmirChartTheme
                    ? window.getFreeAmirChartTheme()
                    : { textColor: '#475569', mutedTextColor: '#64748b', gridColor: 'rgba(148,163,184,0.24)' };

                let trendChart = null;
                const renderTrend = () => {
                    if (!window.Chart) return;
                    const canvas = document.getElementById('crmSalesTrendChart');
                    if (!canvas) return;
                    trendChart?.destroy();
                    trendChart = new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: trend.labels,
                            datasets: [{
                                label: @json(__('Sales')),
                                data: trend.values,
                                backgroundColor: '#34d399',
                                borderRadius: 6,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                datalabels: { display: false },
                                tooltip: {
                                    rtl: true,
                                    callbacks: {
                                        label: (ctx) => ctx.parsed.y.toLocaleString(),
                                    },
                                },
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { color: theme.mutedTextColor, font: { size: 11 } } },
                                y: { beginAtZero: true, grid: { color: theme.gridColor }, ticks: { color: theme.mutedTextColor } },
                            },
                        },
                    });
                };

                renderTrend();
                window.addEventListener('theme:changed', renderTrend);
            });
        </script>
    @endpush
</x-app-layout>
