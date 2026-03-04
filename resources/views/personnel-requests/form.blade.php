{{-- Shared form fields for PersonnelRequest edit --}}

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
        <x-select name="status" id="status" title="{{ __('Status') }}" :options="[
            'pending' => __('Pending'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
        ]" :selected="old('status', $personnelRequest->status ?? 'pending')" required />
    </div>

    <div>
        <label class="form-control w-full">
            <div class="label">
                <span class="label-text">{{ __('Approved By') }}</span>
            </div>
            <input type="text" class="input input-bordered w-full bg-base-200" disabled
                value="{{ $personnelRequest->approvedBy ? $personnelRequest->approvedBy->first_name . ' ' . $personnelRequest->approvedBy->last_name : '—' }}">
        </label>
    </div>

    <div class="md:col-span-2">
        <x-textarea name="reason" id="reason" title="{{ __('Reason') }}" :value="old('reason', $personnelRequest->reason ?? '')" placeholder="{{ __('Optional description…') }}" />
    </div>

</div>
