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
                        <p class="text-sm">{{ __('Subjects missing from the system will be created automatically, preserving the hierarchy.') }}</p>
                        <p class="mt-1 text-sm">{{ __('Duplicate documents (same number + date) will be skipped.') }}</p>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="fieldset w-full">
                        <div class="label">
                            <span>{{ __('CSV File') }} <span class="text-error">*</span></span>
                        </div>
                        <input type="file" name="file" accept=".csv,.txt" class="file-input w-full @error('file') file-input-error @enderror" required />
                        @error('file')
                            <div class="label"><span class="text-xs text-error">{{ $message }}</span></div>
                        @enderror
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
