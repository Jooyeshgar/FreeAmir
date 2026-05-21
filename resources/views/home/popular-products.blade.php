<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Most popular products and services') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Top items by sold quantity') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('products.index')
                    <a href="{{ route('products.index') }}" class="btn btn-xs btn-ghost">{{ __('Products') }}</a>
                @endcan
                @can('services.index')
                    <a href="{{ route('services.index') }}" class="btn btn-xs btn-ghost">{{ __('Services') }}</a>
                @endcan
            </div>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="table table-zebra table-sm">
                <thead>
                    <tr>
                        <th>{{ __('Code') }}</th>
                        <th>{{ __('Product/Service name') }}</th>
                        <th>{{ __('Selling Price') }}</th>
                        <th>{{ __('Average Cost') }}</th>
                        <th>{{ __('Quantity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($popularProductsAndServices as $item)
                        <tr>
                            <td>{{ $item['code'] }}</td>
                            <td>
                                <a href="{{ route($item['type'] . '.show', $item['id']) }}" class="link link-hover">
                                    {{ $item['name'] }}
                                </a>
                            </td>
                            <td>{{ convertToFarsi(number_format($item['selling_price'])) }}</td>
                            <td>{{ convertToFarsi(number_format($item['average_cost'])) }}</td>
                            <td>{{ convertToFarsi(number_format($item['quantity'])) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-sm text-base-content/55">
                                {{ __('No sales recorded yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</article>
