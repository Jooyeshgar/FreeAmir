<div class="grid grid-cols-2 gap-4">
    <x-input name="document_id" value="{{ $document->id }}" hidden />
    <x-input name="user_id" value="{{ auth()->id() }}" hidden />

    <div class="col-span-2 md:col-span-1">
        <x-input title="{{ __('Title') }}" name="title" id="title" :value="old('title', $documentFile->title ?? '')" />
    </div>

    <div class="col-span-2 md:col-span-1 w-64 max-w-md">
        <x-file-input name="file" title="{{ __('File') }} ({{ __('Image') }} {{ __('or') }} {{ __('PDF') }})" accept="image/*,application/pdf"
            :required="!isset($documentFile) || !$documentFile->exists || !$documentFile->path" />

        @if (isset($documentFile) && $documentFile->exists && $documentFile->path)
            <p class="text-xs text-gray-600 mr-4 mt-2">{{ __('Current file') }}:
                <a href="{{ route('documents.files.download', [$document, $documentFile]) }}" class="text-blue-600 hover:text-blue-800 font-semibold">
                    {{ $documentFile->name ?? __('Download') }}
                </a>
            </p>
        @endif
    </div>
</div>
