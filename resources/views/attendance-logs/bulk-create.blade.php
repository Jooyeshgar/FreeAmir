<x-app-layout :title="__('Bulk Attendance Log Creation')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('attendance.attendance-logs.bulk-store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Bulk Attendance Log Creation') }}</h2>
                <x-show-message-bags />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-date-picker name="start_date" id="start_date" title="{{ __('Start Date') }}" :value="old('start_date')" :hint="__('First day of the Jalali month (e.g. 1404/01/01)')" required />
                    </div>
                    <div>
                        <x-input name="duration" id="duration" type="number" title="{{ __('Month Duration (days)') }}" :value="old('duration', 30)" placeholder="29, 30 or 31"
                            hint="{{ __('Number of calendar days in this Jalali month (29–31)') }}" required />
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold">{{ __('Employees') }}</span>
                        <label class="cursor-pointer flex items-center gap-2">
                            <input type="checkbox" id="bulk-select-all" class="checkbox checkbox-sm" checked
                                onchange="document.querySelectorAll('.bulk-employee-cb').forEach(cb => cb.checked = this.checked)" />
                            <span class="text-sm">{{ __('Select All') }}</span>
                        </label>
                    </div>
                    <div class="border border-base-300 rounded-box max-h-96 overflow-y-auto p-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1">
                        @foreach ($employees as $employee)
                            <label class="flex items-center gap-2 cursor-pointer p-2 hover:bg-base-200 rounded">
                                <input type="checkbox" name="employee_ids[]" value="{{ $employee->id }}" class="checkbox checkbox-sm bulk-employee-cb"
                                    {{ $employee->is_active ? 'checked' : '' }} />
                                <span class="text-sm">{{ $employee->first_name }} {{ $employee->last_name }}</span>
                                @if (!$employee->is_active)
                                    <span class="badge badge-error badge-xs">{{ __('Inactive') }}</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="alert alert-info mt-4 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        {{ __('One attendance log is created per worked day for each selected employee using their shift times. Fridays and public holidays are always skipped. Thursdays follow the shift setting: Just skipped if Holiday.') }}
                    </span>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('attendance.attendance-logs.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create Logs') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
