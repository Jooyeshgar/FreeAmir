<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Attendance Log') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('attendance.attendance-logs.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Add Attendance Log') }}</h2>
                <x-show-message-bags />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div class="md:col-span-2">
                        <x-select name="employee_id" id="employee_id" title="{{ __('Employee') }}" :options="$employees->mapWithKeys(fn($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->toArray()" :selected="old('employee_id', '')" required />
                    </div>

                    <div>
                        <x-date-picker name="log_date" id="log_date" title="{{ __('Date') }}" :value="old('log_date', '')" required />
                    </div>

                    <div>{{-- spacer --}}</div>

                    <div>
                        <x-input name="entry_time" id="entry_time" placeholder="08:00" type="text" title="{{ __('Entry Time') }}" :value="old('entry_time', '')" />
                    </div>

                    <div>
                        <x-input name="exit_time" id="exit_time" placeholder="16:00" type="text" title="{{ __('Exit Time') }}" :value="old('exit_time', '')" />
                    </div>

                    <div class="md:col-span-2 flex items-center gap-3 mt-1">
                        <label class="label cursor-pointer gap-2">
                            <input type="hidden" name="is_manual" value="0" />
                            <input type="checkbox" name="is_manual" id="is_manual" value="1" class="checkbox checkbox-warning"
                                {{ old('is_manual') ? 'checked' : '' }} />
                            <span class="label-text">{{ __('Manually Corrected') }}</span>
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <x-textarea name="description" id="description" title="{{ __('Description') }}" :value="old('description', '')" placeholder="{{ __('Optional notes…') }}" />
                    </div>

                </div>

                <div class="card-actions justify-end">
                    <a href="{{ route('attendance.attendance-logs.index') }}" class="btn btn-ghost">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
