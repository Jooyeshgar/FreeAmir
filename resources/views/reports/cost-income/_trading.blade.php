@php
    $currency = config('amir.currency') ?? __('Rial');
    $netSales = (int) ($invoices['netSales'] ?? 0);
    $netPurchases = (int) ($invoices['netPurchases'] ?? 0);
    $tradingMargin = (int) ($invoices['tradingMargin'] ?? 0);
    $tradingMax = max($netSales, $netPurchases, 1);

    $tradingCards = [
        [
            'title' => __('Net Sales'),
            'value' => $netSales,
            'suffix' => $currency,
            'tone' => 'success',
            'icon' => 'sales',
            'detail' => __(':count invoice(s)', ['count' => formatNumber($invoices['sellCount'] ?? 0)]),
        ],
        [
            'title' => __('Net Purchases'),
            'value' => $netPurchases,
            'suffix' => $currency,
            'tone' => 'warning',
            'icon' => 'buy',
            'detail' => __(':count invoice(s)', ['count' => formatNumber($invoices['buyCount'] ?? 0)]),
        ],
        [
            'title' => __('Trading Margin'),
            'value' => abs($tradingMargin),
            'suffix' => $tradingMargin >= 0 ? __('Profit') : __('Loss'),
            'tone' => $tradingMargin >= 0 ? 'primary' : 'error',
            'detail' => __('Net sales minus net purchases'),
            'icon' => 'profit',
        ],
    ];
@endphp

<section class="space-y-3">
    <div>
        <h2 class="text-lg font-bold text-base-content">{{ __('Sales and Purchases') }}</h2>
        <p class="text-xs text-base-content/55">{{ __('Trade activity sourced from invoices') }}</p>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.8fr)]">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @foreach ($tradingCards as $card)
                <x-metric-card :card="$card" />
            @endforeach
        </div>

        <article class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body p-4">
                <div class="mb-3">
                    <h3 class="card-title text-base">{{ __('Sales vs Purchases') }}</h3>
                    <p class="text-xs text-base-content/55">{{ __('Invoice totals for the current fiscal year') }}</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium">{{ __('Net Sales') }}</span>
                            <span class="tabular-nums">{{ formatNumber($netSales) }}</span>
                        </div>
                        <progress class="progress progress-success h-2" value="{{ $netSales }}" max="{{ $tradingMax }}"></progress>
                    </div>

                    <div>
                        <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium">{{ __('Net Purchases') }}</span>
                            <span class="tabular-nums">{{ formatNumber($netPurchases) }}</span>
                        </div>
                        <progress class="progress progress-warning h-2" value="{{ $netPurchases }}" max="{{ $tradingMax }}"></progress>
                    </div>
                </div>
            </div>
        </article>
    </div>
</section>
