@php
    $visibleBudgetLines = $budgetLines
        ->filter(fn ($line) =>
            abs((float) $line['forecast']) > 0
            || abs((float) ($line['actual'] ?? 0)) > 0
            || abs((float) ($line['variance'] ?? 0)) > 0
        )
        ->values();
@endphp

<section id="monthly-forecasts" class="scroll-mt-4" aria-labelledby="monthly-forecasts-title"
    x-data="{ search: '', type: 'all', matches(name, lineType) { const query = $store.utils.convertToEnglish(this.search).trim().toLocaleLowerCase(); return (this.type === 'all' || this.type === lineType) && $store.utils.convertToEnglish(name).toLocaleLowerCase().includes(query); } }">
    <div class="mb-3 flex flex-col justify-between gap-3 md:flex-row [&_h2]:text-base [&_h2]:lg:text-lg [&_h2]:leading-normal [&_h2]:font-bold
        [&_p]:mt-1 [&_p]:text-base-content/60 [&_p]:text-xs items-center max-md:items-start">
        <div>
            <h2 id="monthly-forecasts-title"><span class="sr-only">{{ __('Forecasts') }}</span>{{ __('Forecasts and performance') }}</h2>
            <p>{{ __('Compare the plan with actual results and manage forecasts for :month.', ['month' => $selectedMonthLabel]) }}</p>
        </div>
        <div class="flex items-center gap-2 max-md:w-full max-md:flex-wrap max-md:justify-end">
            @can('budgets.rollover')
                <form method="POST" action="{{ route('budgets.rollover') }}" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Copy the previous month forecast into :month. Existing values for this month will be replaced.', ['month' => $selectedMonthLabel])) }})">
                    @csrf
                    <x-input name="month" value="{{ $selectedMonth }}" hidden />
                    <button type="submit" class="btn btn-outline btn-sm" @disabled($fiscalYearClosed || ! $hasPreviousForecast) title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : (! $hasPreviousForecast ? __('No forecast exists for the previous month.') : '') }}">{{ __('Copy Previous Month') }}</button>
                </form>
            @endcan
            @if (auth()->user()->can('budgets.store') && auth()->user()->can('budgets.search-subjects'))
                <details class="relative [&>summary]:list-none [&>summary::-webkit-details-marker]:hidden" @if ($errors->has('subject_id') || $errors->has('forecast_amount')) open @endif>
                    <summary class="btn btn-primary btn-sm gap-2"><span aria-hidden="true">＋</span>{{ __('New Forecast') }}</summary>
                    <div class="absolute end-0 top-full z-20 mt-2 w-xl max-w-[90vw] rounded-box border border-base-300 bg-base-100 shadow-lg">
                        @include('monthly-budgets.partials.forecast-form')
                    </div>
                </details>
            @endif
        </div>
    </div>

    <div class="card min-w-0 overflow-hidden border border-base-300 bg-base-100/90 shadow-sm">
        <div class="grid grid-cols-3 gap-3 p-4 max-md:grid-cols-2 max-sm:grid-cols-1">
            @foreach ([['income', __('Income'), $forecastIncome, $actualIncome, $incomeAchievement], ['expense', __('Expense'), $forecastExpense, $actualExpense, $expenseUtilization]] as [$type, $label, $forecast, $actual, $progress])
                <article class="px-3.5 py-3 border border-base-300 rounded-box [&>span]:text-base-content/60
                    [&>span]:text-xs [&_strong]:block [&_strong]:[direction:ltr] [&_strong]:text-right [&_strong]:mt-1
                    [&_strong]:text-base [&_strong]:tabular-nums [&_small]:block [&_small]:text-success
                    [&_small]:text-xs">
                    <span>{{ __('Forecast :type', ['type' => $label]) }}</span>
                    <strong>{{ formatNumber(abs($forecast)) }}</strong>
                    <small>{{ __('Actual') }}: {{ is_null($actual) ? '—' : formatNumber(abs($actual)) }} {{ $currency }}</small>
                    <progress class="progress progress-success my-1.5 h-1" value="{{ min(100, max(0, $progress)) }}" max="100" aria-label="{{ __('Achievement') }}"></progress>
                    <small>{{ __('Achievement') }}: {{ $hasDocuments && $forecast != 0 ? localizeNumber(number_format($progress, 0)).'%' : '—' }}</small>
                </article>
            @endforeach
            <article class="min-w-0 px-3.5 py-3 border border-base-300 rounded-box max-md:col-span-full
                max-sm:col-auto [&>div:first-child]:flex [&>div:first-child]:justify-between
                [&>div:first-child]:text-base-content/60 [&>div:first-child]:text-xs
                [&>div:first-child_strong]:text-base-content">
                <div><strong>{{ __('Actual vs Forecast') }}</strong><span>{{ $currency }}</span></div>
                <x-charts.monthly-budget-comparison chart-id="monthlyBudgetVarianceChart" heightClass="h-32" :datasets="$comparisonDatasets" />
            </article>
        </div>

        @if ($manualForecastsCount === 0)
            <p class="px-4 pb-3 text-base-content/60 text-xs">{{ __('No manual forecast is set. Completed months use actuals as the system forecast; other months inherit the previous forecast.') }}</p>
        @endif
        <div class="flex items-center gap-3 px-4 py-3 border-b border-b-base-300 max-md:flex-col max-md:items-stretch">
            <label class="input flex w-full min-w-0 max-w-80 flex-1 items-center gap-2 max-md:max-w-none">
                <span class="sr-only">{{ __('Search forecasts') }}</span>
                <svg class="shrink-0 text-base-content/60" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4" stroke-linecap="round"/></svg>
                <input id="forecast-search" type="search" class="min-w-0 grow" x-model="search" placeholder="{{ __('Subject name or code') }}">
            </label>

            <label class="flex items-center gap-2 text-xs text-base-content/60">
                <span>{{ __('Type') }}</span>
                <select id="forecast-type" class="select select-sm w-auto" x-model="type">
                    <option value="all">{{ __('All') }}</option>
                    <option value="income">{{ __('Income') }}</option>
                    <option value="expense">{{ __('Expense') }}</option>
                </select>
            </label>
            <span class="ms-auto text-base-content/60 text-xs whitespace-nowrap">{{ trans_choice(':count manual forecasts', $manualForecastsCount, ['count' => localizeNumber($manualForecastsCount)]) }}</span>
        </div>
        @include('monthly-budgets.partials.forecast-table', ['visibleBudgetLines' => $visibleBudgetLines])
        <p x-cloak x-show="@js($visibleBudgetLines->map(fn ($line) => ['name' => $line['subject']->name.' '.$line['subject']->code, 'type' => $line['type']])->values()).filter(line => matches(line.name, line.type)).length === 0 && (search !== '' || type !== 'all')" class="px-4 py-8 text-base-content/60 text-center text-xs">{{ __('No forecasts match these filters.') }}</p>
        <details class="px-4 py-3 border-t border-t-base-300 text-base-content/60 [&_summary]:list-none
            [&_summary]:cursor-pointer [&_summary]:text-xs [&_summary]:font-semibold [&_summary]:after:content-['+']
            [&_summary]:after:float-end [&_summary]:after:text-base [&[open]_summary]:after:content-['−'] [&_dl]:grid
            [&_dl]:grid-cols-2 [&_dl]:gap-x-4 [&_dl]:gap-y-3 [&_dl]:mt-3 [&_dl]:text-xs
            [&_dl]:leading-relaxed [&_dl]:max-md:grid-cols-1 [&_dt]:text-base-content [&_dt]:font-bold [&>p]:mt-3
            [&>p]:text-xs">
            <summary>{{ __('Understanding forecasts and variance') }}</summary>
            <dl>
                <div><dt>{{ __('Manual Forecast') }}</dt><dd>{{ __('Entered by the user.') }}</dd></div>
                <div><dt>{{ __('System forecast') }}</dt><dd>{{ __("Calculated automatically from the actual value or the previous month's applied forecast.") }}</dd></div>
                <div><dt>{{ __('Actual') }}</dt><dd>{{ __('Actual values include transactions on the selected subject and all its descendants in documents dated within this month.') }}</dd></div>
                <div><dt>{{ __('Variance') }}</dt><dd>{{ __('Positive variance is favorable; negative variance is unfavorable.') }}</dd></div>
            </dl>
            @if ($hasOverlappingForecasts)
                <p>{{ __('Forecasts for lower-level subjects shown below a manually forecast root are details only and are already included in the root total.') }}</p>
            @endif
        </details>
    </div>
</section>
