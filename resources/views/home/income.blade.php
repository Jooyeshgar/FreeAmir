    <div class=" w-1/3 max-[850px]:w-full relative bg-white rounded-[16px]">
        <div class="flex justify-between items-center h-[62px]">
            <h2 class="text-[#495057] ms-3">
                {{ __('Income') }}
            </h2>
        </div>

        <div class="p-2">
            <x-charts.bar-chart chart-id="monthlyIncomeCostChart" heightClass="h-64"
                :datasets="[
                    ['data' => $monthlyIncome, 'backgroundColor' => '#4bb946c4', 'borderColor' => '#4bb946'],
                    ['data' => $monthlyCost, 'backgroundColor' => 'red', 'borderColor' => 'red'],
                ]" />
        </div>
    </div>
