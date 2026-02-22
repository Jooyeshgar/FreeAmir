<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $workSite->name ?? '')" placeholder="{{ __('Work site name') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $workSite->code ?? '')" placeholder="{{ __('e.g. WS-001') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="phone" id="phone" title="{{ __('Phone') }}" :value="old('phone', $workSite->phone ?? '')" placeholder="{{ __('Optional phone number') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <label for="is_active" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Active') }}
        </label>
        <input type="checkbox" name="is_active" id="is_active" value="1" class="checkbox" {{ old('is_active', $workSite->is_active ?? true) ? 'checked' : '' }} />
        @error('is_active')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-span-2">
        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Address') }}
        </label>
        <textarea name="address" id="address" rows="3"
            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" placeholder="{{ __('Optional address') }}">{{ old('address', $workSite->address ?? '') }}</textarea>
        @error('address')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>
