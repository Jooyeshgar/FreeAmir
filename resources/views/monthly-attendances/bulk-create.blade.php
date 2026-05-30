<x-app-layout :title="__('Bulk Monthly Attendance Creation')">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('attendance.monthly-attendances.bulk-store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Bulk Monthly Attendance Creation') }}</h2>
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
                    <div class="flex items-center justify-between">
                        <span class="font-semibold">{{ __('Employees') }}</span>
                        <x-checkbox id="bulk-select-all" name="" title="{{ __('Select All') }}" :checked="true"
                            onchange="document.querySelectorAll('#employee-list input[type=checkbox]').forEach(cb => cb.checked = this.checked)" />
                    </div>
                    <div id="employee-list" class="border grid grid-cols-3 p-2">
                        @foreach ($employees as $employee)
                            <div 
                                @if (!$employee->is_active) 
                                    x-data
                                    x-init="() => { const lbl = $el.querySelector('.label'); if (lbl) { lbl.classList.add('tooltip', 'text-error'); lbl.dataset.tip = '{{ __('Inactive') }}'; } }"
                                @endif
                            >
                                <x-checkbox name="employee_ids[]" :id="'employee-' . $employee->id" :value="$employee->id"
                                    title="{{ $employee->first_name }} {{ $employee->last_name }}" :checked="$employee->is_active" />
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="alert alert-info mt-4 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        {{ __('The working shift and daily hours are determined by the shift assigned to each employee. Fridays and public holidays are excluded from work days.') }}
                    </span>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('attendance.monthly-attendances.index') }}" class="btn btn-ghost">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Calculate & Save') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
