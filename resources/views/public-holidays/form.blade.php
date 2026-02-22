    <div class="w-40">
        <x-date-picker name="date" id="date" title="{{ __('Date') }}" :value="old('date', isset($publicHoliday) ? $publicHoliday->date->format('Y-m-d') : '')" required />
    </div>

    <div class="w-96">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $publicHoliday->name ?? '')" placeholder="{{ __('e.g. Nowruz') }}" required />
    </div>
