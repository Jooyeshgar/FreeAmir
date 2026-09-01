<x-app-layout :title="__('Monthly Income and Expense Workbench')">
    <x-show-message-bags />

    <main class="mx-auto mt-4 w-full max-w-[1430px] space-y-4 px-2 sm:mt-6 sm:space-y-5 sm:px-4 lg:px-6">
        <section class="relative overflow-hidden rounded-2xl border border-primary/15 bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 shadow-sm">
            <div class="relative flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between lg:p-6">
                <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-content shadow-lg shadow-primary/20 sm:h-12 sm:w-12 sm:rounded-2xl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15l3-3 3 2 5-7m0 0h-4m4 0v4" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="break-words text-xl font-bold text-base-content sm:text-2xl">{{ __('Monthly Income and Expense Workbench') }}</h1>
                            <span class="badge badge-primary badge-outline">{{ $selectedMonthLabel }}</span>
                            @if (! $hasDocuments)
                                <span class="inline-flex max-w-full rounded-lg border border-warning px-2 py-1 text-xs leading-5 text-warning">
                                    {{ __('No accounting document exists for calculating actual income and expense.') }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-1.5 max-w-2xl text-sm leading-6 text-base-content/60">
                            {{ __("Manual forecasts take priority. Completed months otherwise use actual values; current and future months inherit the previous month's applied forecast.") }}
                        </p>
                    </div>
                </div>

                <form method="GET" action="{{ route('budgets.index') }}" class="w-full sm:w-auto lg:shrink-0">
                    <label class="form-control">
                        <span class="mb-1 text-xs font-medium text-base-content/55">{{ __('Month') }}</span>
                        <select name="month" class="select select-bordered select-sm w-1/2 bg-base-100/90 sm:min-w-40" onchange="this.form.submit()">
                            @foreach ($months as $monthNumber => $monthName)
                                <option value="{{ $monthNumber }}" @selected($selectedMonth === $monthNumber)>{{ $monthName }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
            </div>

            <div class="relative flex flex-col items-stretch gap-3 border-t border-primary/10 bg-base-100/45 px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-5 lg:px-6">
                <div class="flex min-w-0 flex-wrap items-center gap-2 text-xs text-base-content/55">
                    <span class="badge badge-ghost gap-1"><span class="h-1.5 w-1.5 rounded-full bg-success"></span>{{ trans_choice(':count income lines', $incomeLinesCount, ['count' => localizeNumber($incomeLinesCount)]) }}</span>
                    <span class="badge badge-ghost gap-1"><span class="h-1.5 w-1.5 rounded-full bg-error"></span>{{ trans_choice(':count expense lines', $expenseLinesCount, ['count' => localizeNumber($expenseLinesCount)]) }}</span>
                    @if ($hasDocuments)
                        <span class="badge badge-success badge-outline">{{ trans_choice(':count accounting documents', $documentCount, ['count' => localizeNumber($documentCount)]) }}</span>
                    @endif
                </div>

                @can('budgets.rollover')
                    <form method="POST" action="{{ route('budgets.rollover') }}" class="w-full sm:w-auto"
                        onsubmit="return confirm('{{ __('Copy the previous month forecast into :month. Existing values for this month will be replaced.', ['month' => $selectedMonthLabel]) }}')">
                        @csrf
                        <x-input name="month" value="{{ $selectedMonth }}" hidden />
                        <button type="submit" class="btn btn-ghost btn-sm w-full sm:w-auto" @disabled(! $hasPreviousForecast)
                            title="{{ ! $hasPreviousForecast ? __('No forecast exists for the previous month.') : '' }}">
                            {{ __('Copy Previous Month') }}
                        </button>
                    </form>
                @endcan
            </div>
        </section>

        <section class="space-y-4">
            @if ((auth()->user()->can('budgets.store') && auth()->user()->can('budgets.search-subjects')) || $hasOverlappingForecasts)
                <div role="alert" class="alert alert-warning grid grid-cols-[auto_minmax(0,1fr)] items-start border border-warning/30 bg-warning/10 px-3 text-sm text-amber-900 sm:px-4 dark:text-slate-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.6 2.7 17.2A2 2 0 0 0 4.4 20h15.2a2 2 0 0 0 1.7-2.8L13.7 3.6a2 2 0 0 0-3.4 0Z" />
                    </svg>
                    <span>{{ __('Forecasts for lower-level subjects shown below a manually forecast root are details only and are already included in the root total.') }}</span>
                </div>
            @endif

            @if (auth()->user()->can('budgets.store') && auth()->user()->can('budgets.search-subjects'))
                <article class="card overflow-visible border border-base-300 bg-base-100 shadow-sm">
                    <div class="flex items-center gap-3 border-b border-base-300 px-4 py-4 sm:px-5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                            </svg>
                        </span>
                        <div>
                            <h2 class="font-bold text-base-content">{{ __('New Forecast') }}</h2>
                            <p class="text-xs text-base-content/50">{{ __('Only root subjects and their immediate lower-level subjects can be selected.') }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('budgets.store') }}" class="p-4 sm:p-5">
                        @csrf
                        @method('PUT')
                        <x-input name="month" value="{{ $selectedMonth }}" hidden />

                        <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-[minmax(0,3fr)_minmax(16rem,2fr)_auto]">
                            <fieldset class="form-control min-w-0" x-data="{
                                selectedName: @js($selectedSubject?->name ?? ''),
                                selectedCode: @js($selectedSubject?->code ?? ''),
                                selectedId: @js($selectedSubject?->id),
                            }">
                                <x-subject-select class="w-full" :subjects="$subjects" :url="route('budgets.search-subjects', ['month' => $selectedMonth])"
                                    title="{{ __('Subject') }}" placeholder="{{ __('Select Subject') }}"
                                    @selected="selectedName = $event.detail.name; selectedCode = $event.detail.code; selectedId = $event.detail.id" />
                                <x-input name="subject_id" x-bind:value="selectedId" hidden />
                            </fieldset>

                            <fieldset class="form-control min-w-0">
                                <label for="forecast_amount_display" class="label py-1 text-xs font-medium">
                                    <span class="tooltip hidden text-base-content/50 md:inline-flex lg:hidden"
                                        data-tip="{{ __('Enter income as a positive amount and expense as a negative amount; the sign must match the subject type.') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mb-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 17v-6m0-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        <span class="sr-only">{{ __('Enter income as a positive amount and expense as a negative amount; the sign must match the subject type.') }}</span>
                                    </span>
                                    {{ __('Forecast Amount') }} ({{ $currency }})
                                </label>
                                <span class="mt-0.5 font-normal text-xs text-base-content/50 md:hidden lg:block">{{ __('Enter income as a positive amount and expense as a negative amount; the sign must match the subject type.') }}</span>
                                <div x-data="{ forecastAmount: @js(old('forecast_amount', '')) }">
                                    <x-input name="forecast_amount" x-bind:value="forecastAmount" hidden />
                                    <x-text-input id="forecast_amount_display" required placeholder="{{ localizeNumber('0') }}"
                                        input_class="grow bg-transparent tabular-nums outline-none" x-bind:value="forecastAmount"
                                        x-on:input="forecastAmount = $store.utils.convertToEnglish($event.target.value)"
                                        x-effect="$el.value = forecastAmount ? $store.utils.localizeNumber($store.utils.formatNumber(forecastAmount)) : ''" />
                                </div>
                            </fieldset>

                            <div class="w-full justify-self-start sm:w-auto">
                                <button type="submit" class="btn btn-primary btn-sm h-10 w-full gap-1.5 whitespace-nowrap px-3 sm:w-auto" title="{{ __('Save Forecast Line') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                                    </svg>
                                    <span>{{ __('Save Forecast') }}</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </article>
            @endif

            @foreach ([[$displayBudgetLines, false], ...($hasMoreBudgetLines ? [[$allBudgetLinesByForecast, true]] : [])] as [$tableLines, $isModal])
                @if ($isModal)
                    <dialog id="all-monthly-forecasts-modal" class="modal">
                        <div class="modal-box max-h-[calc(100dvh-1rem)] w-[calc(100%-1rem)] max-w-6xl p-0 sm:max-h-[calc(100dvh-2rem)] sm:w-11/12">
                            <div class="flex items-center justify-between border-b border-base-300 px-4 py-3 sm:px-5 sm:py-4">
                                <div>
                                    <h3 class="text-lg font-bold">{{ __('Forecasts') }}</h3>
                                    <p class="mt-1 text-xs text-base-content/50">{{ $selectedMonthLabel }}
                                        <span class="badge badge-neutral badge-sm">{{ localizeNumber($budgetLines->count()) }}</span>
                                    </p>
                                </div>
                                <form method="dialog"><button class="btn btn-circle btn-ghost btn-sm" aria-label="{{ __('Close') }}">✕</button></form>
                            </div>
                @else
                    <article class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">
                        <div class="flex flex-wrap items-center gap-3 border-b border-base-300 px-4 py-4 sm:px-5">
                            <div class="w-full">
                                <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="font-bold text-base-content">{{ __('Forecasts') }}</h2>
                                        <span class="badge badge-neutral badge-sm">{{ localizeNumber($budgetLines->count()) }}</span>
                                        <span class="badge badge-primary badge-outline badge-sm">{{ trans_choice(':count manual forecasts', $manualForecastsCount, ['count' => localizeNumber($manualForecastsCount)]) }}</span>
                                    </div>
                                    @if ($hasMoreBudgetLines)
                                        <button type="button" class="btn btn-outline btn-primary btn-sm w-full sm:w-auto" onclick="document.getElementById('all-monthly-forecasts-modal').showModal()">{{ __('Show all') }}</button>
                                    @endif
                                </div>
                                <div class="mt-3 grid w-full grid-cols-1 gap-x-4 gap-y-2 text-[11px] text-base-content/55 sm:grid-cols-2 xl:grid-cols-4">
                                    <span class="flex items-start gap-1.5">
                                        <span class="shrink-0 font-semibold text-primary">{{ __('Manual Forecast') }}:</span>
                                        <span>{{ __('Entered by the user.') }}</span>
                                    </span>
                                    <span class="flex items-start gap-1.5">
                                        <span class="shrink-0 font-semibold text-base-content/70">{{ __('System forecast') }}:</span>
                                        <span>{{ __("Calculated automatically from the actual value or the previous month's applied forecast.") }}</span>
                                    </span>
                                    <span class="flex items-start gap-1.5">
                                        <span class="shrink-0 font-semibold text-success">{{ __('Actual') }}:</span>
                                        <span>{{ __('Actual values include transactions on the selected subject and all its descendants in documents dated within this month.') }}</span>
                                    </span>
                                    <span class="flex items-start gap-1.5">
                                        <span class="shrink-0 font-semibold text-warning">{{ __('Variance') }}:</span>
                                        <span>{{ __('Positive variance is favorable; negative variance is unfavorable.') }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                @endif

                <div @class(['max-h-[70vh] overflow-auto' => $isModal, 'overflow-x-auto' => ! $isModal])>
                    <table class="table">
                        <thead @class(['text-xs uppercase text-base-content/55', 'sticky top-0 z-10 bg-base-200' => $isModal, 'bg-base-200/60' => ! $isModal])>
                            <tr>
                                <th>{{ __('Subject') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th class="text-end">{{ __('Applied Forecast') }}</th>
                                <th class="text-end">{{ __('Actual') }}</th>
                                <th class="text-end">{{ __('Variance') }}</th>
                                <th class="w-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            @forelse ($tableLines as $line)
                                <tr class="group hover:bg-base-200/40">
                                    <td>
                                        <div @class(['min-w-56', 'ps-6' => $line['isChild']])>
                                            <div class="flex items-center gap-1.5 font-medium text-base-content">
                                                @if ($line['isChild'])<span class="text-base-content/35">↳</span>@endif
                                                <span>{{ $line['subject']->name }}</span>
                                                @if ($line['isRemainder'])
                                                    <span class="badge badge-ghost badge-xs">{{ __('Remainder excluding lower-level forecasts') }}</span>
                                                @elseif (! $line['includedInSummary'])
                                                    <span class="badge badge-info badge-outline badge-xs">{{ __('Included in parent total') }}</span>
                                                @endif
                                            </div>
                                            <div class="mt-0.5 font-mono text-[11px] text-base-content/45">{{ formatCode($line['subject']->code) }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $line['type'] === 'income' ? 'badge-success' : 'badge-error' }} badge-outline badge-sm">
                                            {{ $line['type'] === 'income' ? __('Income') : __('Expense') }}
                                        </span>
                                    </td>
                                    <td class="text-end tabular-nums">
                                        <div class="flex flex-wrap items-center justify-end gap-1.5 font-semibold">
                                            <span>{{ formatNumber($line['forecast']) }}</span>
                                            <span @class(['badge badge-xs', 'badge-primary' => $line['source'] === 'manual', 'badge-ghost' => $line['source'] === 'system'])>
                                                {{ $line['source'] === 'manual' ? __('Manual Forecast') : __('System forecast') }}
                                            </span>
                                        </div>
                                        @if ($line['source'] === 'manual')
                                            <div class="mt-1 text-[11px] text-base-content/50">{{ __('System forecast') }}: {{ formatNumber($line['systemForecast']) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end tabular-nums">
                                        @if (is_null($line['actual']))
                                            <span class="badge badge-warning badge-outline badge-sm">{{ __('No document') }}</span>
                                        @else
                                            {{ formatNumber($line['actual']) }}
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if (is_null($line['variance']))
                                            <span class="text-base-content/35">—</span>
                                        @else
                                            <div class="font-semibold tabular-nums {{ $line['variance'] >= 0 ? 'text-success' : 'text-error' }}">{{ formatNumber($line['variance']) }}</div>
                                            <div class="text-[11px] text-base-content/45">{{ localizeNumber(number_format($line['variancePercent'], 1)) }}%</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($line['budget'])
                                            @can('budgets.destroy')
                                                <form method="POST" action="{{ route('budgets.destroy', $line['budget']) }}" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-square btn-ghost btn-xs text-base-content/35 hover:text-error" title="{{ __('Delete') }}">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M9 7l1-3h4l1 3m-9 0 1 14h10l1-14" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-12 text-center text-base-content/50">{{ __('No temporary income or expense subjects are available.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($isModal)
                            <div class="modal-action m-0 border-t border-base-300 px-5 py-3">
                                <form method="dialog"><button class="btn btn-sm">{{ __('Close') }}</button></form>
                            </div>
                        </div>
                        <form method="dialog" class="modal-backdrop"><button aria-label="{{ __('Close') }}"></button></form>
                    </dialog>
                @else
                    </article>
                @endif
            @endforeach
        </section>

        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-metric-card :card="['title' => __('Forecast Income'), 'value' => $forecastIncome, 'suffix' => $currency, 'detail' => trans_choice(':count income lines', $incomeLinesCount, ['count' => localizeNumber($incomeLinesCount)]), 'tone' => 'primary', 'icon' => 'income']" />
            <x-metric-card :class="$hasDocuments ? '' : 'opacity-50 saturate-50'" :card="['title' => __('Actual Income'), 'value' => $actualIncome ?? '—', 'suffix' => $hasDocuments ? $currency : null, 'detail' => $hasDocuments ? __('Income achievement').': '.localizeNumber(number_format($incomeAchievement, 0)).'%' : __('No accounting document exists for calculating actual income and expense.'), 'tone' => $hasDocuments && $incomeVariance < 0 ? 'warning' : 'success', 'icon' => 'income']" />
            <x-metric-card :card="['title' => __('Forecast Expense'), 'value' => $forecastExpense, 'suffix' => $currency, 'detail' => trans_choice(':count expense lines', $expenseLinesCount, ['count' => localizeNumber($expenseLinesCount)]), 'tone' => 'primary', 'icon' => 'cost']" />
            <x-metric-card :class="$hasDocuments ? '' : 'opacity-50 saturate-50'" :card="['title' => __('Actual Expense'), 'value' => $actualExpense ?? '—', 'suffix' => $hasDocuments ? $currency : null, 'detail' => $hasDocuments ? __('Expense utilization').': '.localizeNumber(number_format($expenseUtilization, 0)).'%' : __('No accounting document exists for calculating actual income and expense.'), 'tone' => $hasDocuments && $expenseVariance >= 0 ? 'success' : 'error', 'icon' => 'cost']" />
        </section>

        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                [__('Forecast Income Breakdown'), __('Forecast values for the five largest items.'), 'monthlyIncomeForecastItemsChart', $incomeItemDatasets[0] ?? null],
                [__('Actual Income Breakdown'), __('Actual values for the five largest items.'), 'monthlyIncomeActualItemsChart', $incomeItemDatasets[1] ?? null],
                [__('Forecast Cost Breakdown'), __('Forecast values for the five largest items.'), 'monthlyExpenseForecastItemsChart', $expenseItemDatasets[0] ?? null],
                [__('Actual Cost Breakdown'), __('Actual values for the five largest items.'), 'monthlyExpenseActualItemsChart', $expenseItemDatasets[1] ?? null],
            ] as [$title, $description, $chartId, $dataset])
                <article class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body p-4 sm:p-5">
                        <div>
                            <h2 class="card-title text-base">{{ $title }}</h2>
                            <p class="text-xs text-base-content/50">{{ $description }}</p>
                        </div>
                        <div class="mt-2">
                            <x-charts.item-comparison-pie :chart-id="$chartId" height-class="h-56 sm:h-64" :datasets="$dataset ? [$dataset] : []" />
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <article class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body p-4 sm:p-5">
                <div>
                    <h2 class="card-title text-base">{{ __('Actual vs Forecast') }}</h2>
                    <p class="text-xs text-base-content/50">{{ $hasDocuments ? __('Calculated from accounting documents for :month.', ['month' => $selectedMonthLabel]) : __('No accounting document exists for calculating actual income and expense.') }}</p>
                </div>
                <div class="mt-2 overflow-x-auto"><div class="min-w-[34rem] sm:min-w-0"><x-charts.monthly-budget-comparison chart-id="monthlyBudgetVarianceChart" heightClass="h-64 sm:h-72" :datasets="$comparisonDatasets" /></div></div>
            </div>
        </article>
    </main>

    @push('footer')
        <style>
            @media (max-width: 63.999rem) {
                .monthly-budget-table thead {
                    display: none;
                }

                .monthly-budget-table,
                .monthly-budget-table tbody {
                    display: block;
                    width: 100%;
                }

                .monthly-budget-table tbody {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: .75rem;
                    padding: .75rem;
                }

                .monthly-budget-table tbody tr {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: .75rem 1rem;
                    min-width: 0;
                    padding: 1rem;
                    border: 1px solid var(--color-base-300);
                    border-radius: .75rem;
                    background: var(--color-base-100);
                }

                .monthly-budget-table tbody td {
                    display: flex;
                    min-width: 0;
                    align-items: center;
                    justify-content: space-between;
                    gap: .75rem;
                    padding: 0;
                    text-align: end;
                    white-space: normal;
                }

                .monthly-budget-table tbody td::before {
                    content: attr(data-label);
                    flex: none;
                    color: color-mix(in oklab, var(--color-base-content) 55%, transparent);
                    font-size: .7rem;
                    font-weight: 600;
                }

                .monthly-budget-table tbody td:first-child {
                    display: block;
                    grid-column: 1 / -1;
                    padding-bottom: .75rem;
                    border-bottom: 1px solid var(--color-base-200);
                    text-align: start;
                }

                .monthly-budget-table tbody td:first-child::before,
                .monthly-budget-table tbody td.monthly-budget-row-action::before {
                    display: none;
                }

                .monthly-budget-table tbody td.monthly-budget-row-action {
                    grid-column: 1 / -1;
                    justify-content: end;
                }

                .monthly-budget-table tbody tr.monthly-budget-empty-row {
                    display: block;
                    grid-column: 1 / -1;
                }

                .monthly-budget-table tbody tr.monthly-budget-empty-row td {
                    display: block;
                    padding: 2rem 1rem;
                    border: 0;
                    text-align: center;
                }
            }

            @media (max-width: 39.999rem) {
                .monthly-budget-table tbody {
                    grid-template-columns: minmax(0, 1fr);
                    padding: .5rem;
                }

                .monthly-budget-table tbody tr {
                    grid-template-columns: minmax(0, 1fr);
                    padding: .875rem;
                }

                .monthly-budget-table tbody td:first-child,
                .monthly-budget-table tbody td.monthly-budget-row-action {
                    grid-column: auto;
                }
            }
        </style>
    @endpush
</x-app-layout>
