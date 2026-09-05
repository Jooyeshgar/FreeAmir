<x-app-layout :title="__('Invoice Dashboard')">
    <x-show-message-bags />

    <main class="mt-8 space-y-4" data-invoice-dashboard>
        <section class="rounded-xl border border-base-300 bg-base-100 shadow-sm">
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
                    <div class="w-44">
                        <x-date-picker name="start_date" :title="__('Start date')" :value="old('start_date', convertToJalali($filters['start_date'], true))" />
                    </div>
                    <div class="w-44">
                        <x-date-picker name="end_date" :title="__('End date')" :value="old('end_date', convertToJalali($filters['end_date'], true))" />
                    </div>
                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Apply') }}</button>
                    <a href="{{ route('invoices.dashboard') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>
                </form>
            </div>

        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-metric-card
                :title="__('Product sales revenue')"
                :value="$summary['product_revenue']"
                :suffix="__('Rial')"
                :detail="__('Net of sales returns and VAT')"
                tone="success"
                icon="sales" />
            <x-metric-card
                :title="__('Product COGS')"
                :value="$summary['product_cogs']"
                :suffix="__('Rial')"
                :detail="__('Cost snapshot at the time of sale')"
                tone="error"
                icon="cost" />
            <x-metric-card
                :title="__('Product gross profit')"
                :value="$summary['product_profit']"
                :suffix="__('Rial')"
                :detail="__('Revenue minus COGS')"
                tone="primary"
                icon="profit" />
            <x-metric-card
                :title="__('Product gross margin')"
                :value="$summary['product_profit_margin']"
                suffix="%"
                :detail="__('Gross profit as a percentage of product revenue')"
                tone="warning"
                icon="profit" />
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
                    <x-charts.line-chart
                        chart-id="productInvoiceTrendChart"
                        class="mt-3"
                        height-class="h-72"
                        :labels="$productTrend['labels']"
                        :show-legend="true"
                        :datasets="[
                            ['label' => __('Sell'), 'data' => $productTrend['sell'], 'borderColor' => '#10b981', 'backgroundColor' => '#10b98120'],
                            ['label' => __('Buy'), 'data' => $productTrend['buy'], 'borderColor' => '#0ea5e9', 'backgroundColor' => '#0ea5e914'],
                        ]" />
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <div>
                        <h2 class="card-title text-base">{{ __('Service sell and buy trend') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Net service invoice-item amount after returns') }}</p>
                    </div>
                    <x-charts.line-chart
                        chart-id="serviceInvoiceTrendChart"
                        class="mt-3"
                        height-class="h-72"
                        :labels="$serviceTrend['labels']"
                        :show-legend="true"
                        :datasets="[
                            ['label' => __('Sell'), 'data' => $serviceTrend['sell'], 'borderColor' => '#10b981', 'backgroundColor' => '#10b98120'],
                            ['label' => __('Buy'), 'data' => $serviceTrend['buy'], 'borderColor' => '#0ea5e9', 'backgroundColor' => '#0ea5e914'],
                        ]" />
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Product sales breakdown') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Share of net product sales by product') }}</p>
                    <x-charts.pie-chart
                        chart-id="productSalesPieChart"
                        class="mt-3"
                        height-class="h-72"
                        :datas="$productSalesBreakdown"
                        metric="amount"
                        :label="__('Sales')" />
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body">
                    <h2 class="card-title text-base">{{ __('Service sales breakdown') }}</h2>
                    <p class="text-xs text-base-content/55">{{ __('Share of net service sales by service') }}</p>
                    <x-charts.pie-chart
                        chart-id="serviceSalesPieChart"
                        class="mt-3"
                        height-class="h-72"
                        :datas="$serviceSalesBreakdown"
                        metric="amount"
                        :label="__('Sales')" />
                </div>
            </article>
        </section>

        <section class="grid grid-cols-1 items-start gap-4 xl:grid-cols-2">
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
                                    <th class="text-end">{{ __('Profit percentage') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($topSales as $row)
                                    <tr>
                                        <td class="font-medium">
                                            @php($detailRoute = $row['itemable_type'] === App\Models\Product::class ? 'products.show' : 'services.show')
                                            @can($detailRoute)
                                                <a href="{{ route($detailRoute, $row['id']) }}" class="link link-primary">{{ $row['name'] }}</a>
                                            @else
                                                {{ $row['name'] }}
                                            @endcan
                                        </td>
                                        <td><span class="badge badge-ghost badge-sm">{{ $row['kind'] }}</span></td>
                                        <td class="text-end tabular-nums">{{ formatNumber($row['quantity']) }}</td>
                                        <td class="text-end tabular-nums">{{ formatNumber($row['amount']) }}</td>
                                        <td class="text-end tabular-nums">{{ formatNumber($row['profit_margin']) }}%</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-base-content/60">{{ __('No data') }}</td></tr>
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
                                        <td>
                                            @if ($invoice->customer)
                                                @can('customers.show')
                                                    <a href="{{ route('customers.show', $invoice->customer) }}" class="link link-primary">
                                                        {{ $invoice->customer->name }}
                                                    </a>
                                                @else
                                                    {{ $invoice->customer->name }}
                                                @endcan
                                            @else
                                                —
                                            @endif
                                        </td>
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
            </article>
        </section>
    </main>
</x-app-layout>
