<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="year" id="year" title="{{ __('Year') }}" :value="old('year', $taxSlab->year ?? '')" placeholder="{{ __('e.g. 1403') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="slab_order" id="slab_order" type="number" title="{{ __('Slab Order') }}" :value="old('slab_order', $taxSlab->slab_order ?? '')" placeholder="{{ __('e.g. 1') }}" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="income_from" id="income_from" type="number" title="{{ __('Income From') }}" :value="old('income_from', $taxSlab->income_from ?? '')" placeholder="0" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="income_to" id="income_to" type="number" title="{{ __('Income To') }}" :value="old('income_to', $taxSlab->income_to ?? '')" placeholder="{{ __('Leave empty for unlimited') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="tax_rate" id="tax_rate" type="number" title="{{ __('Tax Rate') }} (%)" :value="old('tax_rate', $taxSlab->tax_rate ?? '')" placeholder="0" required />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="annual_exemption" id="annual_exemption" type="number" title="{{ __('Annual Exemption') }}" :value="old('annual_exemption', $taxSlab->annual_exemption ?? '')" placeholder="0" />
    </div>
</div>
