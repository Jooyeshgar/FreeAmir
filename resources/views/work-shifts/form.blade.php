    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="md:col-span-2">
            <x-input name="name" id="name" title="{{ __('Shift Name') }}" :value="old('name', $workShift->name ?? '')" placeholder="{{ __('e.g. Morning Shift') }}" required />
        </div>

        <div>
            <x-input name="start_time" id="start_time" type="time" title="{{ __('Start Time') }}" :value="old('start_time', isset($workShift) ? substr($workShift->start_time, 0, 5) : '')" required />
        </div>

        <div>
            <x-input name="end_time" id="end_time" type="time" title="{{ __('End Time') }}" :value="old('end_time', isset($workShift) ? substr($workShift->end_time, 0, 5) : '')" required />
        </div>

        <div>
            <x-input name="float_before" id="float_before" type="number" title="{{ __('Float Before (minutes)') }}" :value="old('float_before', $workShift->float_before ?? 0)" placeholder="0" />
        </div>

        <div>
            <x-input name="float_after" id="float_after" type="number" title="{{ __('Float After (minutes)') }}" :value="old('float_after', $workShift->float_after ?? 0)" placeholder="0" />
        </div>

        <div>
            <x-input name="break" id="break" type="number" title="{{ __('Break (minutes)') }}" :value="old('break', $workShift->break ?? 0)" placeholder="0" />
        </div>

        <div class="flex flex-col gap-3 pt-2">
            <x-checkbox name="crosses_midnight" id="crosses_midnight" title="{{ __('Crosses Midnight') }}" :checked="old('crosses_midnight', $workShift->crosses_midnight ?? false)" />

            <x-checkbox name="is_active" id="is_active" title="{{ __('Active') }}" :checked="old('is_active', $workShift->is_active ?? true)" />
        </div>

    </div>
