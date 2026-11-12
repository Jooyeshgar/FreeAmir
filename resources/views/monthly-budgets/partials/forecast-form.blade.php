<form method="POST" action="{{ route('budgets.store') }}" class="p-4 sm:p-5">
    @csrf
    @method('PUT')
    <x-input name="month" value="{{ $selectedMonth }}" hidden />

    <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-2">
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
                <x-text-input id_input="forecast_amount_display" required placeholder="{{ localizeNumber('0') }}"
                    input_class="grow bg-transparent tabular-nums outline-none" x-bind:value="forecastAmount"
                    x-on:input="forecastAmount = $store.utils.convertToEnglish($event.target.value)"
                    x-effect="$el.value = forecastAmount ? $store.utils.localizeNumber($store.utils.formatNumber(forecastAmount)) : ''" />
            </div>
        </fieldset>

        <div class="w-full justify-self-start sm:w-auto md:col-span-2">
            <button type="submit" class="btn btn-primary btn-sm h-10 w-full cursor-pointer gap-1.5 whitespace-nowrap px-3 sm:w-auto" @disabled($fiscalYearClosed) title="{{ $fiscalYearClosed ? __('This fiscal year is already closed.') : __('Save Forecast Line') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" />
                </svg>
                <span>{{ __('Save Forecast') }}</span>
            </button>
        </div>
    </div>
</form>
