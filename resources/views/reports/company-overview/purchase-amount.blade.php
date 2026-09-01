<article class="company-overview-chart card min-w-0 border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-3 lg:p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-sm leading-5 lg:text-base lg:leading-normal">{{ __('Total amount of purchased product and service') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Distribution of purchases by item') }}</p>
            </div>
        </div>

        <div class="mt-3 flex justify-center">
            <x-charts.pie-chart chart-id="buyAmountChart" height-class="h-72 sm:h-80" :datas="$buyAmountPerProducts" position="bottom" metric="amount" label="{{ __('Total Purchased') }}" />
        </div>
    </div>
</article>
