<x-app-layout :title="__('Monthly Income and Expense Workbench') . ' · ' . $selectedMonthLabel . ' ' . localizeNumber(config('active-company-fiscal-year'))">
    <x-show-message-bags />

    @php
        $previousMonthLabel = $months[$selectedMonth - 1] ?? null;
        $fiscalYearLabel = localizeNumber(config('active-company-fiscal-year'));
        $periodStatus = $profitability['isFuture']
            ? __('Upcoming month')
            : ($profitability['isComplete'] ? __('Completed month') : __('Month to date'));
        $expensePalette = ['var(--color-primary)', 'var(--color-info)', 'var(--color-secondary)', 'var(--color-warning)', 'var(--color-base-300)'];
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
        $expenseDonutGradient = $expenseDonutStops ? 'conic-gradient(' . implode(', ', $expenseDonutStops) . ')' : 'var(--color-base-300)';
        $expenseDonutAmount = abs((float) ($actualExpense ?? 0));
        $expenseDonutAmountParts = formatCompactCurrencyParts($expenseDonutAmount, $currency);
        $expenseDonutCenter = $hasExpenseComposition
            ? $expenseDonutAmountParts['value']
            : '—';
    @endphp

    <div dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}" class="mx-auto w-full max-w-7xl space-y-6 px-3 py-4 text-sm leading-relaxed text-base-content sm:px-4 print:space-y-4 print:p-0">
        <header class="flex items-center justify-between gap-4 max-lg:flex-col max-lg:items-start">
            <div class="min-w-0">
                <div class="mb-1 flex flex-wrap items-center gap-2 text-xs text-base-content/60 [&_a]:link [&_a]:link-primary">
                    @can('reports.cost-income')<a
                    href="{{ route('reports.cost-income') }}">{{ __('Annual income and expense report') }}</a>@else<span>{{ __('Reports') }}</span>@endcan
                    <span aria-hidden="true">/</span><span>{{ __('Actual Income') }} · {{ __('Actual Expense') }}</span>
                    <x-user-guide-link source="reports/budgets/budgets.md" class="!h-5 !w-5 !min-h-5 !p-0" />
                </div>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ __('Monthly Financial Performance') }} {{ $selectedMonthLabel }}
                    {{ $fiscalYearLabel }}
                </h1>
                <p class="mt-1 text-xs text-base-content/60 sm:text-sm">{{ __('A unified view of business income, expenses, and profitability.') }} ·
                    {{ $periodStatus }}
                </p>
            </div>
            <div class="flex items-center gap-2 print:hidden max-lg:w-full max-lg:justify-between">
                <form method="GET" action="{{ route('budgets.index') }}" class="join border border-base-300 bg-base-100 shadow-sm max-lg:flex-1">
                    @if ($selectedMonth < 12)
                        <a class="btn btn-ghost btn-sm join-item text-base-content/60 hover:text-primary" href="{{ route('budgets.index', ['month' => $selectedMonth + 1]) }}"
                            aria-label="{{ __('Next month') }}" title="{{ __('Next month') }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    @endif
                    <label class="sr-only" for="budget-month">{{ __('Month') }} · {{ $fiscalYearLabel }}</label>
                    <select id="budget-month" name="month" onchange="this.form.submit()" class="select select-sm join-item min-w-32 border-0 bg-transparent text-center font-semibold">
                        @foreach ($months as $monthNumber => $monthName)
                            <option value="{{ $monthNumber }}" @selected($selectedMonth === $monthNumber)>{{ $monthName }}
                            </option>
                        @endforeach
                    </select>
                    @if ($selectedMonth > 1)
                        <a class="btn btn-ghost btn-sm join-item text-base-content/60 hover:text-primary" href="{{ route('budgets.index', ['month' => $selectedMonth - 1]) }}"
                            aria-label="{{ __('Previous month') }}" title="{{ __('Previous month') }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <path d="m15 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    @endif
                    <noscript><button class="btn btn-ghost btn-sm join-item" type="submit">{{ __('View') }}</button></noscript>
                </form>
                <button class="btn btn-outline btn-sm gap-2" type="button" onclick="window.print()">
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
            <div class="alert alert-warning mb-3 rounded-box text-sm" role="status">
                {{ __('No accounting document exists for calculating actual income and expense.') }}
            </div>
        @endif

        <section id="monthly-overview" aria-label="{{ __('Monthly overview') }}">
            <span class="sr-only">{{ __('Monthly overview') }}</span>
            @include('monthly-budgets.partials.overview')
        </section>

        <section id="monthly-analysis" class="scroll-mt-4 print:break-inside-avoid" aria-labelledby="monthly-analysis-title">
            <div class="flex items-end justify-between gap-4 mb-3 [&_h2]:text-base [&_h2]:lg:text-lg [&_h2]:leading-normal
                [&_h2]:font-bold [&_p]:mt-1 [&_p]:text-base-content/60 [&_p]:text-xs">
                <div>
                    <h2 id="monthly-analysis-title">{{ __('Monthly financial picture') }}</h2>
                    <p>{{ __('Income compared with expenses and the leading expense accounts.') }}</p>
                </div>
                <span class="inline-flex items-center gap-2 text-base-content/60 text-xs whitespace-nowrap [&_i]:w-1.5
                    [&_i]:h-1.5 [&_i]:rounded-full [&_i]:bg-success"><i
                        aria-hidden="true"></i>{{ trans_choice(':count accounting documents', $documentCount, ['count' => localizeNumber($documentCount)]) }}</span>
            </div>
            @php
                $flowScale = max(abs((float) ($actualIncome ?? 0)), abs((float) ($actualExpense ?? 0)), 1);
                $incomeWidth = $actualIncome === null ? 0 : min(100, abs($actualIncome) / $flowScale * 100);
                $expenseWidth = $actualExpense === null ? 0 : min(100, abs($actualExpense) / $flowScale * 100);
                $netWidth = $profitability['accountingNet'] === null ? 0 : min(100, abs($profitability['accountingNet']) / $flowScale * 100);
                $marginValue = $profitability['accountingMargin'];
            @endphp
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <article class="card min-w-0 overflow-hidden border border-base-300 bg-base-100/90 shadow-sm
                    print:shadow-none print:break-inside-avoid">
                    <div class="flex items-center justify-between gap-2 px-5 py-4 border-b border-b-base-300
                        [&_h3]:text-sm [&_h3]:font-bold [&_p]:mt-0.5 [&_p]:text-base-content/60 [&_p]:text-xs">
                        <div>
                            <h3>{{ __('Income to net profit') }}</h3>
                            <p>{{ __('All amounts in :currency · rounded for display', ['currency' => $currency]) }}</p>
                        </div>
                        @if (!is_null($profitability['accountingNet']))
                            <span
                                class="px-2 py-1 rounded-full text-xs font-bold {{ $profitability['accountingNet'] >= 0 ? 'text-success bg-success/10' : 'text-error bg-error/10' }}">{{ $profitability['accountingNet'] >= 0 ? __('Profit') : __('Loss') }}</span>
                        @endif
                    </div>
                    <div class="pt-5 px-5 pb-4">
                        <div class="flex items-center justify-between gap-2 mb-4.5 text-base-content/60 text-xs
                            [&_strong]:text-base-content [&_strong]:text-lg [&_strong]:tabular-nums [&_small]:text-base-content/60
                            [&_small]:text-xs [&_small]:font-normal">
                            <span>{{ __('From every 100 units of income', ['currency' => $currency]) }}</span><strong>{{ is_null($marginValue) ? '—' : localizeNumber(number_format($marginValue, 1)) . '%' }}
                                <small>{{ __('net margin') }}</small></strong>
                        </div>
                        @foreach ([['income', __('Actual Income'), $actualIncome, $incomeWidth], ['expense', __('Actual Expense'), $actualExpense, $expenseWidth], ['net', __('Accounting Net Profit or Loss'), $profitability['accountingNet'], $netWidth]] as [$key, $label, $value, $width])
                            <div class="grid grid-cols-3 items-center gap-2 my-4 text-xs [&_strong]:text-left [&_strong]:wrap-anywhere [&_strong]:tabular-nums">
                                <span>{{ $label }}</span>
                                <progress @class([
                                    'progress h-3 w-full',
                                    'progress-primary' => $key === 'income',
                                    'progress-success' => $key === 'net' && ($value === null || $value >= 0),
                                    'progress-error' => $key === 'net' && $value !== null && $value < 0,
                                ]) value="{{ $width }}" max="100" aria-label="{{ $label }}"></progress>
                                <strong dir="ltr" class="[unicode-bidi:isolate]">{{ is_null($value) ? '—' : formatCompactCurrency($key === 'expense' ? abs($value) : $value, $currency) }}</strong>
                            </div>
                        @endforeach
                        @if (!$profitability['comparisonUnavailable'])
                            <div class="flex items-center flex-wrap gap-2 mt-5 pt-3 border-t border-t-base-300
                                border-dashed text-base-content/60 text-xs [&>span]:inline-flex [&>span]:items-center
                                [&>span]:gap-1">
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
                <article class="card min-w-0 overflow-hidden border border-base-300 bg-base-100/90 shadow-sm
                    print:shadow-none print:break-inside-avoid">
                    <div class="flex items-center justify-between gap-2 px-5 py-4 border-b border-b-base-300
                        [&_h3]:text-sm [&_h3]:font-bold [&_p]:mt-0.5 [&_p]:text-base-content/60 [&_p]:text-xs">
                        <div>
                            <h3>{{ __('Expenses at a glance') }}</h3>
                            <p>{{ __('Share of actual expenses') }}</p>
                        </div>
                        <a class="text-primary! text-xs font-semibold" href="#monthly-drivers">{{ __('Details') }} <span
                                aria-hidden="true">←</span></a>
                    </div>
                    <div class="flex flex-col items-center gap-5 p-4 sm:flex-row">
                        <div class="grid shrink-0 place-items-center w-32 h-32
                            rounded-full relative after:content-[''] after:absolute after:inset-4
                            after:rounded-full after:bg-base-100" role="img" aria-label="{{ __('Expense composition') }}"
                            style="background-image: {{ $expenseDonutGradient }}">
                            <div class="z-[1] text-center [&_strong]:block [&_strong]:text-xl [&_span]:text-base-content/60 [&_span]:text-xs">
                                <strong>{{ $expenseDonutCenter }}</strong><span>{{ $expenseDonutAmountParts['unit'] }}</span>
                            </div>
                        </div>
                        <div class="min-w-0 w-full flex-1">
                            @foreach ($expenseDonutItems as $index => $item)
                                <div class="flex items-center gap-2 my-2 text-xs [&_i]:shrink-0 [&_i]:w-2 [&_i]:h-2
                                    [&_i]:rounded-sm [&_a]:min-w-0 [&_a]:overflow-hidden [&_a]:text-base-content/60 [&_a]:text-ellipsis
                                    [&_a]:whitespace-nowrap [&_span]:min-w-0 [&_span]:overflow-hidden [&_span]:text-base-content/60
                                    [&_span]:text-ellipsis [&_span]:whitespace-nowrap [&_a:hover]:text-primary [&_a:hover]:underline
                                    [&_strong]:ms-auto [&_strong]:text-xs">
                                    <i class="{{ ['bg-primary', 'bg-info', 'bg-secondary', 'bg-warning'][$index] }}"></i>
                                    <a
                                        href="{{ route('transactions.index', ['subject_id' => $item['subject']->id]) }}">{{ $item['subject']->name }}</a>
                                    <strong>{{ is_null($item['share']) ? '—' : localizeNumber(number_format($item['share'], 1)) . '%' }}</strong>
                                </div>
                            @endforeach
                            <div class="flex items-center gap-2 my-2 text-xs [&_i]:shrink-0 [&_i]:w-2 [&_i]:h-2
                                [&_i]:rounded-sm [&_a]:min-w-0 [&_a]:overflow-hidden [&_a]:text-base-content/60 [&_a]:text-ellipsis
                                [&_a]:whitespace-nowrap [&_span]:min-w-0 [&_span]:overflow-hidden [&_span]:text-base-content/60
                                [&_span]:text-ellipsis [&_span]:whitespace-nowrap [&_a:hover]:text-primary [&_a:hover]:underline
                                [&_strong]:ms-auto [&_strong]:text-xs">
                                <i class="bg-base-300"></i>
                                <span>{{ __('Other expenses') }}</span>
                                <strong>{{ $hasExpenseComposition ? localizeNumber(number_format($otherExpenseShare, 1)) . '%' : '—' }}</strong>
                            </div>
                            @if ($expenseDonutItems->isEmpty())
                                <p class="text-base-content/60 text-xs">
                                    {{ __('No expense accounts are available for this period.') }}
                                </p>
                            @endif
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section id="monthly-drivers" class="scroll-mt-4 print:break-inside-avoid" aria-labelledby="monthly-drivers-title">
            <div class="flex items-end justify-between gap-4 mb-3 [&_h2]:text-base [&_h2]:lg:text-lg [&_h2]:leading-normal
                [&_h2]:font-bold [&_p]:mt-1 [&_p]:text-base-content/60 [&_p]:text-xs">
                <div>
                    <h2 id="monthly-drivers-title">{{ __('Income and expense drivers') }}</h2>
                    <p>{{ __('Top five second-level accounts (Moein), ranked by actual balance including all descendants.') }}
                    </p>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach (['income' => __('Income'), 'expense' => __('Expense')] as $type => $title)
                    @include('monthly-budgets.partials.drivers')
                @endforeach
            </div>
            @include('monthly-budgets.partials.module-costs')
        </section>

        @include('monthly-budgets.partials.products')
        @include('monthly-budgets.partials.forecasts')

        <details class="mt-5 border border-base-300 rounded-xl bg-base-100 print:shadow-none print:break-inside-avoid
            [&_summary]:px-4 [&_summary]:py-3.5 [&_summary]:list-none [&_summary]:cursor-pointer
            [&_summary]:text-base-content/60 [&_summary]:text-xs [&_summary]:font-semibold
            [&_summary]:after:content-['+'] [&_summary]:after:float-end [&_summary]:after:text-base
            [&[open]_summary]:after:content-['−']">
            <summary>{{ __('Calculation basis and report guide') }}</summary>
            <div class="grid grid-cols-2 gap-x-5 gap-y-3 px-4 pb-4 text-base-content/60 text-xs
                leading-relaxed max-md:grid-cols-1 [&_strong]:text-base-content">
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
