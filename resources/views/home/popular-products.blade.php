    <div class="home-card w-1/2 max-[1200px]:w-full">
        <div class="home-card-header">
            <div>
                <h2 class="home-card-title">{{ __('Most popular products and services') }}</h2>
            </div>
            <div class="flex m-1 gap-2 overflow-hidden">
                <a href="{{ route('products.index') }}" class="home-card-action">
                    {{ __('Products') }}</a>
                <a href="{{ route('services.index') }}" class="home-card-action">
                    {{ __('Services') }}</a>
            </div>
        </div>
        <div class="home-card-body mt-4 overflow-x-auto p-4">
            <table class="home-table">
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
                            <a href="{{ route($popularProductAndService['type'] . '.show', $popularProductAndService['id']) }}" class="home-link">
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
