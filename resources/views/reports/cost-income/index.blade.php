<x-app-layout :title="__('Cost and Income Dashboard')">
    <x-show-message-bags />

    <main class="mt-6 space-y-6">
        <section class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-base-content">{{ __('Cost and Income Dashboard') }}</h1>
            <p class="text-sm text-base-content/60">
                {{ __('Profitability and trade for the current fiscal year') }}
            </p>
        </section>

        @include('reports.cost-income._metrics')

        <article class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="card-title text-base">{{ __('Monthly Income vs Cost') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Select a month on the chart to open its monthly income and expense workbench.') }}</p>
                    </div>
                </div>

                <div class="mt-3">
                    <x-charts.bar-chart chart-id="costIncomeMonthlyChart" heightClass="h-72" :datasets="[
                        ['label' => __('Actual Income'), 'data' => $monthlyIncome, 'backgroundColor' => '#22c55ecc', 'borderColor' => '#22c55e', 'order' => 2],
                        ['label' => __('Actual Expense'), 'data' => $monthlyCost, 'backgroundColor' => '#ef4444cc', 'borderColor' => '#ef4444', 'order' => 2],
                        ['label' => __('Forecast Income'), 'data' => $forecastIncome, 'type' => 'line', 'borderColor' => '#2563eb', 'backgroundColor' => '#2563eb', 'tension' => 0.4, 'pointRadius' => 3, 'pointHoverRadius' => 5, 'spanGaps' => true, 'order' => 1, 'datalabels' => ['display' => false]],
                        ['label' => __('Forecast Expense'), 'data' => $forecastExpense, 'type' => 'line', 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b', 'tension' => 0.4, 'pointRadius' => 3, 'pointHoverRadius' => 5, 'spanGaps' => true, 'order' => 1, 'datalabels' => ['display' => false]],
                    ]" :links="$monthlyBudgetLinks" />
                </div>

                @if ($monthsWithoutDocuments !== [])
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @foreach ($monthsWithoutDocuments as $monthLabel)
                            <span class="badge badge-error badge-outline badge-sm">
                                {{ $monthLabel }}: {{ __('No accounting document exists for calculating actual income and expense.') }}
                            </span>
                        @endforeach
                    </div>
                @endif
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
