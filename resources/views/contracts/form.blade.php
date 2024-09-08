<div class="grid grid-cols-1 bg-gray-100">
    <div class="bg-gray-100 p-2 min-h-full">
        <div>
            <div class="grid grid-cols-2 gap-1">
                <div>
                    <x-form-input title="{{ __('Name') }}" name="name" place-holder="{{ __('Contract Name') }}" :value="old('name', $contract->name ?? '')" />
                </div>
                <div>
                    <x-form-input title="{{ __('Row') }}" name="row" place-holder="{{ __('Contract Row') }}" :value="old('row', $contract->row ?? '')" />
                </div>
                <div class="col-span-2">
                    <x-form-input title="{{ __('Description') }}" name="description" place-holder="{{ __('Description') }}" :value="old('description', $contract->description ?? '')" />
                </div>
            </div>
        </div>
    </div>
</div>
