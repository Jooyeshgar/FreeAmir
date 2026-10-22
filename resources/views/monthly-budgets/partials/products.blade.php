<section id="monthly-products" class="budget-panel scroll-mt-4" aria-labelledby="monthly-products-title"
    x-data="{ search: '', lossesOnly: false, matches(name, profit) { return name.toLocaleLowerCase().includes(this.search.trim().toLocaleLowerCase()) && (!this.lossesOnly || profit < 0); } }">
    <div class="budget-panel-heading">
        <div>
            <h2 id="monthly-products-title" class="budget-section-title">{{ __('Product Profitability') }}</h2>
            <p class="budget-caption">{{ __('Products ranked by gross profit. Search or filter to find loss-making sales.') }}</p>
        </div>
        <span class="text-xs text-base-content/60">{{ $currency }}</span>
    </div>
    @if ($profitability['products']->contains('missingCost', true))
        <div class="m-4 budget-notice" role="status">{{ __('Some products have unavailable cost data. Their gross profit and margin may be overstated.') }}</div>
    @endif
    @if ($profitability['products']->isNotEmpty())
        <div class="flex flex-wrap items-center gap-4 px-5 py-3">
            <label class="min-w-48 flex-1"><span class="sr-only">{{ __('Search products') }}</span><input type="search" class="input input-bordered input-sm w-full" x-model="search" placeholder="{{ __('Search products') }}"></label>
            <label class="flex items-center gap-2 text-xs"><input type="checkbox" class="checkbox checkbox-sm" x-model="lossesOnly">{{ __('Loss-making products only') }}</label>
            <span class="badge badge-ghost badge-sm">{{ localizeNumber($profitability['products']->count()) }}</span>
        </div>
    @endif
    <div class="budget-product-scroll">
        <table class="table budget-table">
            <thead><tr><th scope="col">{{ __('Product') }}</th><th scope="col" class="text-end">{{ __('Net Product Sales') }}</th><th scope="col" class="text-end">{{ __('Cost of Goods Sold') }}</th><th scope="col" class="text-end">{{ __('Gross Profit') }}</th><th scope="col" class="text-end">{{ __('Gross margin') }}</th></tr></thead>
            <tbody>
                @forelse ($profitability['products'] as $product)
                    <tr x-show="matches(@js($product['name'].' '.($product['group'] ?? '')), @js($product['grossProfit']))">
                        <td class="min-w-48">
                            @can('products.show')<a class="link link-hover font-medium" href="{{ route('products.show', $product['id']) }}">{{ $product['name'] }} <span aria-hidden="true">↗</span></a>@else<span class="font-medium">{{ $product['name'] }}</span>@endcan
                            <div class="budget-caption">{{ $product['group'] ?? __('Ungrouped product') }}</div>
                            @if ($product['missingCost'])<span class="badge badge-warning badge-sm mt-1">{{ __('Cost unavailable') }}</span>@endif
                        </td>
                        <td class="text-end">
                            <div class="font-semibold">{{ formatNumber($product['sales']) }}</div>
                            <div class="budget-caption">{{ __('Sales change') }}</div>
                            @include('monthly-budgets.partials.change', ['comparison' => ['change' => $product['salesChange']], 'increaseIsGood' => true, 'neutral' => false, 'points' => false])
                        </td>
                        <td class="text-end">{{ formatNumber($product['cogs']) }}</td>
                        <td class="text-end">
                            <div class="font-semibold {{ $product['grossProfit'] < 0 ? 'text-error' : '' }}">{{ formatNumber($product['grossProfit']) }}</div>
                            <div class="budget-caption">{{ __('Profit change') }}</div>
                            @include('monthly-budgets.partials.change', ['comparison' => ['change' => $product['grossProfitChange']], 'increaseIsGood' => true, 'neutral' => false, 'points' => false])
                        </td>
                        <td class="text-end"><span class="badge badge-outline {{ ($product['grossMargin'] ?? 0) < 0 ? 'badge-error' : 'badge-ghost' }}">{{ is_null($product['grossMargin']) ? '—' : localizeNumber(number_format($product['grossMargin'], 1)).'%' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="budget-empty">{{ __('No approved product sales in this period. Product profitability will appear after sales are posted.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p x-cloak x-show="@js($profitability['products']->map(fn ($product) => ['name' => $product['name'].' '.($product['group'] ?? ''), 'profit' => $product['grossProfit']])->values()).filter(product => matches(product.name, product.profit)).length === 0 && (search !== '' || lossesOnly)" class="budget-empty">{{ __('No products match these filters.') }}</p>
</section>
