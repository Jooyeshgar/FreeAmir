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
                    <x-user-guide-link source="reports/budgets/budgets.md" class="!h-5 !w-5 !min-h-5 !p-0" />
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
                                        data-budget-width="{{ $width }}"></i></div>
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
                            data-budget-gradient="{{ $expenseDonutGradient }}">
                            <div class="budget-donut-center">
                                <strong>{{ $expenseDonutCenter }}</strong><span>{{ $expenseDonutAmountParts['unit'] }}</span>
                            </div>
                        </div>
                        <div class="budget-legend">
                            @foreach ($expenseDonutItems as $index => $item)
                                <div class="budget-legend-row">
                                    <i class="budget-legend-swatch budget-legend-swatch-{{ $index }}"></i>
                                    <a
                                        href="{{ route('transactions.index', ['subject_id' => $item['subject']->id]) }}">{{ $item['subject']->name }}</a>
                                    <strong>{{ is_null($item['share']) ? '—' : localizeNumber(number_format($item['share'], 1)) . '%' }}</strong>
                                </div>
                            @endforeach
                            <div class="budget-legend-row">
                                <i class="budget-legend-swatch budget-legend-swatch-4"></i>
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

</x-app-layout>
