<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        @php
            $hint = '<a class="link text-blue-500" href="' . route('product-groups.create') . '">اضافه کردن کالا</a>';
        @endphp
        <x-select title="{{ __('Product group') }}" name="group" id="group" :options="$groups->pluck('name', 'id')"
            :selected="$product->group ?? null" :hint="$hint" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="code" id="code" title="{{ __('Code') }}" :value="old('code', $product->code ?? '')"
            placeholder="{{ __('Please insert the code') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Product name') }}" :value="old('name', $product->name ?? '')"
            placeholder="{{ __('Please enter the product name') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="sstid" id="sstid" title="{{ __('Product SSTID') }}" :value="old('sstid', $product->sstid ?? '')"
            placeholder="{{ __('Please enter the product SSTID') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input @input="$event.target.value = $store.utils.formatNumber($event.target.value)" name="selling_price"
            id="selling_price" title="{{ __('Selling price') }}" :value="old('selling_price', $product->selling_price ?? '')" placeholder="{{ __('Please insert Selling price') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input @input="$event.target.value = $store.utils.formatNumber($event.target.value)" name="purchace_price"
            id="purchace_price" title="{{ __('Purchase price') }}" :value="old('purchace_price', $product->purchace_price ?? '')" placeholder="{{ __('Please insert Purchase price') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="vat" id="vat" title="{{ __('Vat') }}" :value="old('vat', $product->vat ?? '')"
            placeholder="0" />
    </div>

    <div class="col-span-2">
        <x-textarea name="notes" id="notes" title="{{ __('Description') }}" :value="old('description', $product->description ?? '')" placeholder="{{ __('Please insert description') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="location" id="location" title="{{ __('Location in warehouse') }}" :value="old('location', $product->location ?? '')" placeholder="{{ __('Please insert the location in warehouse') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="discount_formula" id="discount_formula" title="{{ __('Discount formula') }}"
            :value="old('discount_formula', $product->discount_formula ?? '')"
            placeholder="{{ __('Please enter the discount formula') }}"
            hint="{{ __('(From amount) - (To amount) : Discount amount') }}"
            hint2="{{ __('For example:') . ' 1-30:400, 30-100:360.7' }}" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <x-checkbox title="{{ __('Oversell') }}" name="oversell" id="oversell" :checked="old('oversell', $product->oversell ?? 0)" />
        </div>
        <div class="md:col-span-2">
            <x-input @input="$event.target.value = $store.utils.formatNumber($event.target.value)"
                name="quantity_warning" id="quantity_warning" title="{{ __('Quantity warning') }}"
                :value="old('quantity_warning', $product->quantity_warning ?? '')"
                placeholder="{{ __('Please insert quantity warning') }}" />
        </div>
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input @input="$event.target.value = $store.utils.formatNumber($event.target.value)" name="quantity"
            id="quantity" title="{{ __('Quantity') }}" :value="old('quantity', $product->quantity ?? '')"
            placeholder="{{ __('Please insert quantity') }}" />
    </div>
</div>