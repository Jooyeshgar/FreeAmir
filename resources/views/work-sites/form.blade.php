<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $workSite->name ?? '')" placeholder="{{ __('Work site name') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $workSite->code ?? '')" placeholder="{{ __('e.g. WS-001') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="phone" id="phone" title="{{ __('Phone') }}" :value="old('phone', $workSite->phone ?? '')" placeholder="{{ __('Phone number') }}" />
    </div>

    <div class="col-span-2">
        <label class="label" for="address">
            <span class="label-text">{{ __('Address') }}</span>
        </label>
        <textarea name="address" id="address" rows="3" class="textarea textarea-bordered w-full" placeholder="{{ __('Address') }}">{{ old('address', $workSite->address ?? '') }}</textarea>
        @error('address')
            <span class="text-error text-sm">{{ $message }}</span>
        @enderror
    </div>

    <div class="col-span-2 md:col-span-1">
        <label class="label cursor-pointer justify-start gap-3">
            <input type="hidden" name="is_active" value="0" />
            <input type="checkbox" name="is_active" id="is_active" value="1" class="checkbox checkbox-primary"
                {{ old('is_active', $workSite->is_active ?? true) ? 'checked' : '' }} />
            <span class="label-text">{{ __('Active') }}</span>
        </label>
    </div>
</div>
