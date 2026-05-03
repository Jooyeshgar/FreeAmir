    <div class="gaugeChartContainer home-card w-1/3 max-[850px]:w-full">
        <div class="home-card-header">
            <h2 class="home-card-title">
                {{ __('Sell') }}
            </h2>
        </div>

        <div class="p-2">
            <x-charts.sell-chart id="monthlySellAmountChart" :datas="$monthlySellAmount" />
        </div>
    </div>
