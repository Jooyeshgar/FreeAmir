<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Monthly Attendance') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('monthly-attendances.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Calculate Monthly Attendance') }}</h2>
                <x-show-message-bags />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    {{-- Employee --}}
                    <div class="md:col-span-2">
                        <x-select name="employee_id" id="employee_id" title="{{ __('Employee') }}" :options="$employees->mapWithKeys(fn($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->toArray()" :selected="old('employee_id')" required />
                    </div>

                    {{-- Start Date (beginning of the previous Jalali month) --}}
                    <div>
                        <x-date-picker name="start_date" id="start_date" title="{{ __('Start Date') }}" :value="old('start_date')" :hint="__('First day of the Jalali month (e.g. 1403/10/01)')" required />
                    </div>

                    {{-- Duration in days --}}
                    <div>
                        <x-input name="duration" id="duration" type="number" title="{{ __('Month Duration (days)') }}" :value="old('duration', 30)" placeholder="29, 30 or 31"
                            hint="{{ __('Number of calendar days in this Jalali month (29–31)') }}" required />
                    </div>

                </div>

                {{-- Shift reference card --}}
                <div class="alert alert-info mt-4 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        {{ __('Fixed working shift: :start – :end (:hours hours/day). Fridays and public holidays are excluded from work days.', [
                            'start' => \App\Services\AttendanceService::SHIFT_START,
                            'end' => \App\Services\AttendanceService::SHIFT_END,
                            'hours' => \App\Services\AttendanceService::WORK_MINUTES_PER_DAY / 60,
                        ]) }}
                    </span>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('monthly-attendances.index') }}" class="btn btn-ghost">
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
