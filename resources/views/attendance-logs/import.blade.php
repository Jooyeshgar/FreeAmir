<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Import Attendance Logs') }}
        </h2>
    </x-slot>

    <div class="card bg-base-100 shadow-xl max-w-2xl mx-auto">
        <form action="{{ route('attendance.attendance-logs.import.preview') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Import Attendance Logs') }}</h2>
                <x-show-message-bags />

                <div>
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('Import Format') }} <span class="text-error">*</span></span>
                        </div>
                        <select name="import_type" class="select select-bordered @error('import_type') select-error @enderror" required>
                            <option value="">{{ __('— Select Format —') }}</option>
                            @foreach ($importTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('import_type') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('import_type')
                            <div class="label"><span class="label-text-alt text-error">{{ $message }}</span></div>
                        @enderror
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                    <div>
                        <x-date-picker name="date_from" id="date_from" title="{{ __('From Date') }}" :value="old('date_from')" placeholder="{{ __('e.g. 1403/01/01') }}" />
                    </div>
                    <div>
                        <x-date-picker name="date_to" id="date_to" title="{{ __('To Date') }}" :value="old('date_to')" placeholder="{{ __('e.g. 1403/12/29') }}" />
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('Duplicate Mode') }} <span class="text-error">*</span></span>
                        </div>
                        <select name="duplicate_mode" class="select select-bordered @error('duplicate_mode') select-error @enderror" required>
                            <option value="ignore" {{ old('duplicate_mode', 'ignore') === 'ignore' ? 'selected' : '' }}>
                                {{ __('Ignore (keep existing record)') }}
                            </option>
                            <option value="replace" {{ old('duplicate_mode') === 'replace' ? 'selected' : '' }}>
                                {{ __('Replace (overwrite existing record)') }}
                            </option>
                        </select>
                        @error('duplicate_mode')
                            <div class="label"><span class="label-text-alt text-error">{{ $message }}</span></div>
                        @enderror
                    </label>
                </div>

                <div class="mt-3">
                    <label class="form-control w-full">
                        <div class="label">
                            <span class="label-text">{{ __('File') }} <span class="text-error">*</span></span>
                        </div>
                        <input type="file" name="file" class="file-input file-input-bordered w-full @error('file') file-input-error @enderror" required />
                        @error('file')
                            <div class="label"><span class="label-text-alt text-error">{{ $message }}</span></div>
                        @enderror
                    </label>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('attendance.attendance-logs.index') }}" class="btn btn-ghost">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Preview') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
