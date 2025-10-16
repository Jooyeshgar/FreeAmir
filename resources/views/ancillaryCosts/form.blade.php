<div class="grid grid-cols-4 gap-6">
    <div class="col-span-1 md:col-span-1">
        <x-select title="{{ __('Invoice') }}" name="invoice" id="invoice" :options="$invoices->pluck('number', 'id')"
            :selected="$ancillaryCost->invoice ?? null"/>
    </div>

    <div class="col-span-1 md:col-span-1">
        <x-input @input="$event.target.value = $store.utils.formatNumber($event.target.value)" name="amount"
            id="amount" title="{{ __('Selling price') }}" :value="old('amount', $ancillaryCost->amount ?? '')" placeholder="{{ __('Please insert amount') }}" />
    </div>

    <div class="col-span-3 row-start-2">
        <x-textarea name="description" id="description" title="{{ __('Description') }}" :value="old('description', $product->description ?? '')" placeholder="{{ __('Please insert description') }}" />
    </div>
    
</div>