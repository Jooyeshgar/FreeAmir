@php($displayProducts = $profitability['products']->take(5))

<section id="monthly-products" class="budget-section budget-product-section" aria-labelledby="monthly-products-title"
    x-data="{ search: '', lossesOnly: false, matches(name, profit) { return name.toLocaleLowerCase().includes(this.search.trim().toLocaleLowerCase()) && (!this.lossesOnly || profit < 0); } }">
    <div class="budget-section-heading">
        <div>
            <h2 id="monthly-products-title">{{ __('Product Profitability') }}</h2>
            <p>{{ __('Products ranked by gross profit · excluding VAT') }}</p>
        </div>
        <span class="budget-result-count">{{ trans_choice(':count products', $displayProducts->count(), ['count' => localizeNumber($displayProducts->count())]) }}</span>
    </div>
    <div class="budget-card budget-table-card">
        @if ($displayProducts->contains('missingCost', true))
            <div class="budget-notice" role="status">{{ __('Some products have unavailable cost data. Their gross profit and margin may be overstated.') }}</div>
        @endif
        @if ($displayProducts->isNotEmpty())
            <div class="budget-table-toolbar">
                <label class="budget-search-box">
                    <span class="sr-only">{{ __('Search products') }}</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4" stroke-linecap="round"/></svg>
                    <input type="search" x-model="search" placeholder="{{ __('Search products') }}">
                </label>
                <label class="budget-filter-check"><input type="checkbox" x-model="lossesOnly">{{ __('Loss-making products only') }}</label>
                <span class="budget-result-count">{{ $currency }}</span>
            </div>
        @endif
        <div class="budget-product-scroll">
            <table class="budget-table">
                <thead><tr><th scope="col">{{ __('Product') }}</th><th scope="col" class="num">{{ __('Net Product Sales') }}</th><th scope="col" class="num">{{ __('Cost of Goods Sold') }}</th><th scope="col" class="num">{{ __('Gross Profit') }}</th><th scope="col" class="num">{{ __('Gross margin') }}</th></tr></thead>
                <tbody>
                    @forelse ($displayProducts as $index => $product)
                        <tr x-show="matches(@js($product['name'].' '.($product['group'] ?? '')), @js($product['grossProfit']))">
                            <td>
                                <span class="budget-rank">{{ localizeNumber($index + 1) }}</span>
                                @can('products.show')<a class="budget-product-name" href="{{ route('products.show', $product['id']) }}">{{ $product['name'] }}</a>@else<strong class="budget-product-name">{{ $product['name'] }}</strong>@endcan
                                <div class="budget-product-brand">{{ $product['group'] ?? __('Ungrouped product') }}</div>
                                @if ($product['missingCost'])<span class="budget-cost-warning">{{ __('Cost unavailable') }}</span>@endif
                            </td>
                            <td class="num">
                                <strong>{{ formatNumber($product['sales']) }}</strong>
                                <div class="budget-product-change" data-direction="{{ ($product['salesChange'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ ($product['salesChange'] ?? 0) > 0 ? '↑' : (($product['salesChange'] ?? 0) < 0 ? '↓' : '→') }} {{ formatNumber(abs($product['salesChange'] ?? 0)) }}</div>
                            </td>
                            <td class="num budget-muted">{{ formatNumber($product['cogs']) }}</td>
                            <td class="num">
                                <strong class="{{ $product['grossProfit'] < 0 ? 'budget-negative' : '' }}">{{ formatNumber($product['grossProfit']) }}</strong>
                                <div class="budget-product-change" data-direction="{{ ($product['grossProfitChange'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ ($product['grossProfitChange'] ?? 0) > 0 ? '↑' : (($product['grossProfitChange'] ?? 0) < 0 ? '↓' : '→') }} {{ formatNumber(abs($product['grossProfitChange'] ?? 0)) }}</div>
                            </td>
                            <td class="num"><span class="budget-margin-pill {{ ! is_null($product['grossMargin']) && $product['grossMargin'] < 10 ? 'is-low' : '' }}">{{ is_null($product['grossMargin']) ? '—' : localizeNumber(number_format($product['grossMargin'], 1)).'%' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="budget-empty">{{ __('No approved product sales in this period. Product profitability will appear after sales are posted.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p x-cloak x-show="@js($displayProducts->map(fn ($product) => ['name' => $product['name'].' '.($product['group'] ?? ''), 'profit' => $product['grossProfit']])->values()).filter(product => matches(product.name, product.profit)).length === 0 && (search !== '' || lossesOnly)" class="budget-empty">{{ __('No products match these filters.') }}</p>
    </div>
</section>
