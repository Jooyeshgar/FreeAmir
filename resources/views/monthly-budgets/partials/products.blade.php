@php
    $topProducts = $profitability['products']->take(10);
    $lossMakingProducts = $profitability['products']->filter(fn ($product) => $product['grossProfit'] < 0);
    $displayProducts = $topProducts
        ->concat($lossMakingProducts->whereNotIn('id', $topProducts->pluck('id')))
        ->values();
@endphp

<section id="monthly-products" class="scroll-mt-4 print:break-inside-avoid" aria-labelledby="monthly-products-title"
    x-data="{ search: '', lossesOnly: false, matches(name, profit) { return name.toLocaleLowerCase().includes(this.search.trim().toLocaleLowerCase()) && (!this.lossesOnly || profit < 0); } }">
    <div class="flex items-end justify-between gap-4 mb-3 [&_h2]:text-base [&_h2]:lg:text-lg [&_h2]:leading-normal
        [&_h2]:font-bold [&_p]:mt-1 [&_p]:text-base-content/60 [&_p]:text-xs">
        <div>
            <h2 id="monthly-products-title">{{ __('Product Profitability') }}</h2>
            <p>{{ __('Products ranked by gross profit · excluding VAT') }}</p>
        </div>
        <span class="ms-auto text-base-content/60 text-xs whitespace-nowrap">{{ trans_choice(':count products', $displayProducts->count(), ['count' => localizeNumber($displayProducts->count())]) }}</span>
    </div>
    <div class="card min-w-0 overflow-hidden border border-base-300 bg-base-100/90 shadow-sm
        print:shadow-none print:break-inside-avoid">
        @if ($displayProducts->contains('missingCost', true))
            <div class="alert alert-warning mb-3 rounded-box text-sm" role="status">{{ __('Some products have unavailable cost data. Their gross profit and margin may be overstated.') }}</div>
        @endif
        @if ($displayProducts->isNotEmpty())
            <div class="flex items-center gap-3 px-4 py-3 border-b border-b-base-300 max-md:items-stretch max-md:flex-wrap">
                <label class="input input-sm flex w-full min-w-0 max-w-80 flex-1 items-center gap-2 max-md:max-w-none print:hidden">
                    <span class="sr-only">{{ __('Search products') }}</span>
                    <svg class="shrink-0 text-base-content/60" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4" stroke-linecap="round"/></svg>
                    <input type="search" class="min-w-0 grow" x-model="search" placeholder="{{ __('Search products') }}">
                </label>
                <label class="flex items-center gap-2 text-xs text-base-content/60 print:hidden"><input type="checkbox" class="checkbox checkbox-primary checkbox-sm" x-model="lossesOnly">{{ __('Loss-making products only') }}</label>
                <span class="ms-auto text-base-content/60 text-xs whitespace-nowrap">{{ $currency }}</span>
            </div>
        @endif
        <div class="max-h-128 overflow-auto">
            <table class="table table-sm w-full whitespace-nowrap tabular-nums [&_thead]:bg-base-200/50 [&_a]:text-primary [&_a:hover]:underline">
                <thead><tr><th scope="col">{{ __('Product') }}</th><th scope="col" class="[direction:ltr] [unicode-bidi:isolate] text-left">{{ __('Net Product Sales') }}</th><th scope="col" class="[direction:ltr] [unicode-bidi:isolate] text-left">{{ __('Cost of Goods Sold') }}</th><th scope="col" class="[direction:ltr] [unicode-bidi:isolate] text-left">{{ __('Gross Profit') }}</th><th scope="col" class="[direction:ltr] [unicode-bidi:isolate] text-left">{{ __('Gross margin') }}</th></tr></thead>
                <tbody>
                    @php
                        $changeDirection = fn (?float $change) => is_null($change) || $change == 0 ? 'neutral' : ($change > 0 ? 'up' : 'down');
                    @endphp
                    @forelse ($displayProducts as $index => $product)
                        <tr class="hover:bg-base-200/40" x-show="matches(@js($product['name'].' '.($product['group'] ?? '')), @js($product['grossProfit']))">
                            <td>
                                <span class="inline-grid place-items-center w-5 h-5 me-2 rounded-lg text-base-content/60 bg-base-200/50 text-xs">{{ localizeNumber($index + 1) }}</span>
                                @can('products.show')<a class="[direction:ltr] text-right font-bold" href="{{ route('products.show', $product['id']) }}">{{ $product['name'] }}</a>@else<strong class="[direction:ltr] text-right font-bold">{{ $product['name'] }}</strong>@endcan
                                <div class="text-base-content/60 [direction:ltr] text-right text-xs">{{ $product['group'] ?? __('Ungrouped product') }}</div>
                                @if ($product['missingCost'])<span class="inline-block mt-1 text-warning text-xs">{{ __('Cost unavailable') }}</span>@endif
                            </td>
                            <td class="[direction:ltr] [unicode-bidi:isolate] text-left">
                                <strong>{{ formatNumber($product['sales']) }}</strong>
                                <div class="text-xs text-base-content/60 [&[data-direction='up']]:text-success [&[data-direction='down']]:text-error" data-direction="{{ $changeDirection($product['salesChange']) }}">
                                    @if (is_null($product['salesChange']))
                                        —
                                    @else
                                        {{ $product['salesChange'] > 0 ? '↑' : ($product['salesChange'] < 0 ? '↓' : '→') }} {{ formatNumber(abs($product['salesChange'])) }}
                                    @endif
                                </div>
                            </td>
                            <td class="[direction:ltr] [unicode-bidi:isolate] text-left text-base-content/60!">{{ formatNumber($product['cogs']) }}</td>
                            <td class="[direction:ltr] [unicode-bidi:isolate] text-left">
                                <strong class="{{ $product['grossProfit'] < 0 ? 'text-error!' : '' }}">{{ formatNumber($product['grossProfit']) }}</strong>
                                <div class="text-xs text-base-content/60 [&[data-direction='up']]:text-success [&[data-direction='down']]:text-error" data-direction="{{ $changeDirection($product['grossProfitChange']) }}">
                                    @if (is_null($product['grossProfitChange']))
                                        —
                                    @else
                                        {{ $product['grossProfitChange'] > 0 ? '↑' : ($product['grossProfitChange'] < 0 ? '↓' : '→') }} {{ formatNumber(abs($product['grossProfitChange'])) }}
                                    @endif
                                </div>
                            </td>
                            <td class="[direction:ltr] [unicode-bidi:isolate] text-left"><span class="inline-block px-2 py-1 rounded-lg text-xs font-bold {{ ! is_null($product['grossMargin']) && $product['grossMargin'] < 10 ? 'text-error bg-error/10' : 'text-success bg-success/10' }}">{{ is_null($product['grossMargin']) ? '—' : localizeNumber(number_format($product['grossMargin'], 1)).'%' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-base-content/60 text-center text-xs">{{ __('No approved product sales in this period. Product profitability will appear after sales are posted.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p x-cloak x-show="@js($displayProducts->map(fn ($product) => ['name' => $product['name'].' '.($product['group'] ?? ''), 'profit' => $product['grossProfit']])->values()).filter(product => matches(product.name, product.profit)).length === 0 && (search !== '' || lossesOnly)" class="px-4 py-8 text-base-content/60 text-center text-xs">{{ __('No products match these filters.') }}</p>
    </div>
</section>
