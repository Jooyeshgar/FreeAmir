@php
    $accountingNetAmount = is_null($profitability['accountingNet']) ? null : formatCompactCurrencyParts($profitability['accountingNet'], $currency);
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
    <x-metric-card :title="__('Accounting Net Profit or Loss')" icon="profit"
        :tone="is_null($accountingNetAmount) ? 'placeholder' : ($profitability['accountingNet'] >= 0 ? 'success' : 'error')"
        class="md:col-span-2 print:break-inside-avoid print:shadow-none">
        <x-slot:valueContent>
            <div dir="ltr" class="text-right text-3xl font-extrabold leading-tight tracking-tight tabular-nums [unicode-bidi:isolate] sm:text-4xl">
                {{ is_null($accountingNetAmount) ? '—' : $accountingNetAmount['value'].' ' }}@if (! is_null($accountingNetAmount))<span class="text-base font-medium tracking-normal text-base-content/60">{{ $accountingNetAmount['unit'] }}</span>@endif
            </div>
        </x-slot:valueContent>

        <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/60">
            @include('monthly-budgets.partials.change', [
                'comparison' => $profitability['accountingComparisons']['accountingNet'],
                'increaseIsGood' => true,
                'neutral' => false,
                'points' => false,
                'compact' => true,
                'currency' => $currency,
            ])
            @if (! is_null($profitability['accountingComparisons']['accountingNet']['change'] ?? null))
                <span>{{ formatCompactCurrency(abs($profitability['accountingComparisons']['accountingNet']['change']), $currency) }} {{ __('Compared with previous month') }}</span>
            @endif
        </div>

        <x-slot:footer>
            <div class="mt-auto flex flex-wrap items-center gap-3 border-t border-base-300 pt-3">
                <div>
                    <div class="text-xl font-bold text-primary tabular-nums">{{ is_null($profitability['accountingMargin']) ? '—' : localizeNumber(number_format($profitability['accountingMargin'], 1)).'%' }}</div>
                    <div class="text-xs text-base-content/60">{{ __('Net margin') }}</div>
                </div>
                <progress class="progress progress-primary h-1.5 min-w-20 flex-1"
                    value="{{ min(100, max(0, $profitability['accountingMargin'] ?? 0)) }}" max="100"
                    aria-label="{{ __('Net margin') }}"></progress>
                @include('monthly-budgets.partials.change', ['comparison' => $profitability['accountingComparisons']['accountingMargin'], 'increaseIsGood' => true, 'neutral' => false, 'points' => true])
            </div>
        </x-slot:footer>
    </x-metric-card>

    @foreach ([['income', __('Actual Income'), $actualIncome], ['expense', __('Actual Expense'), $actualExpense]] as [$metric, $label, $actual])
        <x-metric-card :title="$label" :icon="$metric === 'income' ? 'income' : 'cost'"
            :tone="is_null($actual) ? 'placeholder' : ($metric === 'income' ? 'success' : 'error')"
            class="print:break-inside-avoid print:shadow-none">
            <x-slot:valueContent>
                <div dir="ltr" class="wrap-anywhere text-right text-2xl font-bold leading-8 tabular-nums [unicode-bidi:isolate]">
                    {{ is_null($actual) ? '—' : formatCompactCurrency(abs($actual), $currency) }}
                </div>
            </x-slot:valueContent>
            <x-slot:footer>
                <div class="mt-auto space-y-1 text-xs text-base-content/60">
                    @include('monthly-budgets.partials.change', [
                        'comparison' => $profitability['accountingComparisons'][$metric],
                        'increaseIsGood' => $metric === 'income',
                        'neutral' => false,
                        'points' => false,
                        'compact' => true,
                        'currency' => $currency,
                    ])
                    <div>{{ __('Previous month') }}: {{ is_null($profitability['accountingComparisons'][$metric]['previous']) ? '—' : formatCompactCurrency($profitability['accountingComparisons'][$metric]['previous'], $currency) }}</div>
                </div>
            </x-slot:footer>
        </x-metric-card>
    @endforeach
</div>

<div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ([
        ['sales', __('Net Product Sales'), 'success', 'sales', true],
        ['cogs', __('Cost of Goods Sold'), 'error', 'cost', false],
        ['grossProfit', __('Gross Profit'), $profitability['grossProfit'] >= 0 ? 'primary' : 'error', 'profit', true],
        ['purchases', __('Net Purchases'), 'warning', 'buy', false],
    ] as [$key, $title, $tone, $icon, $increaseIsGood])
        <x-metric-card :title="$title" :tone="$tone" :icon="$icon" class="print:break-inside-avoid print:shadow-none">
            <x-slot:valueContent>
                <div dir="ltr" @class(['wrap-anywhere text-right text-xl font-bold leading-8 tabular-nums [unicode-bidi:isolate]', 'text-error' => $key === 'grossProfit' && $profitability[$key] < 0])>{{ formatNumber($profitability[$key]) }}</div>
            </x-slot:valueContent>
            <x-slot:footer>
                <div class="mt-auto space-y-1 text-xs text-base-content/60">
                    <div>{{ __('Previous month') }}: {{ is_null($profitability['comparisons'][$key]['previous'] ?? null) ? '—' : formatNumber($profitability['comparisons'][$key]['previous']) }}</div>
                    @include('monthly-budgets.partials.change', ['comparison' => $profitability['comparisons'][$key] ?? [], 'increaseIsGood' => $increaseIsGood, 'neutral' => false, 'points' => false])
                    @if ($key === 'grossProfit')
                        <div>{{ __('Gross margin') }}: <b @class(['text-success' => ($profitability['grossMargin'] ?? 0) >= 0, 'text-error' => ($profitability['grossMargin'] ?? 0) < 0])>{{ is_null($profitability['grossMargin']) ? '—' : localizeNumber(number_format($profitability['grossMargin'], 1)).'%' }}</b></div>
                    @elseif ($key === 'purchases')
                        <div class="leading-relaxed">{{ __('Inventory purchased, not the cost of goods sold.') }}</div>
                    @endif
                </div>
            </x-slot:footer>
        </x-metric-card>
    @endforeach
</div>
