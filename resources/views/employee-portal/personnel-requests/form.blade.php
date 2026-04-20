<div class="grid grid-cols-1 md:grid-cols-2 gap-4" 
    x-data="{ requestType: '{{ old('request_type', $personnelRequest->request_type->value ?? 'LEAVE_HOURLY') }}' }">
    
    <x-select name="request_type" id="request_type" title="{{ __('Request Type') }}" :options="$requestTypes" 
        :selected="old('request_type', $personnelRequest->request_type->value ?? 'LEAVE_HOURLY')" x-model="requestType" required/>

    <x-date-picker name="request_date" id="request_date" :title="__('Date')" 
        :value="old('request_date', isset($personnelRequest) ? convertToJalali($personnelRequest->start_date) : '')" :placeholder="__('Date')" required />
    
    <div class="contents" x-show="!['LEAVE_DAILY', 'MISSION_DAILY'].includes(requestType)">
        <x-input name="start_time" id="start_time" type="text" :title="__('Start Time')" :value="old('start_time', isset($personnelRequest) ? $personnelRequest->start_date?->format('H:i') : '')" placeholder="{{ __('HH:MM — e.g. 08:30') }}" />
        <x-input name="end_time" id="end_time" type="text" :title="__('End Time')" :value="old('end_time', isset($personnelRequest) ? $personnelRequest->end_date?->format('H:i') : '')" placeholder="{{ __('HH:MM — e.g. 17:00') }}" />
    </div>

    <div class="md:col-span-2">
        <x-textarea name="reason" id="reason" title="{{ __('Reason') }}" :value="old('reason', $personnelRequest->reason ?? '')" placeholder="{{ __('Optional description…') }}" />
    </div>
</div>