    <div class="home-card w-1/2 max-[1200px]:w-full">
        <div class="home-card-header">
            <h2 class="home-card-title">{{ __('Warehouse') }}</h2>
        </div>
        <div class="p-2">
            <x-charts.warehouse-chart :datas="$monthlyWarehouse" />
        </div>
    </div>
