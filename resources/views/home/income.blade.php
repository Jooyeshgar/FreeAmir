    <div class=" w-1/3 max-[850px]:w-full relative bg-white rounded-[16px]">
        <div class="flex justify-between items-center h-[62px]">
            <h2 class="text-[#495057] ms-3">
                {{ __('Income') }}
            </h2>
        </div>

        <div class="p-2">
            <x-charts.bar-chart :datas="$monthlyIncome" chart-id="monthlyIncomeChart" heightClass="h-64" />
        </div>
    </div>
