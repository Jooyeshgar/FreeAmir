<div class="home-card w-1/3 max-[850px]:w-full">
    <div class="home-card-header">
        <h2 class="home-card-title">{{ __('Total amount of sold product and service') }}</h2>
    </div>
    <div class="flex justify-center">
        <x-charts.pie-chart :datas="$sellAmountPerProducts" position="bottom" metric="amount" label="{{ __('Total Sold') }}" />
    </div>
</div>
