<x-app-layout :title="__('Import Documents')">
    <div class="card bg-base-100 shadow-xl max-w-2xl mx-auto">
        <form action="{{ route('documents.import.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="card-body">
                <h2 class="card-title">{{ __('Import Documents') }}</h2>
                <x-show-message-bags />

                <div class="alert alert-info">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current h-6 w-6 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <p class="text-sm">{{ __('Missing accounts are created automatically as long as their parent account exists in the system or is defined elsewhere in the file. If the parent cannot be found in either place, the document is rejected and the reason is reported.') }}</p>
                        <p class="mt-1 text-sm">{{ __('Duplicate documents (same number + date) will be skipped.') }}</p>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="fieldset w-full">
                        <div class="label">
                            <span>{{ __('Import Format') }} <span class="text-error">*</span></span>
                        </div>
                        <select name="format" class="select w-full @error('format') select-error @enderror" required>
                            <option value="" disabled @selected(! old('format'))>{{ __('— Select Format —') }}</option>
                            @foreach($formats as $key => $label)
                                <option value="{{ $key }}" @selected(old('format') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="fieldset w-full">
                        <div class="label">
                            <span>{{ __('CSV File') }} <span class="text-error">*</span></span>
                        </div>
                        <input type="file" name="file" accept=".csv,.txt" class="file-input w-full @error('file') file-input-error @enderror" required />
                    </label>
                </div>

                <div class="card-actions justify-end mt-4">
                    <a href="{{ route('documents.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Import CSV') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
