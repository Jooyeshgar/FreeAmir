@php
    $visibleBudgetLines = $budgetLines
        ->filter(fn ($line) =>
            abs((float) $line['forecast']) > 0
            || abs((float) ($line['actual'] ?? 0)) > 0
            || abs((float) ($line['variance'] ?? 0)) > 0
        )
        ->values();
@endphp

<section id="monthly-forecasts" class="budget-section budget-forecast-section" aria-labelledby="monthly-forecasts-title"
    x-data="{ search: '', type: 'all', matches(name, lineType) { const query = $store.utils.convertToEnglish(this.search).trim().toLocaleLowerCase(); return (this.type === 'all' || this.type === lineType) && $store.utils.convertToEnglish(name).toLocaleLowerCase().includes(query); } }">
    <div class="budget-section-heading budget-forecast-heading">
        <div>
            <h2 id="monthly-forecasts-title"><span class="sr-only">{{ __('Forecasts') }}</span>{{ __('Forecasts and performance') }}</h2>
            <p>{{ __('Compare the plan with actual results and manage forecasts for :month.', ['month' => $selectedMonthLabel]) }}</p>
        </div>
        <div class="budget-forecast-actions">
            @can('budgets.rollover')
                <form method="POST" action="{{ route('budgets.rollover') }}" onsubmit="return confirm({{ Illuminate\Support\Js::from(__('Copy the previous month forecast into :month. Existing values for this month will be replaced.', ['month' => $selectedMonthLabel])) }})">
                    @csrf
                    <x-input name="month" value="{{ $selectedMonth }}" hidden />
                    <button type="submit" class="budget-button" @disabled(! $hasPreviousForecast) title="{{ ! $hasPreviousForecast ? __('No forecast exists for the previous month.') : '' }}">{{ __('Copy Previous Month') }}</button>
                </form>
            @endcan
            @if (auth()->user()->can('budgets.store') && auth()->user()->can('budgets.search-subjects'))
                <details class="budget-forecast-entry" @if ($errors->has('subject_id') || $errors->has('forecast_amount')) open @endif>
                    <summary class="budget-button budget-button-primary"><span aria-hidden="true">＋</span>{{ __('New Forecast') }}</summary>
                    @include('monthly-budgets.partials.forecast-form')
                </details>
            @endif
        </div>
    </div>

    <div class="budget-card budget-table-card">
        <div class="budget-forecast-summary">
            @foreach ([['income', __('Income'), $forecastIncome, $actualIncome, $incomeAchievement], ['expense', __('Expense'), $forecastExpense, $actualExpense, $expenseUtilization]] as [$type, $label, $forecast, $actual, $progress])
                <article class="budget-forecast-stat">
                    <span>{{ __('Forecast :type', ['type' => $label]) }}</span>
                    <strong>{{ formatNumber(abs($forecast)) }}</strong>
                    <small>{{ __('Actual') }}: {{ is_null($actual) ? '—' : formatNumber(abs($actual)) }} {{ $currency }}</small>
                    <div class="budget-forecast-progress"><span style="width:{{ min(100, max(0, $progress)) }}%"></span></div>
                    <small>{{ __('Achievement') }}: {{ $hasDocuments && $forecast != 0 ? localizeNumber(number_format($progress, 0)).'%' : '—' }}</small>
                </article>
            @endforeach
            <article class="budget-forecast-chart">
                <div><strong>{{ __('Actual vs Forecast') }}</strong><span>{{ $currency }}</span></div>
                <x-charts.monthly-budget-comparison chart-id="monthlyBudgetVarianceChart" heightClass="h-32" :datasets="$comparisonDatasets" />
            </article>
        </div>

        @if ($manualForecastsCount === 0)
            <p class="budget-forecast-empty-note">{{ __('No manual forecast is set. Completed months use actuals as the system forecast; other months inherit the previous forecast.') }}</p>
        @endif
        <div class="budget-table-toolbar">
            <label class="budget-search-box">
                <span class="sr-only">{{ __('Search forecasts') }}</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4" stroke-linecap="round"/></svg>
                <input id="forecast-search" type="search" x-model="search" placeholder="{{ __('Subject name or code') }}">
            </label>
            <label class="budget-forecast-type"><span>{{ __('Type') }}</span>
                <select id="forecast-type" x-model="type">
                    <option value="all">{{ __('All types') }}</option><option value="income">{{ __('Income') }}</option><option value="expense">{{ __('Expense') }}</option>
                </select>
            </label>
            <span class="budget-result-count">{{ trans_choice(':count manual forecasts', $manualForecastsCount, ['count' => localizeNumber($manualForecastsCount)]) }}</span>
        </div>
        @include('monthly-budgets.partials.forecast-table', ['visibleBudgetLines' => $visibleBudgetLines])
        <p x-cloak x-show="@js($visibleBudgetLines->map(fn ($line) => ['name' => $line['subject']->name.' '.$line['subject']->code, 'type' => $line['type']])->values()).filter(line => matches(line.name, line.type)).length === 0 && (search !== '' || type !== 'all')" class="budget-empty">{{ __('No forecasts match these filters.') }}</p>
        <details class="budget-forecast-method">
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
