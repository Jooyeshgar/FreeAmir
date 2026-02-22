<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="date" id="date" type="date" title="{{ __('Date') }}"
            :value="old('date', isset($publicHoliday) ? $publicHoliday->date->format('Y-m-d') : '')"
            required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}"
            :value="old('name', $publicHoliday->name ?? '')"
            placeholder="{{ __('e.g. Nowruz') }}" required />
    </div>
</div>
