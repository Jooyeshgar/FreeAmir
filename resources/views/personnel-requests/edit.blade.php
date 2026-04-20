<x-app-layout :title="$personnelRequest->request_type?->label()">
    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('hr.personnel-requests.update', $personnelRequest) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="card-body">
                <h2 class="card-title">{{ $personnelRequest->request_type?->label() }}</h2>
                <x-show-message-bags />
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <x-date-picker name="request_date" id="request_date" :title="__('Date')" :value="old('request_date', isset($personnelRequest) ? convertToJalali($personnelRequest->start_date) : '')" :placeholder="__('Date')" required />
                    
                    <x-input name="start_time" id="start_time" type="text" :title="__('Start Time')" :value="old('start_time', isset($personnelRequest) ? $personnelRequest->start_date?->format('H:i') : '')" placeholder="{{ __('HH:MM — e.g. 08:30') }}" />
                    <x-input name="end_time" id="end_time" type="text" :title="__('End Time')" :value="old('end_time', isset($personnelRequest) ? $personnelRequest->end_date?->format('H:i') : '')" placeholder="{{ __('HH:MM — e.g. 17:00') }}" />

                    <div class="md:col-span-3">
                        <x-textarea name="reason" id="reason" title="{{ __('Reason') }}" :value="old('reason', $personnelRequest->reason ?? '')" placeholder="{{ __('Optional description…') }}" />
                    </div>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('hr.personnel-requests.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Update') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
