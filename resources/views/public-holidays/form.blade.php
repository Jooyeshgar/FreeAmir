<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
    <x-date-picker name="date" id="date" title="{{ __('Date') }}" :value="old('date', isset($publicHoliday) ? toEnglish(formatDate($publicHoliday->date)) : '')" required />
    <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $publicHoliday->name ?? '')" placeholder="{{ __('e.g. Nowruz') }}" required />
</div>
