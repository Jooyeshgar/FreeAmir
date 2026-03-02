<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('New Request') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl">
        <form action="{{ route('employee-portal.personnel-requests.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Submit a Personnel Request') }}</h2>
                <x-show-message-bags />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div class="md:col-span-2">
                        <label class="form-control w-full">
                            <div class="label">
                                <span class="label-text">{{ __('Request Type') }}</span>
                            </div>
                            <select name="request_type" id="request_type" class="select select-bordered @error('request_type') select-error @enderror" required>
                                <option value="">{{ __('— Select —') }}</option>
                                @foreach ($requestTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('request_type') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('request_type')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </label>
                    </div>

                    <div>
                        <label class="form-control w-full">
                            <div class="label">
                                <span class="label-text">{{ __('Start Date & Time') }}</span>
                            </div>
                            <input type="datetime-local" name="start_date" id="start_date"
                                value="{{ old('start_date') }}"
                                class="input input-bordered @error('start_date') input-error @enderror"
                                required />
                            @error('start_date')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </label>
                    </div>

                    <div>
                        <label class="form-control w-full">
                            <div class="label">
                                <span class="label-text">{{ __('End Date & Time') }}</span>
                            </div>
                            <input type="datetime-local" name="end_date" id="end_date"
                                value="{{ old('end_date') }}"
                                class="input input-bordered @error('end_date') input-error @enderror"
                                required />
                            @error('end_date')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </label>
                    </div>

                    <div>
                        <label class="form-control w-full">
                            <div class="label">
                                <span class="label-text">{{ __('Duration (minutes)') }}</span>
                            </div>
                            <input type="number" name="duration_minutes" id="duration_minutes"
                                value="{{ old('duration_minutes', 0) }}"
                                min="0"
                                class="input input-bordered @error('duration_minutes') input-error @enderror"
                                placeholder="0" />
                            @error('duration_minutes')
                                <div class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </div>
                            @enderror
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <label class="form-control w-full">
                            <div class="label">
                                <span class="label-text">{{ __('Reason') }}</span>
                            </div>
                            <textarea name="reason" id="reason" rows="3"
                                class="textarea textarea-bordered @error('reason') textarea-error @enderror"
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
                    <a href="{{ route('employee-portal.personnel-requests.index') }}" class="btn btn-ghost">
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
