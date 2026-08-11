<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Total amount of sold product and service') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Distribution of sales by item') }}</p>
            </div>
        </div>

        <div class="mt-3 flex justify-center">
            <x-charts.pie-chart :datas="$sellAmountPerProducts" position="bottom" metric="amount" label="{{ __('Total Sold') }}" />
        </div>
    </div>
</article>
