    <div class="gaugeChartContainer w-1/3 max-[850px]:w-full relative bg-[#E9ECEF] rounded-[16px]">
        <div class="flex justify-between items-center h-[62px]">
            <h2 class="text-[#495057] ms-3">
                {{ __('Income') }}
            </h2>
        </div>

        <div class="p-2">
            <x-charts.income-chart id="monthlyIncomeChart" :datas="$monthlyIncome" />
        </div>
    </div>
