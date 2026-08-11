@php
    $currency = config('amir.currency') ?? __('Rial');
    $totalSales = $canSales ? collect($monthlySellAmount ?? [])->sum() : 0;

    $cards = [];

    if ($canSales) {
        $cards[] = [
            'title' => __('Sales') . ' (' . __('Year to date') . ')',
            'value' => $totalSales,
            'suffix' => $currency,
            'tone' => 'info',
            'icon' => 'sales',
        ];
    }

    if ($canInventory) {
        $cards[] = [
            'title' => __('Warehouse'),
            'value' => $totalWarehouseValue ?? 0,
            'suffix' => $currency,
            'tone' => 'warning',
            'icon' => 'warehouse',
        ];
    }

    if ($canSales) {
        $cards[] = [
            'title' => __('Buy') . ' (' . __('Year to date') . ')',
            'value' => $totalBuyAmount ?? 0,
            'suffix' => $currency,
            'tone' => 'secondary',
            'icon' => 'buy',
        ];
    }
@endphp

@if (count($cards))
    <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            <x-metric-card :card="$card" />
        @endforeach
    </section>
@endif
