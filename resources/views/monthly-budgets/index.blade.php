<x-app-layout :title="__('Monthly Forecast Income and Expense')">
    <x-show-message-bags />

    @php
        $currency = config('amir.currency') ?? __('Rial');
        $incomeLinesCount = $budgetLines->where('type', 'income')->count();
        $expenseLinesCount = $budgetLines->where('type', 'expense')->count();
        $previousMonth = $selectedMonth > 1 ? $selectedMonth - 1 : null;
        $nextMonth = $selectedMonth < 12 ? $selectedMonth + 1 : null;
        $incomeAchievement = $forecastIncome > 0 ? max(0, ($actualIncome / $forecastIncome) * 100) : 0;
        $incomeProgress = min(100, $incomeAchievement);
        $expenseUtilization = $expensesCalculated && $forecastExpense > 0 ? max(0, ($actualExpense / $forecastExpense) * 100): 0;
        $expenseProgress = min(100, $expenseUtilization);

        $comparisonForecast = [__('Income') => $forecastIncome];
        $comparisonActual = [__('Income') => $actualIncome];

        if ($expensesCalculated) {
            $comparisonForecast[__('Expense')] = $forecastExpense;
            $comparisonActual[__('Expense')] = $actualExpense;
        }
    @endphp

    <main class="mt-6 space-y-5">
        <section class="relative overflow-hidden rounded-2xl border border-primary/15 bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 shadow-sm">
            <div class="pointer-events-none absolute -end-16 -top-20 h-52 w-52 rounded-full bg-primary/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-24 -start-12 h-48 w-48 rounded-full bg-secondary/10 blur-3xl"></div>

            <div class="relative flex flex-col gap-5 p-5 lg:flex-row lg:items-center lg:justify-between lg:p-6">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-content shadow-lg shadow-primary/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3-3 3 2 5-7m0 0h-4m4 0v4" />
                        </svg>
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-base-content sm:text-2xl">
                                {{ __('Monthly Forecast Income and Expense') }}
                            </h1>
                            <span class="badge badge-primary badge-outline">{{ $selectedMonthLabel }}</span>
                        </div>
                        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-base-content/60">
                            {{ __('Plan, calculate, and review subject-level forecasts for :month.', ['month' => $selectedMonthLabel]) }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    <form method="GET" action="{{ route('budgets.index') }}">
                        <label class="form-control">
                            <span class="mb-1 text-xs font-medium text-base-content/55">{{ __('Month') }}</span>
                            <select name="month" class="select select-bordered select-sm min-w-40 bg-base-100/90" onchange="this.form.submit()">
                                @foreach ($months as $monthNumber => $monthName)
                                    <option value="{{ $monthNumber }}" @selected($selectedMonth === $monthNumber)>
                                        {{ $monthName }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </form>
                </div>
            </div>

            <div class="relative flex flex-wrap items-center justify-between gap-3 border-t border-primary/10 bg-base-100/45 px-5 py-3 backdrop-blur-sm lg:px-6">
                <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/55">
                    <span class="badge badge-ghost gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                        {{ trans_choice(':count income lines', $incomeLinesCount, ['count' => localizeNumber($incomeLinesCount)]) }}
                    </span>
                    <span class="badge badge-ghost gap-1">
                        <span class="h-1.5 w-1.5 rounded-full bg-error"></span>
                        {{ trans_choice(':count expense lines', $expenseLinesCount, ['count' => localizeNumber($expenseLinesCount)]) }}
                    </span>
                    @if ($expensesCalculated)
                        <span class="badge badge-success badge-outline gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                            </svg>
                            {{ __('Expenses calculated') }}
                        </span>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @can('budgets.rollover')
                        <form method="POST" action="{{ route('budgets.rollover') }}">
                            @csrf
                            <input type="hidden" name="month" value="{{ $selectedMonth }}">
                            <button type="submit" class="btn btn-ghost btn-sm"
                                @disabled(! $hasPreviousForecast)
                                title="{{ ! $hasPreviousForecast ? __('No forecast exists for the previous month.') : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3M9 15h10a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2Z" />
                                </svg>
                                {{ __('Copy Previous Month') }}
                            </button>
                        </form>
                    @endcan

                    @can('budgets.calculate-expenses')
                        <a href="{{ route('budgets.calculate-expenses', ['month' => $selectedMonth]) }}" class="btn btn-secondary btn-sm gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 12h16M4 19h16M8 3v4m8 3v4m-8 3v4" />
                            </svg>
                            {{ $expensesCalculated ? __('Recalculate Expenses') : __('Calculate Expenses') }}
                        </a>
                    @endcan
                </div>
            </div>
        </section>

        @can('budgets.store')
            <article class="card overflow-visible border border-base-300 bg-base-100 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                            </svg>
                        </span>
                        <div>
                            <h2 class="font-bold text-base-content">{{ __('New Forecast') }}</h2>
                            <p class="text-xs text-base-content/50">
                                {{ __('Selecting an existing subject updates its forecast for this month.') }}
                            </p>
                        </div>
                    </div>
                    <span class="badge badge-ghost">{{ $selectedMonthLabel }}</span>
                </div>

                <form method="POST" action="{{ route('budgets.store') }}" class="p-5" x-data="{ budgetType: @js(old('budget_type', 'income')) }">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="month" value="{{ $selectedMonth }}">
                    <input type="hidden" name="budget_type" x-model="budgetType">

                    <div class="grid grid-cols-1 items-end gap-4 lg:grid-cols-12">
                        <fieldset class="form-control lg:col-span-5" x-data="{
                            selectedName: @js($selectedSubject?->name ?? ''),
                            selectedCode: @js($selectedSubject?->code ?? ''),
                            selectedId: @js(old('subject_id', $selectedSubject?->id)),
                        }">
                            <x-subject-select class="w-full" :subjects="$subjects" :url="route('budgets.search-subjects')" title="{{ __('Subject') }}" placeholder="{{ __('Select Subject') }}"
                                @selected="
                                    selectedName = $event.detail.name;
                                    selectedCode = $event.detail.code;
                                    selectedId = $event.detail.id;
                                " />
                            <x-input name="subject_id" x-bind:value="selectedId" hidden />
                        </fieldset>

                        <fieldset class="form-control lg:col-span-3">
                            <span class="label py-1 text-xs font-medium">{{ __('Forecast Type') }}</span>
                            <div class="grid h-10 grid-cols-2 rounded-lg bg-base-200 p-1">
                                <button type="button" @click="budgetType = 'income'" class="rounded-md text-sm font-medium transition"
                                    :class="budgetType === 'income' ? 'bg-success text-success-content shadow-sm' : 'text-base-content/60 hover:text-base-content'">
                                    {{ __('Income') }}
                                </button>
                                <button type="button" @click="budgetType = 'expense'" class="rounded-md text-sm font-medium transition"
                                    :class="budgetType === 'expense' ? 'bg-error text-error-content shadow-sm' : 'text-base-content/60 hover:text-base-content'">
                                    {{ __('Expense') }}
                                </button>
                            </div>
                        </fieldset>

                        <fieldset class="form-control lg:col-span-3">
                            <label for="forecast_amount" class="label py-1 text-xs font-medium">
                                {{ __('Forecast Amount') }} ({{ $currency }})
                            </label>
                            <div x-data="{ forecastAmount: '{{ old('forecast_amount') }}' }">
                                <input type="hidden" name="forecast_amount" x-bind:value="forecastAmount">
                                <x-text-input id="forecast_amount_display" required placeholder="{{ localizeNumber('0') }}" 
                                    input_class="grow bg-transparent tabular-nums outline-none" x-bind:value="forecastAmount"
                                    x-on:input="forecastAmount = $store.utils.convertToEnglish($event.target.value)"
                                    x-effect="$el.value = forecastAmount ? $store.utils.localizeNumber($store.utils.formatNumber(forecastAmount)) : ''">
                                </x-text-input>
                            </div>
                        </fieldset>

                        <div class="lg:col-span-1">
                            <button type="submit" class="btn btn-primary h-10 w-full px-3" title="{{ __('Save Forecast Line') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                                </svg>
                                <span class="lg:hidden">{{ __('Save Forecast Line') }}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </article>
        @endcan

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-metric-card :card="[
                'title' => __('Forecast Income'),
                'value' => $forecastIncome,
                'suffix' => $currency,
                'detail' => trans_choice(':count income lines', $incomeLinesCount, ['count' => localizeNumber($incomeLinesCount)]),
                'tone' => 'primary',
                'icon' => 'income',
            ]" />
            <x-metric-card :card="[
                'title' => __('Actual Income'),
                'value' => $actualIncome,
                'suffix' => $currency,
                'detail' => __('Income achievement') . ': ' . localizeNumber(number_format($incomeAchievement, 0)) . '%',
                'tone' => $incomeVariance >= 0 ? 'success' : 'warning',
                'icon' => 'income',
            ]" />
            <x-metric-card :card="[
                'title' => __('Forecast Expense'),
                'value' => $forecastExpense,
                'suffix' => $currency,
                'detail' => trans_choice(':count expense lines', $expenseLinesCount, ['count' => localizeNumber($expenseLinesCount)]),
                'tone' => 'primary',
                'icon' => 'cost',
            ]" />
            <x-metric-card :card="[
                'title' => __('Actual Expense'),
                'value' => $expensesCalculated ? $actualExpense : __('Not calculated'),
                'suffix' => $expensesCalculated ? $currency : null,
                'detail' => $expensesCalculated ? __('Expense utilization') . ': ' . localizeNumber(number_format($expenseUtilization, 0)) . '%' : __('Calculate expenses to complete the comparison.'),
                'tone' => $expensesCalculated ? ($expenseVariance >= 0 ? 'success' : 'error') : 'placeholder',
                'icon' => 'cost',
            ]" />
        </section>

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-12">
            <article class="card border border-base-300 bg-base-100 shadow-sm xl:col-span-7">
                <div class="card-body p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="card-title text-base">{{ __('Actual vs Forecast') }}</h2>
                            <p class="text-xs text-base-content/50">
                                {{ __('Calculated from accounting documents for :month.', ['month' => $selectedMonthLabel]) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3 text-xs">
                            <span class="flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-sm bg-indigo-500"></span>{{ __('Forecast') }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span class="h-2.5 w-2.5 rounded-sm bg-teal-500"></span>{{ __('Actual') }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-2">
                        <x-charts.bar-chart chart-id="monthlyBudgetVarianceChart" heightClass="h-72" :show-legend="false" :datasets="[
                            [
                                'label' => __('Forecast'),
                                'data' => $comparisonForecast,
                                'backgroundColor' => '#6366f1cc',
                                'borderColor' => '#6366f1',
                                'borderRadius' => 6,
                            ],
                            [
                                'label' => __('Actual'),
                                'data' => $comparisonActual,
                                'backgroundColor' => '#14b8a6cc',
                                'borderColor' => '#14b8a6',
                                'borderRadius' => 6,
                            ],
                        ]" />
                    </div>
                </div>
            </article>

            <article class="card border border-base-300 bg-base-100 shadow-sm xl:col-span-5">
                <div class="card-body gap-5 p-5">
                    <div>
                        <h2 class="card-title text-base">{{ __('Variance Summary') }}</h2>
                        <p class="text-xs text-base-content/50">{{ __('Positive variance is favorable; expense variance is forecast minus actual.') }}</p>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-xl border border-success/20 bg-success/5 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-xs font-medium text-base-content/55">{{ __('Income') }}</div>
                                    <div class="mt-1 text-lg font-bold tabular-nums">{{ formatNumber($actualIncome) }}</div>
                                    <div class="text-xs text-base-content/45">{{ __('Actual') }} / {{ formatNumber($forecastIncome) }} {{ $currency }}</div>
                                </div>
                                <span class="badge {{ $incomeVariance >= 0 ? 'badge-success' : 'badge-warning' }} badge-outline">
                                    {{ $incomeVariance >= 0 ? '+' : '' }}{{ formatNumber($incomeVariance) }}
                                </span>
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-base-300">
                                <div class="h-full rounded-full bg-success transition-all" style="width: {{ $incomeProgress }}%"></div>
                            </div>
                            <div class="mt-1 text-end text-[11px] text-base-content/45">
                                {{ localizeNumber(number_format($incomeAchievement, 0)) }}%
                            </div>
                        </div>

                        <div class="rounded-xl border border-error/20 bg-error/5 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-xs font-medium text-base-content/55">{{ __('Expense') }}</div>
                                    @if ($expensesCalculated)
                                        <div class="mt-1 text-lg font-bold tabular-nums">{{ formatNumber($actualExpense) }}</div>
                                        <div class="text-xs text-base-content/45">{{ __('Actual') }} / {{ formatNumber($forecastExpense) }} {{ $currency }}</div>
                                    @else
                                        <div class="mt-1 text-sm font-semibold text-base-content/50">{{ __('Not calculated') }}</div>
                                    @endif
                                </div>
                                @if ($expensesCalculated)
                                    <span class="badge {{ $expenseVariance >= 0 ? 'badge-success' : 'badge-error' }} badge-outline">
                                        {{ $expenseVariance >= 0 ? '+' : '' }}{{ formatNumber($expenseVariance) }}
                                    </span>
                                @endif
                            </div>

                            @if ($expensesCalculated)
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-base-300">
                                    <div class="h-full rounded-full {{ $expenseUtilization <= 100 ? 'bg-warning' : 'bg-error' }} transition-all"
                                        style="width: {{ $expenseProgress }}%"></div>
                                </div>
                                <div class="mt-1 text-end text-[11px] text-base-content/45">
                                    {{ localizeNumber(number_format($expenseUtilization, 0)) }}%
                                </div>
                            @else
                                @can('budgets.calculate-expenses')
                                    <a href="{{ route('budgets.calculate-expenses', ['month' => $selectedMonth]) }}"
                                        class="btn btn-outline btn-error btn-xs mt-3">
                                        {{ __('Calculate Expenses') }}
                                    </a>
                                @endcan
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <article class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-300 px-5 py-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-base-content">{{ __('Forecasts') }}</h2>
                        <span class="badge badge-neutral badge-sm">{{ localizeNumber($budgetLines->count()) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-base-content/50">
                        {{ __('Actual values include transactions on the exact selected subject in documents dated within this month.') }}
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead class="bg-base-200/60 text-xs uppercase text-base-content/55">
                        <tr>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th class="text-end">{{ __('Forecast') }}</th>
                            <th class="text-end">{{ __('Actual') }}</th>
                            <th class="text-end">{{ __('Variance') }}</th>
                            <th class="w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-base-200">
                        @forelse ($budgetLines as $line)
                            @php
                                $isIncome = $line['type'] === 'income';
                                $hasVariance = ! is_null($line['variance']);
                                $isFavorable = $hasVariance && $line['variance'] >= 0;
                            @endphp
                            <tr class="group hover:bg-base-200/40">
                                <td>
                                    <div class="flex min-w-56 items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $isIncome ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="{{ $isIncome ? 'm7 14 5-5 5 5M12 9v10' : 'm7 10 5 5 5-5M12 5v10' }}" />
                                            </svg>
                                        </span>
                                        <div>
                                            <div class="font-medium text-base-content">{{ $line['subject']->name }}</div>
                                            <div class="mt-0.5 font-mono text-[11px] text-base-content/45">
                                                {{ formatCode($line['subject']->code) }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $isIncome ? 'badge-success' : 'badge-error' }} badge-outline badge-sm">
                                        {{ $isIncome ? __('Income') : __('Expense') }}
                                    </span>
                                </td>
                                <td class="text-end font-medium tabular-nums">
                                    {{ formatNumber($line['forecast']) }}
                                    <span class="block text-[10px] font-normal text-base-content/40">{{ $currency }}</span>
                                </td>
                                <td class="text-end tabular-nums">
                                    @if (is_null($line['actual']))
                                        <span class="badge badge-ghost badge-sm">{{ __('Not calculated') }}</span>
                                    @else
                                        {{ formatNumber($line['actual']) }}
                                        <span class="block text-[10px] text-base-content/40">{{ $currency }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($hasVariance)
                                        <div class="font-semibold tabular-nums {{ $isFavorable ? 'text-success' : 'text-error' }}">
                                            {{ $line['variance'] >= 0 ? '+' : '' }}{{ formatNumber($line['variance']) }}
                                        </div>
                                        <div class="mt-0.5 text-[11px] text-base-content/45">
                                            {{ localizeNumber(number_format($line['variancePercent'], 1)) }}%
                                        </div>
                                    @else
                                        <span class="text-base-content/35">—</span>
                                    @endif
                                </td>
                                <td>
                                    @can('budgets.destroy')
                                        <form method="POST" action="{{ route('budgets.destroy', $line['budget']) }}"
                                            onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-square btn-ghost btn-xs text-base-content/35 hover:bg-error/10 hover:text-error" title="{{ __('Delete') }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M9 7l1-3h4l1 3m-9 0 1 14h10l1-14" />
                                                </svg>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="flex flex-col items-center justify-center py-14 text-center">
                                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-base-200 text-base-content/35">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m3 6V7m3 10v-3M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z" />
                                            </svg>
                                        </span>
                                        <h3 class="mt-3 font-semibold text-base-content">{{ __('No forecasts have been added for this month.') }}</h3>
                                        <p class="mt-1 text-sm text-base-content/45">{{ __('Add a subject forecast to start monthly variance tracking.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </main>
</x-app-layout>
