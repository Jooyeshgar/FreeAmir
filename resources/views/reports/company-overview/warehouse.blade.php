<article class="company-overview-chart card min-w-0 border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-3 lg:p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-sm leading-5 lg:text-base lg:leading-normal">{{ __('Warehouse') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Monthly warehouse stock') }}</p>
            </div>
        </div>

        <div class="mt-3">
            <x-charts.warehouse-chart :datas="$monthlyWarehouse" />
        </div>
    </div>
</article>