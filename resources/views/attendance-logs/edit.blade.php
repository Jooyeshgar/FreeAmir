<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Attendance Log') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('attendance.attendance-logs.update', $attendanceLog) }}" method="POST">
            @csrf
            @method('PUT')
            {{-- is_manual is always forced to true when editing a log manually --}}
            <input type="hidden" name="is_manual" value="1" />
            <div class="card-body">
                <h2 class="card-title">{{ __('Edit Attendance Log') }}</h2>
                <x-show-message-bags />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Employee: read-only, cannot be changed --}}
                    <div class="md:col-span-2">
                        <label class="label">
                            <span class=" font-medium">{{ __('Employee') }}</span>
                        </label>
                        <div class="input  flex items-center bg-base-200 text-base-content/70 cursor-not-allowed">
                            {{ $attendanceLog->employee->first_name }} {{ $attendanceLog->employee->last_name }}
                        </div>
                        <input type="hidden" name="employee_id" value="{{ $attendanceLog->employee_id }}" />
                    </div>

                    <div>
                        <x-date-picker name="log_date" id="log_date" title="{{ __('Date') }}" :value="old('log_date', convertToJalali($attendanceLog->log_date))" required />
                    </div>

                    <div>{{-- spacer --}}</div>

                    <div>
                        <x-input name="entry_time" id="entry_time" placeholder="08:00" type="text" title="{{ __('Entry Time') }}" :value="old('entry_time', $attendanceLog->entry_time ?? '')" />
                    </div>

                    <div>
                        <x-input name="exit_time" id="exit_time" placeholder="16:00" type="text" title="{{ __('Exit Time') }}" :value="old('exit_time', $attendanceLog->exit_time ?? '')" />
                    </div>

                    <div>
                        <x-input name="worked" id="worked" type="number" min="0" title="{{ __('Worked (minutes)') }}" :value="old('worked', $attendanceLog->worked ?? '')" />
                    </div>

                    <div>
                        <x-input name="delay" id="delay" type="number" min="0" title="{{ __('Delay (minutes)') }}" :value="old('delay', $attendanceLog->delay ?? '')" />
                    </div>

                    <div>
                        <x-input name="early_leave" id="early_leave" type="number" min="0" title="{{ __('Early Leave (minutes)') }}" :value="old('early_leave', $attendanceLog->early_leave ?? '')" />
                    </div>

                    <div>
                        <x-input name="overtime" id="overtime" type="number" min="0" title="{{ __('Overtime (minutes)') }}" :value="old('overtime', $attendanceLog->overtime ?? '')" />
                    </div>

                    <div>
                        <x-input name="mission" id="mission" type="number" min="0" title="{{ __('Mission (minutes)') }}" :value="old('mission', $attendanceLog->mission ?? '')" />
                    </div>

                    <div>
                        <x-input name="paid_leave" id="paid_leave" type="number" min="0" title="{{ __('Paid Leave (minutes)') }}" :value="old('paid_leave', $attendanceLog->paid_leave ?? '')" />
                    </div>

                    <div>
                        <x-input name="unpaid_leave" id="unpaid_leave" type="number" min="0" title="{{ __('Unpaid Leave (minutes)') }}" :value="old('unpaid_leave', $attendanceLog->unpaid_leave ?? '')" />
                    </div>

                    <div>
                        <x-input name="auto_overtime" id="auto_overtime" type="number" min="0" title="{{ __('Auto Overtime (minutes)') }}" :value="old('auto_overtime', $attendanceLog->auto_overtime ?? '')" />
                    </div>

                    <div class="md:col-span-2">
                        <x-textarea name="description" id="description" title="{{ __('Description') }}" :value="old('description', $attendanceLog->description ?? '')" placeholder="{{ __('Optional notes…') }}" />
                    </div>

                </div>

                <div class="card-actions justify-end">
                    <a href="{{ route('attendance.attendance-logs.index') }}" class="btn btn-ghost">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
