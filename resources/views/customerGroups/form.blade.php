<div class="grid grid-cols-2 gap-6">

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $customerGroup->name ?? '')"
            placeholder="{{ __('Please enter name') }}" />
    </div>

    <div class="col-span-2">
        <x-textarea name="description" id="description" title="{{ __('Description') }}"
            placeholder="{{ __('Please enter the description') }}" :value="old('description', $customerGroup->description ?? '')" />
    </div>

</div>