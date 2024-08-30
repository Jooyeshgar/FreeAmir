<div class="grid grid-cols-1 bg-gray-100">
    <div class="bg-gray-100 p-2 min-h-full">
        <div>
            <div class="grid grid-cols-4 gap-1">
                <div>
                    <x-form-input title="{{ __('Name') }}" name="name" place-holder="{{ __('Name') }}"
                        :value="old('name', $workshop->name ?? '')" />
                </div>
                <div>
                    <x-form-input title="{{ __('Code') }}" name="code" place-holder="{{ __('Code') }}"
                        :value="old('code', $workshop->code ?? '')" />
                </div>
                <div>
                    <x-form-input title="{{ __('Address') }}" name="address" place-holder="{{ __('Address') }}"
                        :value="old('address', $workshop->address ?? '')" />
                </div>
                <div>
                    <x-form-input title="{{ __('Telephone') }}" name="telephone" place-holder="{{ __('Telephone') }}"
                        :value="old('telephone', $workshop->telephone ?? '')" />
                </div>
            </div>
        </div>
    </div>
</div>