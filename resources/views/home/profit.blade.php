    <div class="home-card w-1/3 max-[850px]:w-full">
        <div class="home-card-header">
            <h2 class="home-card-title">
                {{ __('Profit and loss') }} : <b>{{ formatNumber($profit) }}
                    {{ config('amir.currency') ?? __('Rial') }}</b>
            </h2>
        </div>

        <div class="p-2">
            @php
                // Merge income and cost data keys so both datasets share the same labels
                $profitLabels = array_unique(array_merge(array_keys($totalIncomesData), array_keys($totalCostsData)));
                $alignedIncomesData = array_fill_keys($profitLabels, 0);
                $alignedCostsData = array_fill_keys($profitLabels, 0);
                foreach ($totalIncomesData as $k => $v) {
                    $alignedIncomesData[$k] = $v;
                }
                foreach ($totalCostsData as $k => $v) {
                    $alignedCostsData[$k] = $v;
                }
            @endphp
            <x-charts.bar-chart chart-id="profitChart" heightClass="h-64" :datasets="[
                ['data' => $alignedIncomesData, 'backgroundColor' => '#22c55ecc', 'borderColor' => '#22c55e'],
                ['data' => $alignedCostsData, 'backgroundColor' => '#ef4444cc', 'borderColor' => '#ef4444'],
            ]" />
        </div>
    </div>
