<div>
    <div class="grid grid-cols-1 md:grid-cols-4">
        
    </div>
    
    <x-card class="bg-yellow-50 border-l-4 border-yellow-400 mb-5">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    <strong class="font-bold">{{ __('Caution') }}: </strong>
                    <span class="font-medium">{{ __("Changes to these settings may affect your fiscal data integrity. Please proceed with care.") }}</span>
                </p>
            </div>
        </div>
    </x-card>

    <fieldset id="subjectForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
        <legend>{{ __('Subject Info') }}</legend>
        <div class="col-span-2 md:col-span-1">
            <x-select name="cust_subject" id="cust_subject" title="{{ __('Customers') }}" :options="$subjects->pluck('name', 'id')"
                :selected="old('cust_subject', $configs['cust_subject'] ?? '')" />
        </div>
        <div class="col-span-2 md:col-span-1">
            <x-select name="bank" id="bank" title="{{ __('Banks') }}" :options="$subjects->pluck('name', 'id')" :selected="old('bank', $configs['bank'] ?? '')" />
        </div>
        <div class="col-span-2 md:col-span-1">
            <x-select name="cash_book" id="cash_book" title="{{ __('Cash book') }}" :options="$subjects->pluck('name', 'id')"
                :selected="old('cash_book', $configs['cash_book'] ?? '')" />
        </div>
        <div class="col-span-2 md:col-span-1">
            <x-select name="income" id="income" title="{{ __('Income') }}" :options="$subjects->pluck('name', 'id')" :selected="old('income', $configs['income'] ?? '')" />
        </div>
        <div class="col-span-2 md:col-span-1">
            <x-input name="cash" id="cash" title="{{ __('Cash') }}" :value="old('cash', $configs['cash'] ?? '')" />
        </div>
        <div class="col-span-2 md:col-span-1">
            <x-input name="buy_discount" id="buy_discount" title="{{ __('Buy Discount') }}" :value="old('buy_discount', $configs['buy_discount'] ?? '')" />
        </div>
        <div class="col-span-2 md:col-span-1">
            <x-input name="sell_discount" id="sell_discount" title="{{ __('Sell Discount') }}" :value="old('sell_discount', $configs['sell_discount'] ?? '')" />
        </div>
        <div class="col-span-2 md:col-span-1">
            <x-input name="sell_vat" id="sell_vat" title="{{ __('Sell VAT') }}" :value="old('sell_vat', $configs['sell_vat'] ?? '')" />
        </div>
        <div class="col-span-2 md:col-span-1">
            <x-input name="buy_vat" id="buy_vat" title="{{ __('Buy VAT') }}" :value="old('buy_vat', $configs['buy_vat'] ?? '')" />
        </div>
    </fieldset>
</div>
