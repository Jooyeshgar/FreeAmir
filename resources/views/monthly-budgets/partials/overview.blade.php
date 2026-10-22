<div class="budget-overview-grid">
    <article class="budget-result">
        <div>
            <h3 class="text-sm font-medium">{{ __('Accounting Net Profit or Loss') }}</h3>
            <div class="budget-result-value">{{ is_null($profitability['accountingNet']) ? '—' : formatNumber($profitability['accountingNet']) }}</div>
            <p class="budget-caption">{{ $currency }}</p>
        </div>
        <div>
            @include('monthly-budgets.partials.change', ['comparison' => $profitability['accountingComparisons']['accountingNet'], 'increaseIsGood' => true, 'neutral' => false, 'points' => false])
            <p class="budget-caption">{{ __('Previous month') }}: {{ is_null($profitability['accountingComparisons']['accountingNet']['previous']) ? '—' : formatNumber($profitability['accountingComparisons']['accountingNet']['previous']) }}</p>
        </div>
        <div class="border-t border-white/20 pt-3">
            <div class="flex flex-wrap justify-between gap-2 text-sm"><span>{{ __('Net margin') }}</span><strong>{{ is_null($profitability['accountingMargin']) ? '—' : localizeNumber(number_format($profitability['accountingMargin'], 1)).'%' }}</strong></div>
            @include('monthly-budgets.partials.change', ['comparison' => $profitability['accountingComparisons']['accountingMargin'], 'increaseIsGood' => true, 'neutral' => false, 'points' => true])
        </div>
    </article>
    <div class="budget-panel">
        @foreach ([['income', __('Actual Income'), $actualIncome, $forecastIncome, $incomeVariance, $incomeAchievement], ['expense', __('Actual Expense'), $actualExpense, $forecastExpense, $expenseVariance, $expenseUtilization]] as [$metric, $label, $actual, $forecast, $variance, $progress])
            <article class="budget-metric {{ $loop->first ? 'border-b border-base-300' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="budget-metric-label">{{ $label }}</h3>
                        <div class="budget-metric-value">{{ is_null($actual) ? '—' : formatNumber($actual) }}</div>
                    </div>
                    <div class="text-end">
                        <p class="budget-caption">{{ __('Previous month') }}: {{ is_null($profitability['accountingComparisons'][$metric]['previous']) ? '—' : formatNumber($profitability['accountingComparisons'][$metric]['previous']) }}</p>
                        @include('monthly-budgets.partials.change', ['comparison' => $profitability['accountingComparisons'][$metric], 'increaseIsGood' => $metric === 'income', 'neutral' => false, 'points' => false])
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap justify-between gap-2 text-xs text-base-content/65">
                    <span>{{ $metric === 'income' ? __('Forecast Income') : __('Forecast Expense') }}: <strong>{{ formatNumber($forecast) }}</strong></span>
                    <span>{{ $metric === 'income' ? __('Income achievement') : __('Expense utilization') }}: {{ $hasDocuments && $forecast != 0 ? localizeNumber(number_format($progress, 0)).'%' : '—' }}</span>
                </div>
                <div class="budget-share" aria-hidden="true"><span style="width: {{ min(100, $progress) }}%"></span></div>
                @if (! is_null($variance))
                    <p class="budget-caption">{{ __('Variance') }}: <strong class="{{ $variance >= 0 ? 'text-success' : 'text-error' }}">{{ formatNumber($variance) }}</strong> · {{ $variance == 0 ? __('Unchanged') : ($variance > 0 ? __('Favorable change') : __('Unfavorable change')) }}</p>
                @endif
            </article>
        @endforeach
    </div>
</div>

<div class="budget-panel">
    <div class="budget-panel-heading">
        <div><h3 class="font-semibold text-sm">{{ __('Product performance') }}</h3><p class="budget-caption">{{ __('Sales and purchases exclude VAT') }}</p></div>
        <a href="#monthly-products" class="budget-back">{{ __('View product details') }} <span aria-hidden="true">↗</span></a>
    </div>
    <div class="grid grid-cols-1 divide-y divide-base-300 sm:grid-cols-2 xl:grid-cols-4 xl:divide-y-0">
        @foreach ([['sales', __('Net Product Sales')], ['cogs', __('Cost of Goods Sold')], ['grossProfit', __('Gross Profit')], ['purchases', __('Net Purchases')]] as [$key, $title])
            <article class="budget-metric">
                <h3 class="budget-metric-label">{{ $title }}</h3>
                <div class="budget-metric-value">{{ formatNumber($profitability[$key]) }}</div>
                <p class="budget-caption">{{ __('Previous month') }}: {{ is_null($profitability['comparisons'][$key]['previous'] ?? null) ? '—' : formatNumber($profitability['comparisons'][$key]['previous']) }}</p>
                @include('monthly-budgets.partials.change', ['comparison' => $profitability['comparisons'][$key] ?? [], 'increaseIsGood' => true, 'neutral' => in_array($key, ['cogs', 'purchases']), 'points' => false])
                @if ($key === 'grossProfit')
                    <p class="budget-caption">{{ __('Gross margin') }}: <strong>{{ is_null($profitability['grossMargin']) ? '—' : localizeNumber(number_format($profitability['grossMargin'], 1)).'%' }}</strong></p>
                    @include('monthly-budgets.partials.change', ['comparison' => $profitability['comparisons']['grossMargin'], 'increaseIsGood' => true, 'neutral' => false, 'points' => true])
                @elseif ($key === 'purchases')
                    <p class="budget-caption">{{ __('Inventory purchased, not the cost of goods sold.') }}</p>
                @endif
            </article>
        @endforeach
    </div>
</div>
