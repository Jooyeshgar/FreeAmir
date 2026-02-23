{{-- Shared form fields for PersonnelRequest create / edit --}}

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <div class="md:col-span-2">
        <x-select name="employee_id" id="employee_id" title="{{ __('Employee') }}" :options="$employees->mapWithKeys(fn($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->toArray()" :selected="old('employee_id', $personnelRequest->employee_id ?? '')" required />
    </div>

    <div class="md:col-span-2">
        <x-select name="request_type" id="request_type" title="{{ __('Request Type') }}" :options="$requestTypes" :selected="old('request_type', $personnelRequest->request_type->value ?? '')" required />
    </div>

    <div>
        <x-input name="start_date" id="start_date" type="datetime-local" title="{{ __('Start Date') }}" :value="old('start_date', isset($personnelRequest) ? $personnelRequest->start_date?->format('Y-m-d\TH:i') : '')" required />
    </div>

    <div>
        <x-input name="end_date" id="end_date" type="datetime-local" title="{{ __('End Date') }}" :value="old('end_date', isset($personnelRequest) ? $personnelRequest->end_date?->format('Y-m-d\TH:i') : '')" required />
    </div>

    <div>
        <x-input name="duration_minutes" id="duration_minutes" type="number" title="{{ __('Duration (minutes)') }}" :value="old('duration_minutes', $personnelRequest->duration_minutes ?? 0)" placeholder="0" />
    </div>

    <div>
        <x-select name="status" id="status" title="{{ __('Status') }}" :options="[
            'pending' => __('Pending'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
        ]" :selected="old('status', $personnelRequest->status ?? 'pending')" required />
    </div>

    <div>
        <x-select name="approved_by" id="approved_by" title="{{ __('Approved By') }}" :options="array_merge(['' => __('— None —')], $employees->mapWithKeys(fn($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->toArray())" :selected="old('approved_by', $personnelRequest->approved_by ?? '')" />
    </div>

    <div class="md:col-span-2">
        <x-textarea name="reason" id="reason" title="{{ __('Reason') }}" :value="old('reason', $personnelRequest->reason ?? '')" placeholder="{{ __('Optional description…') }}" />
    </div>

</div>
