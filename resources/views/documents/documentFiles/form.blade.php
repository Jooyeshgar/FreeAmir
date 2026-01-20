<form action="{{ route('document-files.store', $document) }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="grid grid-cols-2 gap-4">
        <input type="hidden" name="document_id" value="{{ $document->id }}">
        <input type="hidden" name="user_id" value="{{ auth()->id() }}">

        <div class="col-span-2 md:col-span-1">
            <x-input title="{{ __('Title') }}" name="title" id="title" :value="old('title')" />
        </div>

        <div class="col-span-2 md:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ __('File (Image or PDF)') }}
            </label>

            <input type="file" name="file" accept="image/*,application/pdf" required
                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700
                    hover:file:bg-gray-200" />

            @error('file')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="col-span-2">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                {{ __('Upload File') }}
            </button>
        </div>
    </div>
</form>
