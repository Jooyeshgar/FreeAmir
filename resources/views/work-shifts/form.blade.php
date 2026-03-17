    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div class="md:col-span-2">
            <x-input name="name" id="name" title="{{ __('Shift Name') }}" :value="old('name', $workShift->name ?? '')" placeholder="{{ __('e.g. Morning Shift') }}" required />
        </div>

        <div>
            <x-input name="start_time" id="start_time" type="text" placeholder="08:00" title="{{ __('Start Time') }}" :value="old('start_time', isset($workShift) ? substr($workShift->start_time, 0, 5) : '')" required />
        </div>

        <div>
            <x-input name="end_time" id="end_time" type="text" placeholder="17:00" title="{{ __('End Time') }}" :value="old('end_time', isset($workShift) ? substr($workShift->end_time, 0, 5) : '')" required />
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

        <div>
            <x-select name="thursday_status" id="thursday_status" :title="__('Thursday Status')" :options="$thursdayStatusOptions" :selected="old('thursday_status', $workShift->thursday_status->value ?? 'half_day')" required x-data="{}"
                x-on:change="$dispatch('thursday-status-changed', { value: $event.target.value })" />
        </div>

        <div x-data="{ show: '{{ old('thursday_status', $workShift->thursday_status->value ?? 'half_day') }}' === 'half_day' }" x-on:thursday-status-changed.window="show = $event.detail.value === 'half_day'" x-show="show">
            <x-input name="thursday_exit_time" id="thursday_exit_time" type="text" placeholder="13:00" title="{{ __('Thursday Exit Time') }}" :value="old('thursday_exit_time', isset($workShift) ? substr($workShift->thursday_exit_time ?? '', 0, 5) : '')" />
        </div>

        <div class="flex flex-col gap-3 pt-2">
            <x-checkbox name="is_active" value="1" id="is_active" title="{{ __('Active') }}" :checked="old('is_active', $workShift->is_active ?? true)" />
        </div>

    </div>
