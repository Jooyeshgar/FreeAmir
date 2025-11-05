<input type="hidden" name="report_for" value="{{ $type }}">

<hr class="{{ $type == 'Journal' ? 'hidden' : '' }}">


<div class="flex flex-wrap gap-2 items-center">
    <div class="shrink-0 w-32 text-sm font-medium text-gray-600">{{ __('Date range :') }}</div>
    <div class="flex gap-2">
        <x-date-picker name="start_date" class="w-40" placeholder="{{ __('Start date') }}"></x-date-picker>
        <x-date-picker name="end_date" class="w-40" placeholder="{{ __('End date') }}"></x-date-picker>
    </div>
</div>
<div class="flex flex-wrap gap-2 items-center mt-2">
    <div class="shrink-0 w-32 text-sm font-medium text-gray-600">{{ __('Document Number:') }}</div>
    <div class="flex gap-2">
        <x-input name="start_document_number" class="w-40" placeholder="{{ __('Document start number') }}"></x-input>
        <x-input name="end_document_number" class="w-40" placeholder="{{ __('Document end number') }}"></x-input>
    </div>
</div>

<hr>
<div class="flex-1">
    <x-input label_class="flex-1 max-w-44" placeholder="{{ __('Search for documents') }}" title="{{ __('Search for documents') }}" name="search"></x-input>
</div>
