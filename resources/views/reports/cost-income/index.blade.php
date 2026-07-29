<x-app-layout :title="__('Cost and Income Dashboard')">
    <x-show-message-bags />

    <main class="mt-6 space-y-6">
        <section class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold text-base-content">{{ __('Cost and Income Dashboard') }}</h1>
            <p class="text-sm text-base-content/60">
                {{ __('Profitability and trade for the current fiscal year') }}
            </p>
        </section>

        @include('reports.cost-income._metrics')

        <article class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h2 class="card-title text-base">{{ __('Monthly Income vs Cost') }}</h2>
                        <p class="text-xs text-base-content/55">{{ __('Select a month on the chart to open its monthly income and expense workbench.') }}</p>
                    </div>

                    @can('budgets.store')
                        <button type="button" class="btn btn-primary btn-sm gap-1.5" onclick="document.getElementById('cost-income-forecast-modal').showModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                            </svg>
                            {{ __('New Forecast') }}
                        </button>
                    @endcan
                </div>

                <div class="mt-3">
                    <x-charts.bar-chart chart-id="costIncomeMonthlyChart" heightClass="h-72" :datasets="[
                        ['label' => __('Actual Income'), 'data' => $monthlyIncome, 'backgroundColor' => '#22c55ecc', 'borderColor' => '#22c55e', 'order' => 2],
                        ['label' => __('Actual Expense'), 'data' => $monthlyCost, 'backgroundColor' => '#ef4444cc', 'borderColor' => '#ef4444', 'order' => 2],
                        ['label' => __('Forecast Income'), 'data' => $forecastIncome, 'type' => 'line', 'borderColor' => '#2563eb', 'backgroundColor' => '#2563eb', 'tension' => 0.4, 'pointRadius' => 3, 'pointHoverRadius' => 5, 'spanGaps' => true, 'order' => 1, 'datalabels' => ['display' => false]],
                        ['label' => __('Forecast Expense'), 'data' => $forecastExpense, 'type' => 'line', 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b', 'tension' => 0.4, 'pointRadius' => 3, 'pointHoverRadius' => 5, 'spanGaps' => true, 'order' => 1, 'datalabels' => ['display' => false]],
                    ]" :links="$monthlyBudgetLinks" />
                </div>

                @if ($monthsWithoutDocuments !== [])
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="badge badge-error badge-outline badge-sm">
                            {{ $monthsWithoutDocumentsLabel }}: {{ __('No accounting document exists for calculating actual income and expense.') }}
                        </span>
                    </div>
                @endif
            </div>
        </article>

        @can('budgets.store')
            <dialog id="cost-income-forecast-modal" class="modal">
                <div class="modal-box relative w-11/12 max-w-3xl overflow-visible p-0">
                    <div class="flex items-center justify-between border-b border-base-300 px-5 py-4">
                        <div>
                            <h3 class="text-lg font-bold">{{ __('New Forecast') }}</h3>
                            <p class="mt-1 text-xs text-base-content/50">{{ __('Apply one forecast amount to all selected months.') }}</p>
                        </div>
                        <form method="dialog">
                            <button class="btn btn-circle btn-ghost btn-sm" aria-label="{{ __('Close') }}">✕</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('budgets.store') }}" class="space-y-5 p-5">
                        @csrf
                        @method('PUT')
                        <x-input name="source" value="cost-income" hidden />

                        <fieldset>
                            <legend class="mb-2 text-sm font-semibold text-base-content">{{ __('Months') }}</legend>
                            <p class="mb-3 text-xs text-base-content/50">{{ __('The current month and following months are selected by default.') }}</p>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                                @foreach ($forecastMonths as $monthNumber => $monthName)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-base-300 bg-base-100 px-3 py-2 transition-colors hover:border-primary/50 hover:bg-primary/5">
                                        <input type="checkbox" name="months[]" value="{{ $monthNumber }}" class="checkbox checkbox-primary checkbox-sm"
                                            @checked(in_array($monthNumber, old('months', $defaultForecastMonths)))>
                                        <span class="text-sm">{{ $monthName }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="grid grid-cols-12 items-end gap-2">
                            <fieldset class="col-span-8 min-w-0" x-data="{
                                selectedName: '',
                                selectedCode: '',
                                selectedId: @js(old('subject_id')),
                            }">
                                <x-subject-select class="w-full" :subjects="$forecastSubjects"
                                    :url="route('budgets.search-subjects', ['month' => $defaultForecastMonths[0], 'scope' => 'all'])"
                                    title="{{ __('Subject') }}" placeholder="{{ __('Select Subject') }}"
                                    @selected="selectedName = $event.detail.name; selectedCode = $event.detail.code; selectedId = $event.detail.id" />
                                <x-input name="subject_id" x-bind:value="selectedId" hidden />
                            </fieldset>

                            <fieldset class="col-span-4 min-w-0">
                                <label for="bulk_forecast_amount_display" class="label block text-xs font-medium">
                                    <span>{{ __('Forecast Amount') }} ({{ $currency }})</span>
                                    <span class="mt-0.5 block font-normal text-base-content/50">{{ __('Enter income as a positive amount and expense as a negative amount.') }}</span>
                                </label>
                                <div x-data="{ forecastAmount: @js(old('forecast_amount', '')) }">
                                    <x-input name="forecast_amount" x-bind:value="forecastAmount" hidden />
                                    <x-text-input id="bulk_forecast_amount_display" required placeholder="{{ localizeNumber('0') }}"
                                        input_class="grow bg-transparent tabular-nums outline-none" x-bind:value="forecastAmount"
                                        x-on:input="forecastAmount = $store.utils.convertToEnglish($event.target.value)"
                                        x-effect="$el.value = forecastAmount ? $store.utils.localizeNumber($store.utils.formatNumber(forecastAmount)) : ''" />
                                </div>
                            </fieldset>
                        </div>

                        <div class="modal-action m-0 border-t border-base-300 pt-4">
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('cost-income-forecast-modal').close()">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="btn btn-primary">{{ __('Save Forecast') }}</button>
                        </div>
                    </form>
                </div>
                <form method="dialog" class="modal-backdrop"><button>{{ __('Close') }}</button></form>
            </dialog>
        @endcan

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @include('reports.cost-income._breakdown')
        </section>

        @include('reports.cost-income._trading')

        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @include('reports.cost-income._top-customers')
        </section>
    </main>

    @if ($errors->any() && old('source') === 'cost-income')
        @push('scripts')
            <script>
                document.getElementById('cost-income-forecast-modal')?.showModal();
            </script>
        @endpush
    @endif
</x-app-layout>
