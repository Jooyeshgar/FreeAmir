    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-4">
            <x-input name="name" id="name" title="{{ __('Shift Name') }}" :value="old('name', $workShift->name ?? '')" placeholder="{{ __('e.g. Morning Shift') }}" required />
        </div>
        <div>
            <x-input name="start_time" id="start_time" type="text" placeholder="08:00" title="{{ __('Start Time') }}" :value="old('start_time', isset($workShift) ? substr($workShift->start_time, 0, 5) : '')" required />
        </div>
        <div>
            <x-input name="end_time" id="end_time" type="text" placeholder="17:00" title="{{ __('End Time') }}" :value="old('end_time', isset($workShift) ? substr($workShift->end_time, 0, 5) : '')" required />
        </div>
        <div>
            <x-input name="float" id="float" type="number" step="0.5" title="{{ __('Float (minutes)') }}" :value="old('float', $workShift->float ?? 0)" placeholder="0" />
        </div>
        <div>
            <x-input name="break" id="break" type="number" title="{{ __('Break (minutes)') }}" :value="old('break', $workShift->break ?? 0)" placeholder="0" />
        </div>
        <div>
            <x-input name="holiday_coefficient" id="holiday_coefficient" type="number" step="0.01" title="{{ __('Holiday Coefficient') }}" :value="old('holiday_coefficient', $workShift->holiday_coefficient ?? 1.4)"
                placeholder="1.40" />
        </div>
        <div>
            <x-input name="overtime_coefficient" id="overtime_coefficient" type="number" step="0.01" title="{{ __('Overtime Coefficient') }}" :value="old('overtime_coefficient', $workShift->overtime_coefficient ?? 1.4)"
                placeholder="1.40" />
        </div>
         <div>
            <x-input name="auto_overtime_coefficient" id="auto_overtime_coefficient" type="number" step="0.01" title="{{ __('Auto Overtime Coefficient') }}" :value="old('auto_overtime_coefficient', $workShift->auto_overtime_coefficient ?? 1.4)"
                placeholder="1.40" />
        </div>
        <div>
            <x-input name="max_auto_overtime" id="max_auto_overtime" type="number" step="1" title="{{ __('Maximum Auto Overtime') }}" :value="old('max_auto_overtime', $workShift->max_auto_overtime ?? 300)"
                placeholder="300" />
        </div>
        <div>
            <x-input name="mission_coefficient" id="mission_coefficient" type="number" step="0.01" title="{{ __('Mission Coefficient') }}" :value="old('mission_coefficient', $workShift->mission_coefficient ?? 1.4)"
                placeholder="1.40" />
        </div>
        <div>
            <x-input name="undertime_coefficient" id="undertime_coefficient" type="number" step="0.01" title="{{ __('Undertime Coefficient') }}" :value="old('undertime_coefficient', $workShift->undertime_coefficient ?? 2.0)"
                placeholder="1.40" />
        </div>
        <div>
            <x-select name="thursday_status" id="thursday_status" :title="__('Thursday Status')" :options="$thursdayStatusOptions" :selected="old('thursday_status', $workShift->thursday_status->value ?? 'half_day')" required x-data="{}"
                x-on:change="$dispatch('thursday-status-changed', { value: $event.target.value })" />
        </div>
        <div x-data="{ show: '{{ old('thursday_status', $workShift->thursday_status->value ?? 'half_day') }}' === 'half_day' }" x-on:thursday-status-changed.window="show = $event.detail.value === 'half_day'" x-show="show">
            <x-input name="thursday_exit_time" id="thursday_exit_time" type="text" placeholder="13:00" title="{{ __('Thursday Exit Time') }}" :value="old('thursday_exit_time', isset($workShift) ? substr($workShift->thursday_exit_time ?? '13:00', 0, 5) : '')" />
        </div>
        <div>
            <x-input name="paid_leave" id="paid_leave" type="number" title="{{ __('Paid Leave (minutes)') }}" :value="old('paid_leave', $workShift->paid_leave ?? 1200)"
                placeholder="1200" />
        </div>
        <div class="flex flex-col gap-3 pt-2">
            <x-checkbox name="is_active" value="1" id="is_active" title="{{ __('Active') }}" :checked="old('is_active', $workShift->is_active ?? true)" />
        </div>
    </div>
