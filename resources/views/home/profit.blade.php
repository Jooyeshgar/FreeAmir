    <div class=" w-1/3 max-[850px]:w-full relative bg-white rounded-[16px]">
        <div class="flex justify-between items-center h-[62px]">
            <h2 class="text-[#495057] ms-3">
                {{ __('Profit and loss') }} : <b>{{ formatNumber($profit) }} {{ config('amir.currency') ?? __('Rial') }}</b>
            </h2>
        </div>

        <div class="p-2">
            <x-charts.bar-chart chart-id="profitChart" heightClass="h-64"
                :datasets="[
                    ['data' => $totalIncomesData, 'backgroundColor' => '#4bb946c4', 'borderColor' => '#4bb946'],
                    ['data' => $totalCostsData, 'backgroundColor' => 'red', 'borderColor' => 'red'],
                ]" />
        </div>
    </div>
