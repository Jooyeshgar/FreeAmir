<div class="grid grid-cols-2 gap-6">
    <div class="col-span-2 md:col-span-1">
        <x-input name="income_to" id="income_to" type="number" title="{{ __('Income To') }}" :value="old('income_to', $taxSlab->income_to ?? '')" placeholder="{{ __('Leave empty for unlimited') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="tax_rate" id="tax_rate" type="number" title="{{ __('Tax Rate') }} (%)" :value="old('tax_rate', $taxSlab->tax_rate ?? '')" placeholder="0" required />
    </div>
</div>
