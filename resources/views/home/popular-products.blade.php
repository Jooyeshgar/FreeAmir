    <div class="w-1/2 max-[1200px]:w-full bg-[#E9ECEF] rounded-[16px]">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-[#495057] ms-3">{{ __('Most popular products and services') }}</h2>
            </div>
            <div class="flex rounded-[16px] m-1 overflow-hidden">
                <a href="{{ route('products.index') }}" class="flex ml-2 items-center justify-center bg-[#DEE2E6] text-[#242424] rounded-[16px] w-[72px] h-[56px]">
                    {{ __('Products') }}</a>
                <a href="{{ route('services.index') }}" class="flex items-center justify-center bg-[#DEE2E6] text-[#242424] rounded-[16px] w-[72px] h-[56px]">
                    {{ __('Services') }}</a>
            </div>
        </div>
        <div class="text-[#495057] mt-4">
            <div class="flex justify-between mx-4 border-b-2 border-b-[#CED4DA] pb-3 mb-4">
                <p>{{ __('Product/Service name') }}</p>
                <p>{{ __('Quantity') }}</p>
            </div>
            <div class="flex justify-between mx-4 text-[13px]">
                <div>
                    @foreach ($popularProductsAndServices as $popularProductAndService)
                        <p class="mb-4">
                            <a href="{{ route($popularProductAndService['type'] . '.show', $popularProductAndService['id']) }}">
                                {{ $popularProductAndService['name'] }}</a>
                        </p>
                    @endforeach
                </div>
                <div>
                    @foreach ($popularProductsAndServices as $popularProductAndService)
                        <p class="mb-4">
                            {{ convertToFarsi(number_format($popularProductAndService['quantity'])) }}
                        </p>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
