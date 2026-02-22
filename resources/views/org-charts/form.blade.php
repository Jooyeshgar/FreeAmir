<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="title" id="title" title="{{ __('Title') }}" :value="old('title', $orgChart->title ?? '')" placeholder="{{ __('e.g. CEO') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <label for="parent_id" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Parent Node') }}
        </label>
        <select name="parent_id" id="parent_id" class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
            <option value="">— {{ __('None (Root Node)') }} —</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" {{ old('parent_id', $orgChart->parent_id ?? '') == $parent->id ? 'selected' : '' }}>
                    {{ $parent->title }}
                </option>
            @endforeach
        </select>
        @error('parent_id')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Description') }}
        </label>
        <textarea name="description" id="description" rows="3"
            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            placeholder="{{ __('Optional description') }}">{{ old('description', $orgChart->description ?? '') }}</textarea>
        @error('description')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>
