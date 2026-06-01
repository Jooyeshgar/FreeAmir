<x-app-layout :title="__('Cost and Income Dashboard')">
    <x-show-message-bags />

    <main class="mt-6 space-y-6">
        <section class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-base-content">{{ __('Cost and Income Dashboard') }}</h1>
            <p class="text-sm text-base-content/60">
                {{ __('Profitability, trade and payroll cost for the current fiscal year') }}
            </p>
        </section>

        @include('reports.cost-income._metrics')

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
