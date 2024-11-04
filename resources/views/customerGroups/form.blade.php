<div class="grid grid-cols-2 gap-6">

    <div class="col-span-2 md:col-span-1">
        <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $customerGroup->code ?? '')"
            placeholder="{{ __('Please insert code') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $customerGroup->name ?? '')"
            placeholder="{{ __('Please enter name') }}" />
    </div>

    <div class="col-span-2">
        <x-textarea name="description" id="description" title="{{ __('Description') }}"
            placeholder="{{ __('Please enter the desc') }}" :value="old('description', $customerGroup->description ?? '')" />
    </div>

</div>
