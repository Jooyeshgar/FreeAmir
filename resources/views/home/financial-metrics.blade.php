@php
    $totalBankBalance = collect($topTenBankAccountBalances ?? [])->sum() * -1;
    $totalMonthlyIncome = collect($monthlyIncome ?? [])->sum();
    $totalMonthlyCost = collect($monthlyCost ?? [])->sum();
    $profitValue = (int) ($profit ?? 0);
    $currency = config('amir.currency') ?? __('Rial');

    $incomeSeries = array_values($monthlyIncome ?? []);
    $costSeries = array_values($monthlyCost ?? []);
    $profitSeries = collect($incomeSeries)
        ->map(fn ($value, $index) => (float) $value - (float) ($costSeries[$index] ?? 0))
        ->all();

    $financialCards = [
        [
            'title' => __('Total Bank Balance'),
            'value' => $totalBankBalance,
            'suffix' => $currency,
            'tone' => 'primary',
        ],
        [
            'title' => __('Income') . ' (' . __('Year to date') . ')',
            'value' => $totalMonthlyIncome,
            'suffix' => $currency,
            'tone' => 'success',
            'series' => $incomeSeries,
        ],
        [
            'title' => __('Cost') . ' (' . __('Year to date') . ')',
            'value' => $totalMonthlyCost,
            'suffix' => $currency,
            'tone' => 'error',
            'series' => $costSeries,
        ],
        [
            'title' => __('Profit and loss'),
            'value' => abs($profitValue),
            'suffix' => $profitValue >= 0 ? __('Profit') : __('Loss'),
            'tone' => $profitValue >= 0 ? 'success' : 'error',
            'series' => $profitSeries,
        ],
    ];
@endphp

<section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($financialCards as $card)
        <x-metric-card :card="$card" />
    @endforeach
</section>
