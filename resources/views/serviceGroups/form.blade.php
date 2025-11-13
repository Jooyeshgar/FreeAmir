<div class="grid grid-cols-2 gap-5">
    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $serviceGroup->name ?? '')"
            placeholder="{{ __('Please enter name') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="vat" id="vat" title="{{ __('VAT') }}" :value="old('vat', $serviceGroup->vat ?? 0)"
            placeholder="{{ __('Please enter VAT') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="sstid" id="sstid" title="{{ __(key: 'SSTID') }}" :value="old('sstid', $serviceGroup->sstid ?? '')"
            placeholder="{{ __('Please enter SSTID') }}" />
    </div>

</div>