    <div class="w-1/2 max-[1200px]:w-full bg-white rounded-[16px]">
        <div class="flex justify-between items-center h-[62px]">
            <h2 class="text-[#495057] ms-3">{{ __('Warehouse') }}</h2>
        </div>
        <div class="p-2">
            <x-charts.warehouse-chart :datas="$monthlyWarehouse" />
        </div>
    </div>
