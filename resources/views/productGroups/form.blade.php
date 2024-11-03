<div class="grid grid-cols-2 gap-5">
    <div class="col-span-2 md:col-span-1">
        <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $productGroup->code ?? '')"
            placeholder="{{ __('Please insert code') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $productGroup->name ?? '')"
            placeholder="{{ __('Please enter name') }}" />
    </div>
</div>
