    <div class="home-card w-1/3 max-[850px]:w-full">
        <div class="home-card-header">
            <h2 class="home-card-title">
                {{ __('Income') }}
            </h2>
        </div>

        <div class="p-2">
            <x-charts.bar-chart chart-id="monthlyIncomeCostChart" heightClass="h-64" :datasets="[
                ['data' => $monthlyIncome, 'backgroundColor' => '#22c55ecc', 'borderColor' => '#22c55e'],
                ['data' => $monthlyCost, 'backgroundColor' => '#ef4444cc', 'borderColor' => '#ef4444'],
            ]" />
        </div>
    </div>
