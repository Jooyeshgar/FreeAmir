<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $title }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('personnel-requests.store') }}" method="POST">
            @csrf
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="card-body">
                <h2 class="card-title">{{ $title }}</h2>
                <x-show-message-bags />

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <div class="md:col-span-3">
                        <div class="w-72">
                            <x-select name="employee_id" id="employee_id" :title="__('Employee')" :options="$employees->mapWithKeys(fn($e) => [$e->id => $e->first_name . ' ' . $e->last_name])->toArray()" :selected="old('employee_id')" required />
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <div class="w-72">
                            <x-select name="request_type" id="request_type" :title="__('Request Type')" :options="$requestTypes" :selected="old('request_type')" required />
                        </div>
                    </div>

                    <x-date-picker name="request_date" id="request_date" :title="__('Date')" :value="old('request_date')" :placeholder="__('Date')" required />

                    <x-input name="start_time" id="start_time" type="text" :title="__('Start Time')" :value="old('start_time')" placeholder="{{ __('HH:MM — e.g. 08:30') }}"
                        required />

                    <x-input name="end_time" id="end_time" type="text" :title="__('End Time')" :value="old('end_time')" placeholder="{{ __('HH:MM — e.g. 17:00') }}" required />

                    <div>
                        <x-select name="status" id="status" :title="__('Status')" :options="[
                            'pending' => __('Pending'),
                            'approved' => __('Approved'),
                            'rejected' => __('Rejected'),
                        ]" :selected="old('status', 'pending')" required />
                    </div>

                    <div class="md:col-span-3">
                        <label class="form-control w-full">
                            <div class="label">
                                <span class="label-text">{{ __('Reason') }}</span>
                            </div>
                            <textarea name="reason" id="reason" rows="3" class="textarea textarea-bordered @error('reason') textarea-error @enderror"
                                placeholder="{{ __('Optional description…') }}">{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </label>
                    </div>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('personnel-requests.index', ['tab' => $tab]) }}" class="btn btn-ghost">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Submit Request') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
