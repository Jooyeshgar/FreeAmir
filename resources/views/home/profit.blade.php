    <div class=" w-1/3 max-[850px]:w-full relative bg-white rounded-[16px]">
        <div class="flex justify-between items-center h-[62px]">
            <h2 class="text-[#495057] ms-3">
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
                ['data' => $alignedIncomesData, 'backgroundColor' => '#4bb946c4', 'borderColor' => '#4bb946'],
                ['data' => $alignedCostsData, 'backgroundColor' => 'red', 'borderColor' => 'red'],
            ]" />
        </div>
    </div>
