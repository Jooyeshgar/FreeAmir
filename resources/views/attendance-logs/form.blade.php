{{-- Shared form fields for AttendanceLog create / edit --}}

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <div class="md:col-span-2">
        <x-select name="employee_id" id="employee_id" title="{{ __('Employee') }}" :options="$employees->mapWithKeys(fn($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->toArray()" :selected="old('employee_id', $attendanceLog->employee_id ?? '')" required />
    </div>

    <div>
        <x-date-picker name="log_date" id="log_date" title="{{ __('Date') }}" :value="old('log_date', isset($attendanceLog) ? $attendanceLog->log_date?->format('Y-m-d') : '')" required />
    </div>

    <div>{{-- spacer on desktop --}}</div>

    <div>
        <x-input name="entry_time" id="entry_time" type="time" title="{{ __('Entry Time') }}" :value="old('entry_time', $attendanceLog->entry_time ?? '')" />
    </div>

    <div>
        <x-input name="exit_time" id="exit_time" type="time" title="{{ __('Exit Time') }}" :value="old('exit_time', $attendanceLog->exit_time ?? '')" />
    </div>

    <div class="md:col-span-2 flex items-center gap-3 mt-1">
        <label class="label cursor-pointer gap-2">
            <input type="hidden" name="is_manual" value="0" />
            <input type="checkbox" name="is_manual" id="is_manual" value="1" class="checkbox checkbox-warning"
                {{ old('is_manual', $attendanceLog->is_manual ?? false) ? 'checked' : '' }} />
            <span class="label-text">{{ __('Manually Corrected') }}</span>
        </label>
    </div>

    <div class="md:col-span-2">
        <x-textarea name="description" id="description" title="{{ __('Description') }}" :value="old('description', $attendanceLog->description ?? '')" placeholder="{{ __('Optional notes…') }}" />
    </div>

</div>
