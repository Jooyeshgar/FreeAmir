<x-app-layout :title="__('Cost and Income Dashboard')">
    <x-show-message-bags />
    @php
        $currency = config('amir.currency') ?? __('Rial');
        $profitValue = (int) ($profit ?? 0);

        $cards = [
            [
                'title' => __('Total Income'),
                'value' => $totalIncome ?? 0,
                'suffix' => $currency,
                'tone' => 'success',
                'series' => array_values($monthlyIncome ?? []),
                'icon' => 'income',
            ],
            [
                'title' => __('Total Cost'),
                'value' => $totalCost ?? 0,
                'suffix' => $currency,
                'tone' => 'error',
                'series' => array_values($monthlyCost ?? []),
                'icon' => 'cost',
            ],
            [
                'title' => __('Net Profit'),
                'value' => abs($profitValue),
                'suffix' => $profitValue >= 0 ? __('Profit') : __('Loss'),
                'tone' => $profitValue >= 0 ? 'success' : 'error',
                'icon' => 'profit',
            ],
            [
                'title' => __('Profit Margin'),
                'value' => $margin ?? 0,
                'suffix' => __('Percent sign'),
                'tone' => ($margin ?? 0) >= 0 ? 'primary' : 'error',
                'icon' => 'chart',
            ],
        ];
    @endphp

    <main class="mt-6 space-y-6">
        <section class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-base-content">{{ __('Cost and Income Dashboard') }}</h1>
            <p class="text-sm text-base-content/60">
                {{ __('Profitability, trade and payroll cost for the current fiscal year') }}
            </p>
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $card)
                <x-metric-card :card="$card" />
            @endforeach
        </section>

        <article class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="card-title text-base">{{ __('Monthly Income vs Cost') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Across the current fiscal year') }}</p>
                    </div>
                </div>

                <div class="mt-3">
                    <x-charts.bar-chart chart-id="costIncomeMonthlyChart" heightClass="h-72" :datasets="[
                        ['label' => __('Income'), 'data' => $monthlyIncome, 'backgroundColor' => '#22c55ecc', 'borderColor' => '#22c55e'],
                        ['label' => __('Cost'), 'data' => $monthlyCost, 'backgroundColor' => '#ef4444cc', 'borderColor' => '#ef4444'],
                    ]" />
                </div>
            </div>
        </article>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @include('reports.cost-income._breakdown')
        </section>

        @include('reports.cost-income._trading')

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @include('reports.cost-income._top-customers')
        </section>
    </main>
</x-app-layout>
