<div class="grid grid-cols-2 gap-4">
    <input type="hidden" name="document_id" value="{{ $document->id }}">
    <input type="hidden" name="user_id" value="{{ auth()->id() }}">

    <div class="col-span-2 md:col-span-1">
        <x-input title="{{ __('Title') }}" name="title" id="title" :value="old('title', $documentFile->title ?? '')" />
    </div>

    <div class="col-span-2 md:col-span-1 w-64 max-w-md">
        <x-input type="file" name="file"
            title="{{ __('File') }} ({{ __('Image') }} {{ __('or') }} {{ __('PDF') }})"
            accept="image/*,application/pdf" :bordered="false" :required="!isset($documentFile) || !$documentFile->exists || !$documentFile->path"
            class="block text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg
                file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" />

        @if (isset($documentFile) && $documentFile->exists && $documentFile->path)
            <p class="text-xs text-gray-600 mr-4 mt-2">{{ __('Current file') }}:
                <a href="{{ route('document-files.download', [$document, $documentFile]) }}"
                    class="text-blue-600 hover:text-blue-800 font-semibold">
                    {{ $documentFile->name ?? __('Download') }}
                </a>
            </p>
        @endif
    </div>
</div>
