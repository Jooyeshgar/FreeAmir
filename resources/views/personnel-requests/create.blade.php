<x-app-layout :title="$title">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('hr.personnel-requests.store') }}" method="POST">
            @csrf
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="card-body">
                <h2 class="card-title">{{ $title }}</h2>
                <x-show-message-bags />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4"
                    x-data="{ requestType: '{{ old('request_type', $personnelRequest->request_type->value ?? 'LEAVE_HOURLY') }}' }">
                    
                    <x-select name="employee_id" id="employee_id" title="{{ __('Employee') }}" :options="$employees->mapWithKeys(fn($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->toArray()" :selected="old('employee_id')" required />
                    <x-select name="request_type" id="request_type" title="{{ __('Request Type') }}" :options="$requestTypes" :selected="old('request_type', '')" required />
                    
                    <x-date-picker name="request_date" id="request_date" :title="__('Date')" :value="old('request_date')" :placeholder="__('Date')" required />
                    
                    <div class="contents" x-show="!['LEAVE_DAILY', 'MISSION_DAILY'].includes(requestType)">
                        <x-input name="start_time" id="start_time" type="text" :title="__('Start Time')" :value="old('start_time')" placeholder="{{ __('HH:MM — e.g. 08:30') }}" />
                        <x-input name="end_time" id="end_time" type="text" :title="__('End Time')" :value="old('end_time')" placeholder="{{ __('HH:MM — e.g. 17:00') }}" />
                    </div>

                    <div class="md:col-span-2">
                        <x-textarea name="reason" id="reason" title="{{ __('Reason') }}" :value="old('reason')" placeholder="{{ __('Optional description…') }}" />
                    </div>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('hr.personnel-requests.index', ['tab' => $tab]) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Submit Request') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
