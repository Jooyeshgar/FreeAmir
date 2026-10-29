@php
    $accountingNetAmount = is_null($profitability['accountingNet']) ? null : formatCompactCurrencyParts($profitability['accountingNet'], $currency);
    $formatPercent = fn($percent) => is_null($percent) ? '—' : localizeNumber(number_format($percent, 1)).'%';
    $previousDetail = fn($previous, $format) => __('Previous month').': '.(is_null($previous) ? '—' : $format($previous));
    $compactCurrency = fn($amount) => formatCompactCurrency($amount, $currency);
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
    <x-metric-card :title="__('Accounting Net Profit or Loss')" icon="profit"
        :tone="is_null($accountingNetAmount) ? 'placeholder' : ($profitability['accountingNet'] >= 0 ? 'success' : 'error')"
        :value="$accountingNetAmount['value'] ?? '—'" :suffix="$accountingNetAmount['unit'] ?? null"
        :change="$profitability['accountingComparisons']['accountingNet']['percent'] ?? null"
        :detail="$previousDetail($profitability['accountingComparisons']['accountingNet']['previous'] ?? null, $compactCurrency)"
        :progress="is_null($profitability['accountingMargin']) ? null : $profitability['accountingMargin']"
        :progress-label="__('Net margin').': '.$formatPercent($profitability['accountingMargin'])"
        class="md:col-span-2 print:break-inside-avoid print:shadow-none" />

    @foreach ([['income', __('Actual Income'), $actualIncome], ['expense', __('Actual Expense'), $actualExpense]] as [$metric, $label, $actual])
        <x-metric-card :title="$label" :icon="$metric === 'income' ? 'income' : 'cost'"
            :tone="is_null($actual) ? 'placeholder' : ($metric === 'income' ? 'success' : 'error')"
            :value="is_null($actual) ? '—' : formatCompactCurrency(abs($actual), $currency)"
            :change="$profitability['accountingComparisons'][$metric]['percent'] ?? null"
            :increase-is-good="$metric === 'income'"
            :detail="$previousDetail($profitability['accountingComparisons'][$metric]['previous'] ?? null, $compactCurrency)"
            class="print:break-inside-avoid print:shadow-none" />
    @endforeach
</div>

<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        ['sales', __('Net Product Sales'), 'success', 'sales', true],
        ['cogs', __('Cost of Goods Sold'), 'error', 'cost', false],
        ['grossProfit', __('Gross Profit'), $profitability['grossProfit'] >= 0 ? 'primary' : 'error', 'profit', true],
        ['purchases', __('Net Purchases'), 'warning', 'buy', false],
    ] as [$key, $title, $tone, $icon, $increaseIsGood])
        <x-metric-card :title="$title" :tone="$tone" :icon="$icon"
            :value="formatNumber($profitability[$key])"
            :change="$profitability['comparisons'][$key]['percent'] ?? null"
            :increase-is-good="$increaseIsGood"
            :detail="$previousDetail($profitability['comparisons'][$key]['previous'] ?? null, 'formatNumber')"
            :progress="$key === 'grossProfit' && ! is_null($profitability['grossMargin']) ? $profitability['grossMargin'] : null"
            :progress-label="$key === 'grossProfit' ? __('Gross margin').': '.$formatPercent($profitability['grossMargin']) : null"
            class="print:break-inside-avoid print:shadow-none" />
    @endforeach
</div>
