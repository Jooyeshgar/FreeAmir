<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $organizationUnit->name ?? '')" required />
    </div>

    <div>
        <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $organizationUnit->code ?? '')" />
    </div>

    <div>
        @php
            $selectedParentId = old('parent_id', $organizationUnit->parent_id ?? '');
        @endphp
        <label for="parent_id" class="block text-sm font-medium label">
            {{ __('Parent Unit') }}
        </label>
        <select name="parent_id" id="parent_id" class="select w-full">
            <option value="">{{ __('— None —') }}</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" {{ (string) $selectedParentId === (string) $parent->id ? 'selected' : '' }}>
                    {{ $parent->name }}
                </option>
            @endforeach
        </select>
        @error('parent_id')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-end">
        <label class="cursor-pointer justify-start gap-3">
            <x-checkbox name="is_active" id="is_active" :title="__('Active')" value="1" 
                    :checked="old('is_active', $organizationUnit->is_active ?? true)" />
        </label>
    </div>

    <div class="md:col-span-2">
        <label for="description" class="block text-sm font-medium label mb-1">
            {{ __('Description') }}
        </label>
        <textarea name="description" id="description" rows="3" class="textarea textarea-bordered w-full"
            placeholder="{{ __('Optional description') }}">{{ old('description', $organizationUnit->description ?? '') }}</textarea>
        @error('description')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>
