<section id="monthly-forecasts" class="budget-panel scroll-mt-4" aria-labelledby="monthly-forecasts-title"
    x-data="{ search: '', type: 'all', matches(name, lineType) { const query = $store.utils.convertToEnglish(this.search).trim().toLocaleLowerCase(); return (this.type === 'all' || this.type === lineType) && $store.utils.convertToEnglish(name).toLocaleLowerCase().includes(query); } }">
    <div class="budget-panel-heading">
        <div>
            <h2 id="monthly-forecasts-title" class="budget-section-title">{{ __('Forecasts') }} <span class="badge badge-ghost badge-sm">{{ localizeNumber($budgetLines->count()) }}</span></h2>
            <p class="budget-caption">{{ __('Compare the plan with actual results and manage forecasts for :month.', ['month' => $selectedMonthLabel]) }}</p>
        </div>
        @can('budgets.rollover')
            <form method="POST" action="{{ route('budgets.rollover') }}" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Copy the previous month forecast into :month. Existing values for this month will be replaced.', ['month' => $selectedMonthLabel])) }})">
                @csrf
                <x-input name="month" value="{{ $selectedMonth }}" hidden />
                <button type="submit" class="btn btn-outline btn-sm" @disabled(! $hasPreviousForecast) title="{{ ! $hasPreviousForecast ? __('No forecast exists for the previous month.') : '' }}">{{ __('Copy Previous Month') }}</button>
            </form>
        @endcan
    </div>

    <div class="budget-forecast-summary p-5">
        <div class="space-y-4">
            <h3 class="font-semibold text-sm">{{ __('Actual vs Forecast') }}</h3>
            @foreach ([['income', __('Income'), $forecastIncome, $actualIncome], ['expense', __('Expense'), $forecastExpense, $actualExpense]] as [$type, $label, $forecast, $actual])
                <div class="rounded-xl border border-base-300 p-4">
                    <strong class="text-sm">{{ $label }}</strong>
                    <dl class="mt-3 grid grid-cols-2 gap-4 text-xs">
                        <div><dt class="text-base-content/60">{{ __('Applied Forecast') }}</dt><dd class="mt-1 font-bold tabular-nums">{{ formatNumber($forecast) }}</dd></div>
                        <div><dt class="text-base-content/60">{{ __('Actual') }}</dt><dd class="mt-1 font-bold tabular-nums">{{ is_null($actual) ? '—' : formatNumber($actual) }}</dd></div>
                    </dl>
                </div>
            @endforeach
        </div>
        <div><x-charts.monthly-budget-comparison chart-id="monthlyBudgetVarianceChart" heightClass="h-64" :datasets="$comparisonDatasets" /></div>
    </div>

    @if (auth()->user()->can('budgets.store') && auth()->user()->can('budgets.search-subjects'))
        <details class="budget-forecast-entry" @if ($errors->has('subject_id') || $errors->has('forecast_amount')) open @endif>
            <summary>{{ __('New Forecast') }} <span class="font-normal text-base-content/60">· {{ __('Select a subject and enter an amount') }}</span></summary>
            @include('monthly-budgets.partials.forecast-form')
        </details>
    @endif

    @if ($manualForecastsCount === 0)
        <p class="mx-5 mb-4 budget-caption">{{ __('No manual forecast is set. Completed months use actuals as the system forecast; other months inherit the previous forecast.') }}</p>
    @endif
    <div class="flex flex-wrap items-end gap-3 border-t border-base-300 px-5 py-4">
        <div class="min-w-48 flex-1">
            <label for="forecast-search" class="mb-1 block text-xs text-base-content/65">{{ __('Search forecasts') }}</label>
            <input id="forecast-search" type="search" x-model="search" class="input input-bordered input-sm w-full" placeholder="{{ __('Subject name or code') }}">
        </div>
        <div>
            <label for="forecast-type" class="mb-1 block text-xs text-base-content/65">{{ __('Type') }}</label>
            <select id="forecast-type" x-model="type" class="select select-bordered select-sm">
                <option value="all">{{ __('All types') }}</option><option value="income">{{ __('Income') }}</option><option value="expense">{{ __('Expense') }}</option>
            </select>
        </div>
        <span class="text-xs text-base-content/60">{{ trans_choice(':count manual forecasts', $manualForecastsCount, ['count' => localizeNumber($manualForecastsCount)]) }}</span>
    </div>
    @include('monthly-budgets.partials.forecast-table')
    <p x-cloak x-show="@js($budgetLines->map(fn ($line) => ['name' => $line['subject']->name.' '.$line['subject']->code, 'type' => $line['type']])->values()).filter(line => matches(line.name, line.type)).length === 0 && (search !== '' || type !== 'all')" class="budget-empty">{{ __('No forecasts match these filters.') }}</p>
    <details class="px-5 py-4 border-t border-base-300">
        <summary class="cursor-pointer text-xs font-semibold">{{ __('Understanding forecasts and variance') }}</summary>
        <dl class="mt-3 grid gap-3 text-xs leading-6 text-base-content/65 md:grid-cols-2">
            <div><dt class="font-semibold text-base-content">{{ __('Manual Forecast') }}</dt><dd>{{ __('Entered by the user.') }}</dd></div>
            <div><dt class="font-semibold text-base-content">{{ __('System forecast') }}</dt><dd>{{ __("Calculated automatically from the actual value or the previous month's applied forecast.") }}</dd></div>
            <div><dt class="font-semibold text-base-content">{{ __('Actual') }}</dt><dd>{{ __('Actual values include transactions on the selected subject and all its descendants in documents dated within this month.') }}</dd></div>
            <div><dt class="font-semibold text-base-content">{{ __('Variance') }}</dt><dd>{{ __('Positive variance is favorable; negative variance is unfavorable.') }}</dd></div>
        </dl>
        @if ($hasOverlappingForecasts)
            <p class="mt-3 text-xs leading-6 text-base-content/65">{{ __('Forecasts for lower-level subjects shown below a manually forecast root are details only and are already included in the root total.') }}</p>
        @endif
    </details>
</section>
