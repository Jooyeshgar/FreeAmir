<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        @php
            $hint = '<a class="link text-blue-500" href="' . route('product-groups.create') . '">اضافه کردن گروه خدمات  </a>';
        @endphp
        <x-select title="{{ __('Service group') }}" name="group" id="group" :options="$groups->pluck('name', 'id')"
            :selected="$service->group ?? null" :hint="$hint" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $service->code ?? '')"
            placeholder="{{ __('Please insert the code') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Service name') }}" :value="old('name', $service->name ?? '')"
            placeholder="{{ __('Please enter the service name') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="sstid" id="sstid" title="{{ __('Service SSTID') }}" :value="old('sstid',
            isset($service) ? ($service->sstid ?: ($groups->find($service->group)->sstid ?? '')) : '')"
            placeholder="{{ __('Please enter the service SSTID') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input @input="$event.target.value = $store.utils.formatNumber($event.target.value)" name="selling_price"
            id="selling_price" title="{{ __('Selling price') }}" :value="old('selling_price', $service->selling_price ?? '')" placeholder="{{ __('Please insert Selling price') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="vat" id="vat" title="{{ __('VAT') }}" :value="old('vat', $service->vat ?? '')"
            placeholder="0" />
    </div>    
</div>