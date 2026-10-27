<x-app-layout :title="__('Monthly Income and Expense Workbench') . ' · ' . $selectedMonthLabel . ' ' . localizeNumber(config('active-company-fiscal-year'))">
    <x-show-message-bags />

    @php
        $previousMonthLabel = $months[$selectedMonth - 1] ?? null;
        $fiscalYearLabel = localizeNumber(config('active-company-fiscal-year'));
        $periodStatus = $profitability['isFuture']
            ? __('Upcoming month')
            : ($profitability['isComplete'] ? __('Completed month') : __('Month to date'));
        $expensePalette = ['#5b5bd6', '#7e8ff0', '#a6b3f4', '#d0a55d', '#b7bfd0'];
        $expenseDonutItems = $categoryDrivers['expense']->take(4)->values();
        $hasExpenseComposition = !is_null($actualExpense) && $actualExpense < 0;
        $expenseDonutStops = [];
        $expenseDonutOffset = 0;

        foreach ($expenseDonutItems as $index => $item) {
            $share = $hasExpenseComposition ? max(0, (float) ($item['share'] ?? 0)) : 0;
            $start = $expenseDonutOffset;
            $expenseDonutOffset = min(100, $expenseDonutOffset + $share);

            if ($expenseDonutOffset > $start) {
                $expenseDonutStops[] = $expensePalette[$index] . ' ' . $start . '% ' . $expenseDonutOffset . '%';
            }
        }

        $otherExpenseShare = $hasExpenseComposition ? max(0, 100 - $expenseDonutOffset) : 0;
        if ($otherExpenseShare > 0) {
            $expenseDonutStops[] = $expensePalette[4] . ' ' . $expenseDonutOffset . '% 100%';
        }
        $expenseDonutGradient = $expenseDonutStops ? 'conic-gradient(' . implode(', ', $expenseDonutStops) . ')' : 'var(--bw-line)';
        $expenseDonutAmount = abs((float) ($actualExpense ?? 0));
        $expenseDonutAmountParts = formatCompactCurrencyParts($expenseDonutAmount, $currency);
        $expenseDonutCenter = $hasExpenseComposition
            ? $expenseDonutAmountParts['value']
            : '—';
    @endphp

    <div dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}" class="budget-workbench">
        <header class="budget-page-head">
            <div class="budget-page-heading">
                <div class="budget-eyebrow">
                    @can('reports.cost-income')<a
                    href="{{ route('reports.cost-income') }}">{{ __('Annual income and expense report') }}</a>@else<span>{{ __('Reports') }}</span>@endcan
                    <span aria-hidden="true">/</span><span>{{ __('Actual Income') }} · {{ __('Actual Expense') }}</span>
                </div>
                <h1 class="budget-title">{{ __('Monthly Financial Performance') }} {{ $selectedMonthLabel }}
                    {{ $fiscalYearLabel }}
                </h1>
                <p class="budget-subtitle">{{ __('A unified view of business income, expenses, and profitability.') }} ·
                    {{ $periodStatus }}
                </p>
            </div>
            <div class="budget-head-actions">
                <form method="GET" action="{{ route('budgets.index') }}" class="budget-month-switch">
                    @if ($selectedMonth < 12)
                        <a class="budget-icon-button" href="{{ route('budgets.index', ['month' => $selectedMonth + 1]) }}"
                            aria-label="{{ __('Next month') }}" title="{{ __('Next month') }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    @endif
                    <label class="sr-only" for="budget-month">{{ __('Month') }} · {{ $fiscalYearLabel }}</label>
                    <select id="budget-month" name="month" onchange="this.form.submit()">
                        @foreach ($months as $monthNumber => $monthName)
                            <option value="{{ $monthNumber }}" @selected($selectedMonth === $monthNumber)>{{ $monthName }}
                            </option>
                        @endforeach
                    </select>
                    @if ($selectedMonth > 1)
                        <a class="budget-icon-button" href="{{ route('budgets.index', ['month' => $selectedMonth - 1]) }}"
                            aria-label="{{ __('Previous month') }}" title="{{ __('Previous month') }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m15 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    @endif
                    <noscript><button class="budget-button" type="submit">{{ __('View') }}</button></noscript>
                </form>
                <button class="budget-button" type="button" onclick="window.print()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.8">
                        <path
                            d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"
                            stroke-linejoin="round" />
                    </svg>
                    {{ __('Export PDF') }}
                </button>
            </div>
        </header>

        @if (!$hasDocuments)
            <div class="budget-notice" role="status">
                {{ __('No accounting document exists for calculating actual income and expense.') }}
            </div>
        @endif

        <section id="monthly-overview" aria-label="{{ __('Monthly overview') }}">
            <span class="sr-only">{{ __('Monthly overview') }}</span>
            @include('monthly-budgets.partials.overview')
        </section>

        <section id="monthly-analysis" class="budget-section" aria-labelledby="monthly-analysis-title">
            <div class="budget-section-heading">
                <div>
                    <h2 id="monthly-analysis-title">{{ __('Monthly financial picture') }}</h2>
                    <p>{{ __('Income compared with expenses and the leading expense accounts.') }}</p>
                </div>
                <span class="budget-document-count"><i
                        aria-hidden="true"></i>{{ trans_choice(':count accounting documents', $documentCount, ['count' => localizeNumber($documentCount)]) }}</span>
            </div>
            @php
                $flowScale = max(abs((float) ($actualIncome ?? 0)), abs((float) ($actualExpense ?? 0)), 1);
                $incomeWidth = $actualIncome === null ? 0 : min(100, abs($actualIncome) / $flowScale * 100);
                $expenseWidth = $actualExpense === null ? 0 : min(100, abs($actualExpense) / $flowScale * 100);
                $netWidth = $profitability['accountingNet'] === null ? 0 : min(100, abs($profitability['accountingNet']) / $flowScale * 100);
                $marginValue = $profitability['accountingMargin'];
            @endphp
            <div class="budget-analysis-grid">
                <article class="budget-card budget-flow-card">
                    <div class="budget-card-heading">
                        <div>
                            <h3>{{ __('Income to net profit') }}</h3>
                            <p>{{ __('All amounts in :currency · rounded for display', ['currency' => $currency]) }}</p>
                        </div>
                        @if (!is_null($profitability['accountingNet']))
                            <span
                                class="budget-result-badge {{ $profitability['accountingNet'] >= 0 ? 'is-positive' : 'is-negative' }}">{{ $profitability['accountingNet'] >= 0 ? __('Profit') : __('Loss') }}</span>
                        @endif
                    </div>
                    <div class="budget-flow-content">
                        <div class="budget-flow-summary">
                            <span>{{ __('From every 100 units of income', ['currency' => $currency]) }}</span><strong>{{ is_null($marginValue) ? '—' : localizeNumber(number_format($marginValue, 1)) . '%' }}
                                <small>{{ __('net margin') }}</small></strong>
                        </div>
                        @foreach ([['income', __('Actual Income'), $actualIncome, $incomeWidth], ['expense', __('Actual Expense'), $actualExpense, $expenseWidth], ['net', __('Accounting Net Profit or Loss'), $profitability['accountingNet'], $netWidth]] as [$key, $label, $value, $width])
                            <div class="budget-flow-row">
                                <span>{{ $label }}</span>
                                <div class="budget-flow-track"><i
                                        class="flow-{{ $key }} {{ $key === 'net' && $value !== null && $value < 0 ? 'flow-net-negative' : '' }}"
                                        style="width: {{ $width }}%"></i></div>
                                <strong>{{ is_null($value) ? '—' : formatCompactCurrency($key === 'expense' ? abs($value) : $value, $currency) }}</strong>
                            </div>
                        @endforeach
                        @if (!$profitability['comparisonUnavailable'])
                            <div class="budget-analysis-note">
                                <span>{{ __('Changes compared with :month', ['month' => $previousMonthLabel]) }}:</span>
                                <span>
                                    {{ __('Actual Income') }}
                                    @include('monthly-budgets.partials.change', [
                                        'comparison' => $profitability['accountingComparisons']['income'],
                                        'increaseIsGood' => true,
                                        'neutral' => false,
                                        'points' => false,
                                        'compact' => true,
                                        'currency' => $currency,
                                    ])
                                </span>
                                <span>
                                    {{ __('Actual Expense') }}
                                    @include('monthly-budgets.partials.change', [
                                        'comparison' => $profitability['accountingComparisons']['expense'],
                                        'increaseIsGood' => false,
                                        'neutral' => false,
                                        'points' => false,
                                        'compact' => true,
                                        'currency' => $currency,
                                    ])
                                </span>
                            </div>
                        @endif
                    </div>
                </article>
                <article class="budget-card budget-expense-composition">
                    <div class="budget-card-heading">
                        <div>
                            <h3>{{ __('Expenses at a glance') }}</h3>
                            <p>{{ __('Share of actual expenses') }}</p>
                        </div>
                        <a class="budget-text-link" href="#monthly-drivers">{{ __('Details') }} <span
                                aria-hidden="true">←</span></a>
                    </div>
                    <div class="budget-composition">
                        <div class="budget-donut" role="img" aria-label="{{ __('Expense composition') }}"
                            style="background:{{ $expenseDonutGradient }}">
                            <div class="budget-donut-center">
                                <strong>{{ $expenseDonutCenter }}</strong><span>{{ $expenseDonutAmountParts['unit'] }}</span>
                            </div>
                        </div>
                        <div class="budget-legend">
                            @foreach ($expenseDonutItems as $index => $item)
                                <div class="budget-legend-row">
                                    <i style="background:{{ $expensePalette[$index] }}"></i>
                                    <a
                                        href="{{ route('transactions.index', ['subject_id' => $item['subject']->id]) }}">{{ $item['subject']->name }}</a>
                                    <strong>{{ is_null($item['share']) ? '—' : localizeNumber(number_format($item['share'], 1)) . '%' }}</strong>
                                </div>
                            @endforeach
                            <div class="budget-legend-row">
                                <i style="background:{{ $expensePalette[4] }}"></i>
                                <span>{{ __('Other expenses') }}</span>
                                <strong>{{ $hasExpenseComposition ? localizeNumber(number_format($otherExpenseShare, 1)) . '%' : '—' }}</strong>
                            </div>
                            @if ($expenseDonutItems->isEmpty())
                                <p class="budget-composition-empty">
                                    {{ __('No expense accounts are available for this period.') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section id="monthly-drivers" class="budget-section" aria-labelledby="monthly-drivers-title">
            <div class="budget-section-heading">
                <div>
                    <h2 id="monthly-drivers-title">{{ __('Income and expense drivers') }}</h2>
                    <p>{{ __('Top five second-level accounts (Moein), ranked by actual balance including all descendants.') }}
                    </p>
                </div>
            </div>
            <div class="budget-driver-grid">
                @foreach (['income' => __('Income'), 'expense' => __('Expense')] as $type => $title)
                    @include('monthly-budgets.partials.drivers')
                @endforeach
            </div>
            @include('monthly-budgets.partials.module-costs')
        </section>

        @include('monthly-budgets.partials.products')
        @include('monthly-budgets.partials.forecasts')

        <details class="budget-methodology">
            <summary>{{ __('Calculation basis and report guide') }}</summary>
            <div class="budget-method-content">
                <p><strong>{{ __('Accounting result') }}:</strong>
                    {{ __('Income and expenses use approved accounting documents. Net result is income minus expenses; cost of goods sold is not deducted again.') }}
                </p>
                <p><strong>{{ __('Product performance') }}:</strong>
                    {{ __('Based on approved invoices and stored sale cost snapshots. Sales and purchases exclude VAT and include line discounts, returns, and voids.') }}
                    {{ __('Purchases are inventory movements and are shown separately from cost of goods sold.') }}
                </p>
                <p><strong>{{ __('System forecast') }}:</strong>
                    {{ __('Completed months use actual results as the system forecast. Manual forecasts replace the system forecast for the selected subject.') }}
                </p>
                <p><strong>{{ __('Monthly comparison') }}:</strong>
                    {{ $profitability['comparisonUnavailable'] ? __('Previous month comparison is unavailable for the first month of the fiscal year.') : __('Changes compared with :month', ['month' => $previousMonthLabel]) }}.
                    {{ $profitability['isCurrentMonth'] ? __('Current month comparisons use the same elapsed days of the previous month.') : __('Full month comparison') }}.
                </p>
            </div>
        </details>
    </div>

    @push('footer')
        <style>
            .budget-workbench {
                --bw-bg: #f5f7fb;
                --bw-surface: #fff;
                --bw-soft: #f8fafc;
                --bw-text: #172033;
                --bw-muted: #6c778c;
                --bw-line: #e7ebf1;
                --bw-primary: #5b5bd6;
                --bw-primary-soft: #eeeeff;
                --bw-green: #11855b;
                --bw-green-soft: #eaf8f1;
                --bw-red: #d14b5a;
                --bw-red-soft: #fff0f2;
                --bw-amber: #a96809;
                --bw-shadow: 0 1px 2px rgba(24, 34, 54, .025), 0 8px 30px rgba(24, 34, 54, .035);
                width: min(1240px, calc(100% - 24px));
                margin-inline: auto;
                padding-block: 1rem 1.5rem;
                color: var(--bw-text);
                font-family: Vazirmatn, Tahoma, sans-serif;
                font-size: 14px;
                line-height: 1.6;
            }

            body:has(.budget-workbench) main:has(.budget-workbench) {
                margin-top: 0 !important;
            }

            body:has(.budget-workbench) {
                background: #f5f7fb !important;
            }

            html[data-theme="dark"] body:has(.budget-workbench) {
                background: #10131a !important;
            }

            [data-theme="dark"] .budget-workbench {
                --bw-bg: #10131a;
                --bw-surface: #181d27;
                --bw-soft: #202633;
                --bw-text: #edf1f7;
                --bw-muted: #99a4b7;
                --bw-line: #2b3341;
                --bw-primary: #9999f4;
                --bw-primary-soft: #292942;
                --bw-green: #58d6a2;
                --bw-green-soft: #19352e;
                --bw-red: #f18a96;
                --bw-red-soft: #38252c;
                --bw-amber: #e7b55c;
                --bw-shadow: none;
            }

            .budget-workbench *,
            .budget-workbench *::before,
            .budget-workbench *::after {
                box-sizing: border-box;
            }

            .budget-workbench a {
                color: inherit;
                text-decoration: none;
            }

            .budget-workbench button,
            .budget-workbench input,
            .budget-workbench select {
                font: inherit;
            }

            .budget-workbench button,
            .budget-workbench select,
            .budget-workbench summary {
                cursor: pointer;
            }

            .budget-workbench :is(a, button, input, select, summary):focus-visible {
                outline: 3px solid color-mix(in srgb, var(--bw-primary) 30%, transparent);
                outline-offset: 3px;
            }

            .budget-page-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1.25rem;
                padding-block: .75rem 1.5rem;
            }

            .budget-page-heading {
                min-width: 0;
            }

            .budget-eyebrow {
                display: flex;
                align-items: center;
                gap: .45rem;
                margin-bottom: .25rem;
                color: var(--bw-muted);
                font-size: .75rem;
            }

            .budget-eyebrow a {
                color: var(--bw-primary);
            }

            .budget-title {
                margin: 0;
                font-size: clamp(1.35rem, 2.5vw, 1.65rem);
                line-height: 1.4;
                letter-spacing: -.025em;
                font-weight: 800;
            }

            .budget-subtitle {
                margin: .25rem 0 0;
                color: var(--bw-muted);
                font-size: .78rem;
            }

            .budget-head-actions {
                display: flex;
                align-items: center;
                gap: .6rem;
            }

            .budget-month-switch {
                display: flex;
                align-items: center;
                padding: .2rem;
                border: 1px solid var(--bw-line);
                border-radius: .7rem;
                background: var(--bw-surface);
                box-shadow: var(--bw-shadow);
            }

            .budget-month-switch select {
                appearance: none;
                min-width: 7.5rem;
                padding: .45rem .55rem;
                border: 0;
                outline: 0;
                color: var(--bw-text);
                background: transparent;
                font-size: .78rem;
                font-weight: 700;
                text-align: center;
            }

            .budget-icon-button {
                display: grid;
                place-items: center;
                width: 2.15rem;
                height: 2.15rem;
                border: 0;
                border-radius: .5rem;
                color: var(--bw-muted) !important;
                background: transparent;
            }

            .budget-icon-button:hover {
                color: var(--bw-primary) !important;
                background: var(--bw-soft);
            }

            .budget-button {
                display: inline-flex;
                min-height: 2.35rem;
                align-items: center;
                justify-content: center;
                gap: .45rem;
                padding: .45rem .8rem;
                border: 1px solid var(--bw-line);
                border-radius: .65rem;
                color: var(--bw-text);
                background: var(--bw-surface);
                font-size: .75rem;
                font-weight: 600;
                transition: .2s;
            }

            .budget-button:hover {
                border-color: var(--bw-primary);
                color: var(--bw-primary);
            }

            .budget-button-primary {
                color: #fff;
                border-color: var(--bw-primary);
                background: var(--bw-primary);
            }

            .budget-button-primary:hover {
                color: #fff;
                filter: brightness(1.06);
            }

            .budget-notice {
                margin-bottom: .9rem;
                padding: .75rem .9rem;
                border-inline-start: 3px solid var(--bw-amber);
                border-radius: .5rem;
                color: var(--bw-text);
                background: color-mix(in srgb, var(--bw-amber) 9%, var(--bw-surface));
                font-size: .76rem;
                line-height: 1.8;
            }

            .budget-card {
                min-width: 0;
                border: 1px solid var(--bw-line);
                border-radius: 1rem;
                background: var(--bw-surface);
                box-shadow: var(--bw-shadow);
                overflow: hidden;
            }

            .budget-hero {
                display: grid;
                grid-template-columns: 1.55fr 1fr;
                gap: 1rem;
            }

            .budget-profit-card {
                position: relative;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                gap: 1rem;
                padding: 1.6rem 1.75rem;
                overflow: hidden;
                background: linear-gradient(130deg, var(--bw-surface) 58%, var(--bw-primary-soft));
            }

            .budget-profit-card::after {
                content: '';
                position: absolute;
                width: 11rem;
                height: 11rem;
                border: 2.25rem solid color-mix(in srgb, var(--bw-primary) 6%, transparent);
                border-radius: 50%;
                inset-inline-start: -5rem;
                top: -4.7rem;
                pointer-events: none;
            }

            .budget-card-label {
                display: flex;
                align-items: center;
                gap: .5rem;
                color: var(--bw-muted);
                font-size: .75rem;
                font-weight: 500;
            }

            .budget-label-icon {
                display: grid;
                place-items: center;
                width: 1.8rem;
                height: 1.8rem;
                border-radius: .5rem;
                color: var(--bw-primary);
                background: var(--bw-primary-soft);
                font-size: 1rem;
                font-weight: 700;
            }

            .budget-label-icon.is-expense {
                color: var(--bw-red);
                background: var(--bw-red-soft);
            }

            .budget-profit-value {
                text-align: right;
                margin-top: .65rem;
                font-size: clamp(1.8rem, 3vw, 2.4rem);
                line-height: 1.25;
                letter-spacing: -.04em;
                font-weight: 800;
                font-variant-numeric: tabular-nums;
            }

            .budget-profit-value span {
                color: var(--bw-muted);
                font-size: .9rem;
                letter-spacing: 0;
                font-weight: 500;
            }

            .budget-profit-meta {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: .55rem;
                margin-top: .55rem;
                color: var(--bw-muted);
                font-size: .72rem;
            }

            .budget-profit-bottom {
                display: flex;
                align-items: center;
                gap: .8rem;
                padding-top: 1rem;
                border-top: 1px solid var(--bw-line);
            }

            .budget-margin-number {
                color: var(--bw-primary);
                font-size: 1.4rem;
                font-weight: 800;
            }

            .budget-metric-foot {
                color: var(--bw-muted);
                font-size: .67rem;
                line-height: 1.8;
            }

            .budget-mini-progress {
                height: .4rem;
                flex: 1;
                min-width: 5rem;
                overflow: hidden;
                border-radius: 99px;
                background: var(--bw-line);
            }

            .budget-mini-progress span {
                display: block;
                height: 100%;
                border-radius: inherit;
                background: var(--bw-primary);
            }

            .budget-profit-change {
                font-size: .68rem;
            }

            .budget-summary-side {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .budget-summary-metric {
                min-width: 0;
                padding: 1.45rem;
            }

            .budget-summary-metric+.budget-summary-metric {
                border-inline-start: 1px solid var(--bw-line);
            }

            .budget-metric-value {
                text-align: right;
                margin-block: .8rem .5rem;
                overflow-wrap: anywhere;
                font-size: clamp(1.15rem, 2vw, 1.5rem);
                font-weight: 800;
                font-variant-numeric: tabular-nums;
            }

            .budget-change {
                display: inline-flex;
                align-items: center;
                gap: .2rem;
                font-size: .68rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .budget-change[data-direction="up"] {
                color: var(--bw-green);
            }

            .budget-change[data-direction="down"] {
                color: var(--bw-red);
            }

            .budget-change[data-direction="neutral"] {
                color: var(--bw-muted);
            }

            .budget-kpi-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                margin-top: 1rem;
            }

            .budget-kpi {
                position: relative;
                min-width: 0;
                padding: 1.15rem 1.35rem;
            }

            .budget-kpi+.budget-kpi {
                border-inline-start: 1px solid var(--bw-line);
            }

            .budget-kpi-title {
                display: flex;
                align-items: center;
                gap: .45rem;
                color: var(--bw-muted);
                font-size: .75rem;
            }

            .budget-kpi-dot {
                width: .45rem;
                height: .45rem;
                border-radius: 50%;
            }

            .budget-kpi-value {
                direction: ltr;
                text-align: right;
                margin-block: .45rem;
                overflow-wrap: anywhere;
                font-size: 1.2rem;
                font-weight: 800;
                font-variant-numeric: tabular-nums;
            }

            .budget-kpi-foot {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: .35rem;
                color: var(--bw-muted);
                font-size: .67rem;
            }

            .budget-kpi-extra {
                margin-top: .35rem;
                color: var(--bw-muted);
                font-size: .64rem;
                line-height: 1.7;
            }

            .budget-positive {
                color: var(--bw-green) !important;
            }

            .budget-negative {
                color: var(--bw-red) !important;
            }

            .budget-muted {
                color: var(--bw-muted) !important;
            }

            .budget-section {
                margin-top: 1.8rem;
                scroll-margin-top: 1rem;
            }

            .budget-section-heading {
                display: flex;
                align-items: end;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: .8rem;
            }

            .budget-section-heading h2 {
                margin: 0;
                font-size: 1.05rem;
                line-height: 1.5;
                font-weight: 800;
            }

            .budget-section-heading p {
                margin: .2rem 0 0;
                color: var(--bw-muted);
                font-size: .74rem;
            }

            .budget-document-count {
                display: inline-flex;
                align-items: center;
                gap: .45rem;
                color: var(--bw-muted);
                font-size: .7rem;
                white-space: nowrap;
            }

            .budget-document-count i {
                width: .4rem;
                height: .4rem;
                border-radius: 50%;
                background: var(--bw-green);
            }

            .budget-analysis-grid {
                display: grid;
                grid-template-columns: 1.2fr .8fr;
                gap: 1rem;
            }

            .budget-card-heading {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: .7rem;
                padding: 1.1rem 1.25rem;
                border-bottom: 1px solid var(--bw-line);
            }

            .budget-card-heading h3 {
                margin: 0;
                font-size: .86rem;
                font-weight: 700;
            }

            .budget-card-heading p {
                margin: .15rem 0 0;
                color: var(--bw-muted);
                font-size: .67rem;
            }

            .budget-result-badge {
                padding: .2rem .5rem;
                border-radius: 999px;
                font-size: .67rem;
                font-weight: 700;
            }

            .budget-result-badge.is-positive {
                color: var(--bw-green);
                background: var(--bw-green-soft);
            }

            .budget-result-badge.is-negative {
                color: var(--bw-red);
                background: var(--bw-red-soft);
            }

            .budget-flow-content {
                padding: 1.35rem 1.4rem 1.1rem;
            }

            .budget-flow-summary {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: .7rem;
                margin-bottom: 1.1rem;
                color: var(--bw-muted);
                font-size: .71rem;
            }

            .budget-flow-summary strong {
                color: var(--bw-text);
                font-size: 1.15rem;
                font-variant-numeric: tabular-nums;
            }

            .budget-flow-summary small {
                color: var(--bw-muted);
                font-size: .65rem;
                font-weight: 400;
            }

            .budget-flow-row {
                display: grid;
                grid-template-columns: 5rem 1fr 8.5rem;
                align-items: center;
                gap: .7rem;
                margin-block: .95rem;
                font-size: .72rem;
            }

            .budget-flow-row strong {
                text-align: left;
                overflow-wrap: anywhere;
                font-variant-numeric: tabular-nums;
            }

            .budget-flow-track {
                height: .7rem;
                overflow: hidden;
                border-radius: .3rem;
                background: var(--bw-soft);
                direction: rtl;
            }

            .budget-flow-track i {
                display: block;
                height: 100%;
                border-radius: inherit;
                animation: budget-grow .8s ease-out;
                transform-origin: right;
            }

            .budget-flow-track .flow-income {
                background: var(--bw-primary);
            }

            .budget-flow-track .flow-expense {
                background: #8a95ad;
            }

            .budget-flow-track .flow-net {
                background: var(--bw-green);
            }

            .budget-flow-track .flow-net-negative {
                background: var(--bw-red);
            }

            @keyframes budget-grow {
                from {
                    transform: scaleX(0);
                }
            }

            .budget-analysis-note {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: .65rem;
                margin-top: 1.2rem;
                padding-top: .8rem;
                border-top: 1px dashed var(--bw-line);
                color: var(--bw-muted);
                font-size: .66rem;
            }

            .budget-analysis-note>span {
                display: inline-flex;
                align-items: center;
                gap: .25rem;
            }

            .budget-text-link {
                color: var(--bw-primary) !important;
                font-size: .7rem;
                font-weight: 600;
            }

            .budget-composition {
                display: flex;
                align-items: center;
                gap: 1.25rem;
                padding: 1.2rem 1.25rem;
            }

            .budget-donut {
                display: grid;
                flex: 0 0 8rem;
                place-items: center;
                width: 8rem;
                height: 8rem;
                border-radius: 50%;
                position: relative;
            }

            .budget-donut::after {
                content: '';
                position: absolute;
                inset: 1.05rem;
                border-radius: 50%;
                background: var(--bw-surface);
            }

            .budget-donut-center {
                z-index: 1;
                text-align: center;
            }

            .budget-donut-center strong {
                display: block;
                font-size: 1.2rem;
            }

            .budget-donut-center span {
                color: var(--bw-muted);
                font-size: .63rem;
            }

            .budget-legend {
                flex: 1;
                min-width: 0;
            }

            .budget-legend-row {
                display: flex;
                align-items: center;
                gap: .45rem;
                margin-block: .55rem;
                font-size: .69rem;
            }

            .budget-legend-row i {
                flex: none;
                width: .45rem;
                height: .45rem;
                border-radius: .15rem;
            }

            .budget-legend-row a,
            .budget-legend-row span {
                min-width: 0;
                overflow: hidden;
                color: var(--bw-muted);
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .budget-legend-row a:hover {
                color: var(--bw-primary);
                text-decoration: underline;
            }

            .budget-legend-row strong {
                margin-inline-start: auto;
                font-size: .68rem;
            }

            .budget-composition-empty {
                color: var(--bw-muted);
                font-size: .68rem;
            }

            .budget-driver-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }

            .budget-driver-list {
                padding: .45rem 1.25rem .7rem;
            }

            .budget-driver-row {
                padding-block: .75rem;
            }

            .budget-driver-row+.budget-driver-row {
                border-top: 1px solid var(--bw-line);
            }

            .budget-driver-top {
                display: flex;
                align-items: center;
                gap: .65rem;
            }

            .budget-driver-subject {
                min-width: 0;
            }

            .budget-driver-subject a {
                display: block;
                overflow: hidden;
                color: var(--bw-text);
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: .73rem;
                font-weight: 600;
            }

            .budget-driver-subject a:hover {
                color: var(--bw-primary);
                text-decoration: underline;
            }

            .budget-driver-subject small {
                display: block;
                color: var(--bw-muted);
                direction: ltr;
                text-align: right;
                font-family: ui-monospace, monospace;
                font-size: .62rem;
            }

            .budget-driver-amount {
                margin-inline-start: auto;
                direction: ltr;
                text-align: left;
                font-size: .73rem;
                font-weight: 700;
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
            }

            .budget-driver-trend {
                width: 4.5rem;
                direction: ltr;
                text-align: left;
                color: var(--bw-muted);
                font-size: .64rem;
                white-space: nowrap;
            }

            .budget-driver-trend[data-direction="up"] {
                color: var(--bw-green);
            }

            .budget-driver-trend[data-direction="down"] {
                color: var(--bw-red);
            }

            .budget-driver-track {
                height: .28rem;
                margin-top: .55rem;
                overflow: hidden;
                border-radius: 99px;
                background: var(--bw-soft);
            }

            .budget-driver-track span {
                display: block;
                height: 100%;
                border-radius: inherit;
                background: var(--bw-primary);
            }

            .is-expense .budget-driver-track span {
                background: #8a95ad;
            }

            .budget-driver-icon {
                font-size: 1.1rem;
            }

            .budget-empty {
                padding: 2rem 1rem;
                color: var(--bw-muted);
                text-align: center;
                font-size: .75rem;
            }

            .budget-module-strip {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                margin-top: 1rem;
                padding-top: .35rem;
                border-top: 1px solid var(--bw-line);
                border-radius: 1rem;
                box-shadow: none;
            }

            .budget-module {
                display: flex;
                align-items: center;
                gap: .75rem;
                min-width: 0;
                padding: 1rem 1.25rem;
            }

            .budget-module+.budget-module {
                border-inline-start: 1px solid var(--bw-line);
            }

            .budget-module-copy {
                min-width: 0;
            }

            .budget-module-copy strong {
                display: block;
                font-size: .75rem;
            }

            .budget-module-copy small {
                display: block;
                overflow: hidden;
                color: var(--bw-muted);
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: .64rem;
            }

            .budget-module-value {
                margin-inline-start: auto;
                direction: ltr;
                text-align: left;
                font-size: .73rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .budget-module[data-module-cost="cogs"] .budget-module-value {
                color: #c2831f;
            }

            .budget-module[data-module-cost="payroll"] .budget-module-value {
                color: #7660d8;
            }

            .budget-module[data-module-cost="services"] .budget-module-value {
                color: #d35f75;
            }

            .budget-table-card {
                overflow: hidden;
            }

            .budget-table-toolbar {
                display: flex;
                align-items: center;
                gap: .75rem;
                padding: .8rem 1.1rem;
                border-bottom: 1px solid var(--bw-line);
            }

            .budget-search-box {
                position: relative;
                flex: 1;
                max-width: 20rem;
            }

            .budget-search-box svg {
                position: absolute;
                inset-inline-end: .7rem;
                top: 50%;
                color: var(--bw-muted);
                transform: translateY(-50%);
                pointer-events: none;
            }

            .budget-search-box input,
            .budget-forecast-type select {
                width: 100%;
                height: 2.25rem;
                padding: .4rem .7rem .4rem 2.1rem;
                border: 1px solid var(--bw-line);
                border-radius: .55rem;
                outline: 0;
                color: var(--bw-text);
                background: var(--bw-soft);
                font-size: .72rem;
            }

            .budget-search-box input:focus,
            .budget-forecast-type select:focus {
                border-color: var(--bw-primary);
                background: var(--bw-surface);
            }

            .budget-filter-check {
                display: flex;
                align-items: center;
                gap: .4rem;
                color: var(--bw-muted);
                font-size: .68rem;
                white-space: nowrap;
            }

            .budget-filter-check input {
                width: .95rem;
                height: .95rem;
                accent-color: var(--bw-primary);
            }

            .budget-result-count {
                margin-inline-start: auto;
                color: var(--bw-muted);
                font-size: .68rem;
                white-space: nowrap;
            }

            .budget-product-scroll {
                max-height: 34rem;
                overflow: auto;
            }

            .budget-table {
                width: 100%;
                border-collapse: collapse;
                white-space: nowrap;
                font-variant-numeric: tabular-nums;
            }

            .budget-table th {
                padding: .7rem 1.1rem;
                border-bottom: 1px solid var(--bw-line);
                color: var(--bw-muted);
                background: var(--bw-soft);
                font-size: .64rem;
                font-weight: 500;
                text-align: right;
            }

            .budget-table td {
                padding: .75rem 1.1rem;
                border-bottom: 1px solid var(--bw-line);
                font-size: .72rem;
                text-align: right;
            }

            .budget-table tbody tr:last-child td {
                border-bottom: 0;
            }

            .budget-table tbody tr:hover {
                background: var(--bw-soft);
            }

            .budget-table .num {
                direction: ltr;
                text-align: left;
            }

            .budget-table a {
                color: var(--bw-primary);
            }

            .budget-table a:hover {
                text-decoration: underline;
            }

            .budget-rank {
                display: inline-grid;
                place-items: center;
                width: 1.4rem;
                height: 1.4rem;
                margin-inline-end: .45rem;
                border-radius: .4rem;
                color: var(--bw-muted);
                background: var(--bw-soft);
                font-size: .62rem;
            }

            .budget-product-name {
                direction: ltr;
                text-align: right;
                font-weight: 700;
            }

            .budget-product-brand {
                color: var(--bw-muted);
                direction: ltr;
                text-align: right;
                font-size: .62rem;
            }

            .budget-product-change {
                font-size: .62rem;
            }

            .budget-product-change[data-direction="up"] {
                color: var(--bw-green);
            }

            .budget-product-change[data-direction="down"] {
                color: var(--bw-red);
            }

            .budget-cost-warning {
                display: inline-block;
                margin-top: .25rem;
                color: var(--bw-amber);
                font-size: .6rem;
            }

            .budget-margin-pill {
                display: inline-block;
                padding: .2rem .5rem;
                border-radius: .4rem;
                color: var(--bw-green);
                background: var(--bw-green-soft);
                font-size: .64rem;
                font-weight: 700;
            }

            .budget-margin-pill.is-low {
                color: var(--bw-red);
                background: var(--bw-red-soft);
            }

            .budget-forecast-heading {
                align-items: center;
            }

            .budget-forecast-actions {
                display: flex;
                align-items: center;
                gap: .5rem;
            }

            .budget-forecast-entry {
                position: relative;
            }

            .budget-forecast-entry>summary {
                list-style: none;
            }

            .budget-forecast-entry>summary::-webkit-details-marker {
                display: none;
            }

            .budget-forecast-entry form {
                position: absolute;
                z-index: 5;
                inset-inline-end: 0;
                top: calc(100% + .5rem);
                width: min(43rem, 90vw);
                border: 1px solid var(--bw-line);
                border-radius: .7rem;
                background: var(--bw-surface);
                box-shadow: var(--bw-shadow);
            }

            .budget-forecast-summary {
                display: grid;
                grid-template-columns: 1fr 1fr 1.25fr;
                gap: .75rem;
                padding: 1.1rem;
            }

            .budget-forecast-stat {
                padding: .8rem .9rem;
                border: 1px solid var(--bw-line);
                border-radius: .7rem;
            }

            .budget-forecast-stat>span {
                color: var(--bw-muted);
                font-size: .64rem;
            }

            .budget-forecast-stat strong {
                display: block;
                direction: ltr;
                text-align: right;
                margin-top: .25rem;
                font-size: 1rem;
                font-variant-numeric: tabular-nums;
            }

            .budget-forecast-stat small {
                display: block;
                color: var(--bw-green);
                font-size: .62rem;
            }

            .budget-forecast-progress {
                height: .25rem;
                margin-block: .35rem;
                overflow: hidden;
                border-radius: 99px;
                background: var(--bw-soft);
            }

            .budget-forecast-progress span {
                display: block;
                height: 100%;
                border-radius: inherit;
                background: var(--bw-green);
            }

            .budget-forecast-chart {
                min-width: 0;
                padding: .8rem .9rem;
                border: 1px solid var(--bw-line);
                border-radius: .7rem;
            }

            .budget-forecast-chart>div:first-child {
                display: flex;
                justify-content: space-between;
                color: var(--bw-muted);
                font-size: .68rem;
            }

            .budget-forecast-chart>div:first-child strong {
                color: var(--bw-text);
            }

            .budget-forecast-empty-note {
                margin: 0;
                padding: 0 1.1rem .8rem;
                color: var(--bw-muted);
                font-size: .67rem;
            }

            .budget-forecast-type {
                display: flex;
                align-items: center;
                gap: .45rem;
                color: var(--bw-muted);
                font-size: .66rem;
                white-space: nowrap;
            }

            .budget-forecast-type select {
                width: auto;
                min-width: 7rem;
                padding-inline: .6rem;
            }

            .budget-forecast-method {
                padding: .8rem 1.1rem;
                border-top: 1px solid var(--bw-line);
                color: var(--bw-muted);
            }

            .budget-forecast-method summary,
            .budget-methodology summary {
                list-style: none;
                cursor: pointer;
                font-size: .7rem;
                font-weight: 600;
            }

            .budget-forecast-method summary::-webkit-details-marker,
            .budget-methodology summary::-webkit-details-marker {
                display: none;
            }

            .budget-forecast-method summary::after,
            .budget-methodology summary::after {
                content: '+';
                float: inline-end;
                font-size: 1rem;
            }

            .budget-forecast-method[open] summary::after,
            .budget-methodology[open] summary::after {
                content: '−';
            }

            .budget-forecast-method dl {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: .7rem 1rem;
                margin: .7rem 0 0;
                font-size: .66rem;
                line-height: 1.8;
            }

            .budget-forecast-method dt {
                color: var(--bw-text);
                font-weight: 700;
            }

            .budget-forecast-method dd {
                margin: 0;
            }

            .budget-forecast-method>p {
                margin: .7rem 0 0;
                font-size: .66rem;
            }

            .budget-methodology {
                margin-top: 1.4rem;
                border: 1px solid var(--bw-line);
                border-radius: .75rem;
                background: var(--bw-surface);
            }

            .budget-methodology summary {
                padding: .85rem 1.1rem;
                color: var(--bw-muted);
            }

            .budget-method-content {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: .7rem 1.3rem;
                padding: 0 1.1rem 1rem;
                color: var(--bw-muted);
                font-size: .68rem;
                line-height: 1.8;
            }

            .budget-method-content p {
                margin: 0;
            }

            .budget-method-content strong {
                color: var(--bw-text);
            }

            @media (max-width: 1000px) {

                .budget-hero,
                .budget-analysis-grid {
                    grid-template-columns: 1fr;
                }

                .budget-kpi-grid {
                    grid-template-columns: 1fr 1fr;
                }

                .budget-kpi:nth-child(odd) {
                    border-inline-start: 0;
                }

                .budget-kpi:nth-child(n + 3) {
                    border-top: 1px solid var(--bw-line);
                }
            }

            @media (max-width: 720px) {
                .budget-workbench {
                    width: min(100% - 1rem, 1240px);
                    padding-top: .5rem;
                }

                .budget-page-head {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: .8rem;
                }

                .budget-head-actions {
                    width: 100%;
                    justify-content: space-between;
                }

                .budget-month-switch {
                    flex: 1;
                    justify-content: space-between;
                }

                .budget-month-switch select {
                    flex: 1;
                }

                .budget-summary-metric {
                    padding: 1rem;
                }

                .budget-profit-card {
                    padding: 1.25rem;
                }

                .budget-driver-grid {
                    grid-template-columns: 1fr;
                }

                .budget-module-strip {
                    grid-template-columns: 1fr;
                }

                .budget-module+.budget-module {
                    border-top: 1px solid var(--bw-line);
                    border-inline-start: 0;
                }

                .budget-table-toolbar {
                    align-items: stretch;
                    flex-wrap: wrap;
                }

                .budget-search-box {
                    max-width: none;
                }

                .budget-forecast-heading {
                    align-items: flex-start;
                }

                .budget-forecast-actions {
                    width: 100%;
                    justify-content: flex-end;
                }

                .budget-forecast-summary {
                    grid-template-columns: 1fr 1fr;
                }

                .budget-forecast-chart {
                    grid-column: 1 / -1;
                }

                .budget-method-content,
                .budget-forecast-method dl {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 480px) {
                .budget-summary-side {
                    grid-template-columns: 1fr;
                }

                .budget-summary-metric+.budget-summary-metric {
                    border-top: 1px solid var(--bw-line);
                    border-inline-start: 0;
                }

                .budget-kpi {
                    padding: .85rem;
                }

                .budget-flow-row {
                    grid-template-columns: 4.3rem 1fr 5.7rem;
                    gap: .4rem;
                    font-size: .64rem;
                }

                .budget-composition {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .budget-donut {
                    align-self: center;
                }

                .budget-forecast-summary {
                    grid-template-columns: 1fr;
                }

                .budget-forecast-chart {
                    grid-column: auto;
                }
            }

            @media print {

                .budget-head-actions,
                .budget-forecast-actions,
                .budget-forecast-method,
                .budget-search-box,
                .budget-filter-check,
                .budget-forecast-type {
                    display: none !important;
                }

                .budget-workbench {
                    width: 100%;
                    max-width: none;
                    padding: 0;
                }

                .budget-card,
                .budget-methodology {
                    box-shadow: none;
                    break-inside: avoid;
                }

                .budget-section {
                    break-inside: avoid;
                }
            }
        </style>
    @endpush
</x-app-layout>