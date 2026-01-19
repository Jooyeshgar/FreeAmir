    <div class="w-1/3 max-[850px]:w-full bg-white rounded-[16px]">
        <div class="flex justify-between items-center h-[62px]">
            <h2 class="text-[#495057] ms-3">{{ __('Total amount of sold product and service') }}</h2>
        </div>
        <div class="flex justify-center">
            <x-charts.pie-chart :datas="$sellAmountPerProducts" position="bottom" metric="amount" label="{{ __('Total Sold') }}" />
        </div>
    </div>
