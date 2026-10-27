@php
    $accountingNetAmount = is_null($profitability['accountingNet']) ? null : formatCompactCurrencyParts($profitability['accountingNet'], $currency);
@endphp

<div class="budget-hero">
    <article class="budget-card budget-profit-card">
        <div class="budget-card-label"><span class="budget-label-icon" aria-hidden="true">↗</span>{{ __('Accounting Net Profit or Loss') }}</div>
        <div class="budget-profit-value">{{ is_null($accountingNetAmount) ? '—' : $accountingNetAmount['value'].' ' }}@if (! is_null($accountingNetAmount))<span>{{ $accountingNetAmount['unit'] }}</span>@endif</div>
        <div class="budget-profit-meta">
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
        <div class="budget-profit-bottom">
            <div><div class="budget-margin-number">{{ is_null($profitability['accountingMargin']) ? '—' : localizeNumber(number_format($profitability['accountingMargin'], 1)).'%' }}</div><div class="budget-metric-foot">{{ __('Net margin') }}</div></div>
            <div class="budget-mini-progress" aria-hidden="true"><span style="width:{{ min(100, max(0, $profitability['accountingMargin'] ?? 0)) }}%"></span></div>
            <div class="budget-profit-change">@include('monthly-budgets.partials.change', ['comparison' => $profitability['accountingComparisons']['accountingMargin'], 'increaseIsGood' => true, 'neutral' => false, 'points' => true])</div>
        </div>
    </article>

    <div class="budget-card budget-summary-side">
        @foreach ([['income', __('Actual Income'), $actualIncome], ['expense', __('Actual Expense'), $actualExpense]] as [$metric, $label, $actual])
            <article class="budget-summary-metric">
                <div class="budget-card-label"><span class="budget-label-icon {{ $metric === 'expense' ? 'is-expense' : '' }}" aria-hidden="true">{{ $metric === 'income' ? '↑' : '↓' }}</span>{{ $label }}</div>
                <div class="budget-metric-value">{{ is_null($actual) ? '—' : formatCompactCurrency(abs($actual), $currency) }}</div>
                @include('monthly-budgets.partials.change', [
                    'comparison' => $profitability['accountingComparisons'][$metric],
                    'increaseIsGood' => $metric === 'income',
                    'neutral' => false,
                    'points' => false,
                    'compact' => true,
                    'currency' => $currency,
                ])
                <div class="budget-metric-foot">{{ __('Previous month') }}: {{ is_null($profitability['accountingComparisons'][$metric]['previous']) ? '—' : formatCompactCurrency($profitability['accountingComparisons'][$metric]['previous'], $currency) }}</div>
            </article>
        @endforeach
    </div>
</div>

<div class="budget-card budget-kpi-grid">
    @foreach ([['sales', __('Net Product Sales'), '#5b5bd6', true], ['cogs', __('Cost of Goods Sold'), '#8a95ad', false], ['grossProfit', __('Gross Profit'), '#11855b', true], ['purchases', __('Net Purchases'), '#a96809', false]] as [$key, $title, $color, $increaseIsGood])
        <article class="budget-kpi">
            <div class="budget-kpi-title"><span class="budget-kpi-dot" style="background:{{ $color }}"></span>{{ $title }}</div>
            <div class="budget-kpi-value {{ $key === 'grossProfit' && $profitability[$key] < 0 ? 'budget-negative' : '' }}">{{ formatNumber($profitability[$key]) }}</div>
            <div class="budget-kpi-foot">
                <span>{{ __('Previous month') }}: {{ is_null($profitability['comparisons'][$key]['previous'] ?? null) ? '—' : formatNumber($profitability['comparisons'][$key]['previous']) }}</span>
                @include('monthly-budgets.partials.change', ['comparison' => $profitability['comparisons'][$key] ?? [], 'increaseIsGood' => $increaseIsGood, 'neutral' => false, 'points' => false])
            </div>
            @if ($key === 'grossProfit')
                <div class="budget-kpi-foot budget-kpi-extra">{{ __('Gross margin') }}: <b class="budget-positive">{{ is_null($profitability['grossMargin']) ? '—' : localizeNumber(number_format($profitability['grossMargin'], 1)).'%' }}</b></div>
            @elseif ($key === 'purchases')
                <div class="budget-kpi-extra">{{ __('Inventory purchased, not the cost of goods sold.') }}</div>
            @endif
        </article>
    @endforeach
</div>
