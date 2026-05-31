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
