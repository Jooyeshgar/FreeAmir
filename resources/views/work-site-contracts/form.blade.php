<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <label for="work_site_id" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Work Site') }} <span class="text-red-500">*</span>
        </label>
        <select name="work_site_id" id="work_site_id"
            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" required>
            <option value="">— {{ __('Select Work Site') }} —</option>
            @foreach ($workSites as $workSite)
                <option value="{{ $workSite->id }}" {{ old('work_site_id', $workSiteContract->work_site_id ?? '') == $workSite->id ? 'selected' : '' }}>
                    {{ $workSite->name }}
                </option>
            @endforeach
        </select>
        @error('work_site_id')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $workSiteContract->name ?? '')" placeholder="{{ __('Contract name') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $workSiteContract->code ?? '')" placeholder="{{ __('e.g. C-001') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <label for="is_active" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Active') }}
        </label>
        <input type="checkbox" name="is_active" id="is_active" value="1" class="checkbox"
            {{ old('is_active', $workSiteContract->is_active ?? true) ? 'checked' : '' }} />
        @error('is_active')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
            {{ __('Description') }}
        </label>
        <textarea name="description" id="description" rows="3"
            class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
            placeholder="{{ __('Optional description') }}">{{ old('description', $workSiteContract->description ?? '') }}</textarea>
        @error('description')
            <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>
