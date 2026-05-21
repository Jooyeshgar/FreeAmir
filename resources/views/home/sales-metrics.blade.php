@php
    $currency = config('amir.currency') ?? __('Rial');
    $totalSales = $canSales ? collect($monthlySellAmount ?? [])->sum() : 0;
    $totalWarehouse = $canInventory ? collect($monthlyWarehouse ?? [])->sum() : 0;
    $topItem = $canPopularItems ? collect($popularProductsAndServices ?? [])->first() : null;

    $cards = [];

    if ($canSales) {
        $cards[] = [
            'title' => __('Sales') . ' (' . __('Year to date') . ')',
            'value' => $totalSales,
            'suffix' => $currency,
            'tone' => 'info',
            'icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.6-8M7 13l-2 7m12-7l2 7M9 21h.01M19 21h.01',
        ];
    }

    if ($canInventory) {
        $cards[] = [
            'title' => __('Warehouse'),
            'value' => $totalWarehouse,
            'suffix' => $currency,
            'tone' => 'warning',
            'icon' => 'M3 7l9-4 9 4v10l-9 4-9-4V7zm9-4v18M3 7l9 4 9-4',
        ];
    }

    if ($topItem) {
        $cards[] = [
            'title' => __('Most popular products and services'),
            'value' => (int) ($topItem['quantity'] ?? 0),
            'suffix' => $topItem['name'] ?? '-',
            'tone' => 'secondary',
            'icon' => 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
        ];
    }
@endphp

@if (count($cards))
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            @php
                $tone = [
                    'info' => ['card' => 'border-sky-500/25 bg-sky-500/10', 'icon' => 'bg-sky-500/15 text-sky-500'],
                    'error' => ['card' => 'border-rose-500/25 bg-rose-500/10', 'icon' => 'bg-rose-500/15 text-rose-500'],
                    'success' => ['card' => 'border-emerald-500/25 bg-emerald-500/10', 'icon' => 'bg-emerald-500/15 text-emerald-500'],
                    'primary' => ['card' => 'border-primary/25 bg-primary/10', 'icon' => 'bg-primary/15 text-primary'],
                    'warning' => ['card' => 'border-amber-500/25 bg-amber-500/10', 'icon' => 'bg-amber-500/15 text-amber-500'],
                    'secondary' => ['card' => 'border-violet-500/25 bg-violet-500/10', 'icon' => 'bg-violet-500/15 text-violet-500'],
                ][$card['tone']] ?? ['card' => 'border-base-300 bg-base-200', 'icon' => 'bg-base-300 text-base-content'];
            @endphp
            <article class="card border shadow-sm {{ $tone['card'] }}">
                <div class="card-body gap-3 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="rounded-lg p-2 {{ $tone['icon'] }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                            </svg>
                        </div>
                        <span class="text-xs text-base-content/60 text-right">{{ $card['title'] }}</span>
                    </div>

                    <div>
                        <div class="text-xl font-bold leading-8 text-base-content">
                            {{ formatNumber($card['value']) }}
                        </div>
                        <div class="text-xs text-base-content/60 truncate">
                            {{ $card['suffix'] }}
                        </div>
                    </div>
                </div>
            </article>
        @endforeach
    </section>
@endif
