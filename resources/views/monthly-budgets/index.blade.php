<x-app-layout :title="__('Monthly Income and Expense Workbench')">
    <x-show-message-bags />

    @php
        $previousMonthLabel = $months[$selectedMonth - 1] ?? null;
        $fiscalYearLabel = localizeNumber(config('active-company-fiscal-year'));
    @endphp

    <div dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}" class="budget-workbench mx-auto w-full max-w-[1430px] space-y-5 px-3 sm:px-6">
        <header class="budget-header">
            <div class="budget-header-main">
                @can('reports.cost-income')
                    <a class="budget-back" href="{{ route('reports.cost-income') }}">{{ __('Annual income and expense report') }} <span aria-hidden="true">↗</span></a>
                @endcan
                <div class="flex items-center gap-2">
                    <h1 class="budget-page-title">{{ __('Monthly Income and Expense Workbench') }}</h1>
                    <x-user-guide-link source="reports/budgets/budgets.md" />
                </div>
            </div>
            <form method="GET" action="{{ route('budgets.index') }}" class="budget-month-picker">
                @if ($selectedMonth > 1)
                    <a class="btn btn-ghost btn-square btn-sm" href="{{ route('budgets.index', ['month' => $selectedMonth - 1]) }}" aria-label="{{ __('Previous month') }}"><span class="budget-prev" aria-hidden="true">‹</span></a>
                @endif
                <div class="budget-month-field">
                    <label for="budget-month" class="sr-only">{{ __('Month') }} · {{ $fiscalYearLabel }}</label>
                    <select id="budget-month" name="month" class="select select-ghost select-sm w-28 font-bold" onchange="this.form.submit()">
                        @foreach ($months as $monthNumber => $monthName)
                            <option value="{{ $monthNumber }}" @selected($selectedMonth === $monthNumber)>{{ $monthName }}</option>
                        @endforeach
                    </select>
                    <span class="budget-month-year" aria-hidden="true">{{ $fiscalYearLabel }}</span>
                </div>
                <noscript><button class="btn btn-sm btn-ghost" type="submit">{{ __('View') }}</button></noscript>
                @if ($selectedMonth < 12)
                    <a class="btn btn-ghost btn-square btn-sm" href="{{ route('budgets.index', ['month' => $selectedMonth + 1]) }}" aria-label="{{ __('Next month') }}"><span class="budget-next" aria-hidden="true">›</span></a>
                @endif
            </form>
        </header>

        <nav class="budget-navigation" aria-label="{{ __('Monthly report sections') }}">
            <a href="#monthly-overview">{{ __('Monthly overview') }}</a>
            <a href="#monthly-drivers">{{ __('Income and expense drivers') }}</a>
            <a href="#monthly-module-costs">{{ __('Module share of total expenses') }}</a>
            <a href="#monthly-products">{{ __('Product Profitability') }}</a>
            <a href="#monthly-forecasts">{{ __('Forecasts') }} <span class="badge badge-sm badge-ghost">{{ localizeNumber($manualForecastsCount) }}</span></a>
        </nav>

        <section id="monthly-overview" class="scroll-mt-4 space-y-4" aria-labelledby="monthly-overview-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="monthly-overview-title" class="budget-section-title">{{ __('Monthly overview') }} <span class="text-base-content/50">/ {{ $selectedMonthLabel }} {{ $fiscalYearLabel }}</span></h2>
                    <p class="budget-caption">{{ __('All amounts in :currency', ['currency' => $currency]) }} · {{ $profitability['isFuture'] ? __('Upcoming month') : ($profitability['isComplete'] ? __('Completed month') : __('Month to date')) }}</p>
                </div>
                <span class="budget-status"><span aria-hidden="true"></span>{{ trans_choice(':count accounting documents', $documentCount, ['count' => localizeNumber($documentCount)]) }}</span>
            </div>
            @if (! $hasDocuments)
                <div class="budget-notice" role="status">{{ __('No accounting document exists for calculating actual income and expense.') }}</div>
            @endif
            @if ($profitability['comparisonUnavailable'])
                <p class="budget-caption">{{ __('Previous month comparison is unavailable for the first month of the fiscal year.') }}</p>
            @else
                <p class="budget-caption">{{ __('Changes compared with :month', ['month' => $previousMonthLabel]) }} · {{ $profitability['isCurrentMonth'] ? __('Current month comparisons use the same elapsed days of the previous month.') : __('Full month comparison') }}</p>
            @endif

            @include('monthly-budgets.partials.overview')

            <details class="budget-methodology">
                <summary>{{ __('How these figures are calculated') }}</summary>
                <div class="grid gap-4 pt-3 text-xs leading-6 text-base-content/65 md:grid-cols-2">
                    <p><strong class="text-base-content">{{ __('Accounting result') }}.</strong> {{ __('Income and expenses use approved accounting documents. Net result is income minus expenses; cost of goods sold is not deducted again.') }}</p>
                    <p><strong class="text-base-content">{{ __('Product performance') }}.</strong> {{ __('Based on approved invoices and stored sale cost snapshots. Sales and purchases exclude VAT and include line discounts, returns, and voids.') }} {{ __('Purchases are inventory movements and are shown separately from cost of goods sold.') }}</p>
                </div>
            </details>
        </section>

        <section id="monthly-drivers" class="scroll-mt-4 space-y-4" aria-labelledby="monthly-drivers-title">
            <div>
                <h2 id="monthly-drivers-title" class="budget-section-title">{{ __('Income and expense drivers') }}</h2>
                <p class="budget-caption">{{ __('Top five second-level accounts (Moein), ranked by their actual balance including all descendants, independently of forecasts.') }}</p>
            </div>
            <div class="budget-driver-grid">
                @foreach (['income' => __('Actual Income Breakdown'), 'expense' => __('Actual Cost Breakdown')] as $type => $title)
                    @include('monthly-budgets.partials.drivers')
                @endforeach
                @include('monthly-budgets.partials.module-costs')
            </div>
        </section>

        @include('monthly-budgets.partials.products')
        @include('monthly-budgets.partials.forecasts')
    </div>

    @push('footer')
        <style>
            .budget-workbench { --budget-ink: #193d56; --budget-teal: #087f8c; padding-top: .5rem; }
            .budget-overview-grid, .budget-forecast-summary { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
            @media (min-width: 64rem) { .budget-overview-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); } .budget-forecast-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            .budget-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; padding-block: .15rem; }
            .budget-header-main { display: grid; gap: .1rem; min-width: 0; }
            .budget-page-title { font-size: clamp(1.05rem, 2vw, 1.35rem); font-weight: 750; line-height: 1.5; }
            .budget-back { display: inline-flex; gap: .5rem; font-size: .68rem; color: var(--budget-teal); }
            .budget-month-picker { display: flex; align-items: center; gap: .15rem; padding: .25rem; background: var(--color-base-100); border: 1px solid var(--color-base-300); border-radius: .75rem; }
            .budget-month-field { display: flex; align-items: center; gap: .25rem; }
            .budget-month-year { padding-inline-end: .4rem; font-size: .75rem; color: color-mix(in oklab, var(--color-base-content) 65%, transparent); }
            [dir=rtl] .budget-prev, [dir=rtl] .budget-next { display: inline-block; transform: rotate(180deg); }
            .budget-navigation { display: flex; gap: 1rem; overflow-x: auto; border-bottom: 1px solid var(--color-base-300); padding-bottom: .5rem; scrollbar-width: thin; }
            .budget-navigation a { flex-shrink: 0; padding: .35rem .15rem; font-size: .8rem; font-weight: 600; white-space: nowrap; color: var(--color-base-content); }
            .budget-navigation a:hover { color: var(--budget-teal); }
            .budget-section-title { font-size: 1rem; font-weight: 750; line-height: 1.8; }
            .budget-caption { margin-top: .25rem; font-size: .75rem; line-height: 1.8; color: color-mix(in oklab, var(--color-base-content) 65%, transparent); }
            .budget-panel { min-width: 0; border: 1px solid var(--color-base-300); border-radius: 1rem; background: var(--color-base-100); overflow: hidden; }
            .budget-panel-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: 1.25rem; border-bottom: 1px solid var(--color-base-200); }
            .budget-status { display: inline-flex; align-items: center; gap: .5rem; font-size: .75rem; color: var(--budget-teal); }
            .budget-status > span { width: .4rem; height: .4rem; background: currentColor; border-radius: 50%; }
            .budget-notice { border-inline-start: 3px solid var(--color-warning); background: color-mix(in oklab, var(--color-warning) 9%, var(--color-base-100)); padding: .8rem 1rem; border-radius: .5rem; font-size: .8rem; line-height: 1.8; }
            .budget-result { color: #f4fbff; background: var(--budget-ink); padding: 1.5rem; border-radius: 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 1.25rem; min-width: 0; }
            .budget-result .budget-caption { color: #c5dae7; }
            .budget-result-value { font-size: clamp(1.6rem, 2.8vw, 2.5rem); font-weight: 800; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; line-height: 1.6; }
            .budget-result .budget-change { color: #e0f2fe; }
            .budget-result .budget-change[data-direction=up] { color: #83f0d0; }
            .budget-result .budget-change[data-direction=down] { color: #ffb8ba; }
            .budget-metric { min-width: 0; padding: 1.25rem; }
            .budget-metric-value { font-size: clamp(1.15rem, 2vw, 1.65rem); font-weight: 750; line-height: 1.8; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
            .budget-change { font-size: .75rem; font-variant-numeric: tabular-nums; }
            .budget-change[data-direction=up] { color: #08785d; }
            .budget-change[data-direction=down] { color: #be364b; }
            .budget-metric-label { font-size: .8rem; color: color-mix(in oklab, var(--color-base-content) 70%, transparent); }
            .budget-methodology { border-bottom: 1px solid var(--color-base-300); padding: .5rem 0 1rem; }
            .budget-methodology summary { width: fit-content; cursor: pointer; font-size: .75rem; font-weight: 600; }
            .budget-table { width: 100%; font-size: .8rem; }
            .budget-table th { background: var(--color-base-200); color: color-mix(in oklab, var(--color-base-content) 65%, transparent); font-weight: 500; white-space: nowrap; }
            .budget-table :is(th, td) { padding: .85rem 1rem; }
            .budget-table td:not(:first-child) { font-variant-numeric: tabular-nums; }
            .budget-table td { border-bottom: 1px solid var(--color-base-200); }
            .budget-product-scroll { max-height: 34rem; overflow: auto; }
            .budget-product-scroll thead { position: sticky; top: 0; z-index: 1; }
            [id^=monthly-] { scroll-margin-top: 5rem; }
            .budget-share { height: .25rem; border-radius: 1rem; background: var(--color-base-200); overflow: hidden; min-width: 4rem; margin-top: .5rem; }
            .budget-share > span { display: block; height: 100%; background: var(--budget-teal); border-radius: inherit; }
            .budget-expense .budget-share > span { background: #b57a35; }
            .budget-driver-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); align-items: start; }
            @media (min-width: 64rem) { .budget-driver-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (min-width: 80rem) { .budget-driver-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            #monthly-module-costs .budget-panel-heading { padding: 1.25rem 1.25rem .35rem; border-bottom: 0; font-size: .9rem; }
            .budget-module-grid { display: grid; gap: .1rem; padding: .7rem 1.25rem .9rem; }
            .budget-module { min-width: 0; padding: .45rem 0 .55rem; }
            .budget-module-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; }
            .budget-module-ratio { font-size: .85rem; font-weight: 750; font-variant-numeric: tabular-nums; color: var(--color-base-content); }
            .budget-module-name { font-size: .78rem; font-weight: 650; }
            .budget-module-amount { margin-inline-start: .35rem; font-size: .68rem; font-weight: 400; color: color-mix(in oklab, var(--color-base-content) 55%, transparent); }
            .budget-module .budget-share { height: .35rem; margin-top: .35rem; }
            .budget-module[data-module-cost=cogs] .budget-share > span { background: #f59e0b; }
            .budget-module[data-module-cost=payroll] .budget-share > span { background: #7660ff; }
            .budget-module[data-module-cost=services] .budget-share > span { background: #f43f5e; }
            .budget-module-note { margin: .65rem 1.25rem 1.25rem; padding: .7rem .85rem; border-radius: .6rem; background: color-mix(in oklab, #818cf8 12%, var(--color-base-100)); color: color-mix(in oklab, #4f46e5 80%, var(--color-base-content)); font-size: .7rem; line-height: 1.8; }
            .budget-module-note-icon { display: inline-flex; align-items: center; justify-content: center; width: 1rem; height: 1rem; margin-inline-end: .25rem; border: 1px solid currentColor; border-radius: 999px; font-size: .65rem; font-weight: 700; }
            .budget-module-details { margin: 0 1.25rem 1rem; font-size: .7rem; line-height: 1.8; color: color-mix(in oklab, var(--color-base-content) 65%, transparent); }
            .budget-module-details summary { cursor: pointer; width: fit-content; }
            .budget-module-details p { margin-top: .35rem; }
            [data-theme=dark] .budget-module-note { color: #c7c9ff; }
            .budget-empty { padding: 2rem 1.25rem; text-align: center; color: color-mix(in oklab, var(--color-base-content) 60%, transparent); font-size: .8rem; line-height: 2; }
            .budget-forecast-entry { border: 1px solid var(--color-base-300); border-radius: .75rem; margin: 1.25rem; }
            .budget-forecast-entry > summary { padding: 1rem; cursor: pointer; font-weight: 600; font-size: .85rem; }
            .budget-workbench :is(a, button, input, select, summary):focus-visible { outline: 2px solid var(--budget-teal); outline-offset: 4px; }
            [data-theme=dark] .budget-workbench { --budget-teal: #5cd1d5; }
            [data-theme=dark] .budget-change[data-direction=up] { color: #6ee7b7; }
            [data-theme=dark] .budget-change[data-direction=down] { color: #fda4af; }
            @media (max-width: 40rem) { .budget-month-picker { width: 100%; justify-content: space-between; } .budget-result { padding: 1.25rem; } .budget-table :is(th, td) { padding: .75rem; } }
        </style>
    @endpush
</x-app-layout>
