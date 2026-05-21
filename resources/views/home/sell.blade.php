<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Sell') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Monthly sales volume') }}</p>
            </div>
        </div>

        <div class="mt-3">
            <x-charts.sell-chart id="monthlySellAmountChart" :datas="$monthlySellAmount" />
        </div>
    </div>
</article>
