    <div class="w-1/2 max-[1200px]:w-full bg-white rounded-[16px]">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-[#495057] ms-3">{{ __('Most popular products and services') }}</h2>
            </div>
            <div class="flex rounded-[16px] m-1 overflow-hidden ">
                <a href="{{ route('products.index') }}" class="flex ml-2 items-center justify-center bg-[#DEE2E6] text-[#242424] rounded-[16px] w-[72px] h-[56px]">
                    {{ __('Products') }}</a>
                <a href="{{ route('services.index') }}" class="flex items-center text-center bg-[#DEE2E6] text-[#242424] rounded-[16px] w-[72px] h-[56px]">
                    {{ __('Services') }}</a>
            </div>
        </div>
        <div class="text-[#495057] mt-4 p-4">
            <table class="w-full text-center text-[13px]">
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Product/Service name') }}</th>
                    <th>{{ __('Selling Price') }}</th>
                    <th>{{ __('Average Cost') }}</th>
                    <th>{{ __('Quantity') }}</th>
                </tr>
                @foreach ($popularProductsAndServices as $popularProductAndService)
                    <tr>
                        <td class="p-2">
                            {{ $popularProductAndService['code'] }}
                        </td>
                        <td class="text-right">
                            <a href="{{ route($popularProductAndService['type'] . '.show', $popularProductAndService['id']) }}">
                                {{ $popularProductAndService['name'] }}</a>
                        </td>
                        <td>
                            {{ convertToFarsi(number_format($popularProductAndService['selling_price'])) }}
                        </td>
                        <td>
                            {{ convertToFarsi(number_format($popularProductAndService['average_cost'])) }}
                        </td>
                        <td>
                            {{ convertToFarsi(number_format($popularProductAndService['quantity'])) }}
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
