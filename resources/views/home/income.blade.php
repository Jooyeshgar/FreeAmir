<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Income') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Monthly income vs. cost') }}</p>
            </div>
        </div>

        <div class="mt-3">
            <x-charts.bar-chart chart-id="monthlyIncomeCostChart" heightClass="h-64" :datasets="[
                ['data' => $monthlyIncome, 'backgroundColor' => '#22c55ecc', 'borderColor' => '#22c55e'],
                ['data' => $monthlyCost, 'backgroundColor' => '#ef4444cc', 'borderColor' => '#ef4444'],
            ]" />
        </div>
    </div>
</article>
