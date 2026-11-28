@php
    $currency = config('amir.currency') ?? __('Rial');
    $profitValue = (float) ($profit ?? 0);
    $forecastDetail = isset($forecastProfit) ? __('FY forecast') . ': ' . formatNumber($forecastProfit) . ' ' . $currency . ' | ' : '';

    $cards = [
        [
            'title' => __('Total Income'),
            'value' => $totalIncome ?? 0,
            'suffix' => $currency,
            'tone' => 'success',
            'series' => array_values($monthlyIncome ?? []),
            'icon' => 'income',
        ],
        [
            'title' => __('Total Cost'),
            'value' => $totalCost ?? 0,
            'suffix' => $currency,
            'tone' => 'error',
            'series' => array_values($monthlyCost ?? []),
            'icon' => 'cost',
        ],
        [
            'title' => __('Net Profit'),
            'value' => abs($profitValue),
            'suffix' => $profitValue >= 0 ? __('Profit') : __('Loss'),
            'detail' => $forecastDetail . __('Completed') . ': ' . formatNumber(abs((float) ($actualCompletedProfit ?? 0))) . ' ' . $currency . ' (' . localizeNumber(number_format((float) ($profitCompletionPercent ?? 0), 0)) . __('Percent sign') . ')',
            'progress' => $profitCompletionPercent ?? 0,
            'progressLabel' => __('FY forecast'),
            'tone' => $profitValue >= 0 ? 'success' : 'error',
            'icon' => 'profit',
        ],
        [
            'title' => __('Profit Margin'),
            'value' => $margin ?? 0,
            'suffix' => __('Percent sign'),
            'tone' => ($margin ?? 0) >= 0 ? 'primary' : 'error',
            'icon' => 'chart',
        ],
    ];
@endphp

<section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($cards as $card)
        <x-metric-card :card="$card" />
    @endforeach
</section>
