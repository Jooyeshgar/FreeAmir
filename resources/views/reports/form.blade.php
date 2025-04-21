<input type="hidden" name="report_for" value="{{ $type }}">

<hr class="{{ $type == 'Journal' ? 'hidden' : '' }}">
<div class="flex items-center">
    <div class="flex-1 gap-4">
        <label class="label cursor-pointer justify-end gap-2" dir="ltr">
            <span class="">{{ __('all book contents') }}</span>
            <input type="radio" name="report_type" value="all" class="radio checked:bg-red-500" checked />
        </label>
    </div>
</div>
<div class="flex items-center">
    <div class="flex-1 gap-4">
        <label class="label cursor-pointer justify-end gap-2" dir="ltr">
            <span class="">{{ __('all book contents in specific date') }}</span>
            <input type="radio" name="report_type" value="specific_date" class="radio checked:bg-red-500" />
        </label>
    </div>

    <div class="flex-1">
        <x-text-input name="specific_date" label_class="flex-1" data-jdp placeholder="{{ __('Your specific date') }}"></x-text-input>
    </div>
</div>
<div class="flex items-center">

    <div class="flex-1 gap-4">
        <label class="label cursor-pointer max-w-60 justify-end gap-2" dir="ltr">
            <span class="">{{ __('Contents between dates') }}</span>
            <input type="radio" name="report_type" value="between_dates" class="radio checked:bg-red-500" />
        </label>
    </div>

    <div class="flex-1 flex gap-2 justify-between">
        <x-text-input name="start_date" label_class="flex-1" data-jdp placeholder="{{ __('Start date') }}"></x-text-input>
        <x-text-input name="end_date" label_class="flex-1" data-jdp placeholder="{{ __('End date') }}"></x-text-input>
    </div>
</div>
<div class="flex items-center">

    <div class="flex-1 gap-4">
        <label class="label cursor-pointer justify-end gap-2" dir="ltr">
            <span class="">{{ __('Contents between document numbers') }}</span>
            <input type="radio" name="report_type" value="between_numbers" class="radio checked:bg-red-500" />
        </label>
    </div>

    <div class="flex-1 flex gap-2 justify-between">
        <x-text-input name="start_document_number" label_class="flex-1" placeholder="{{ __('Document start number') }}"></x-text-input>
        <x-text-input name="end_document_number" label_class="flex-1" placeholder="{{ __('Document end number') }}"></x-text-input>
    </div>
</div>

<hr>
<div class="flex-1">
    <x-text-input label_class="flex-1 max-w-44" placeholder="{{ __('Search for documents') }}" title="{{ __('Search for documents') }}" name="search"></x-text-input>
</div>
