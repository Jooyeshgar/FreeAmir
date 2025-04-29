<div x-data="{ toggleFields: false }">
    <div class="grid grid-cols-1 md:grid-cols-4">
        <x-checkbox @click="toggleFields = !toggleFields" name="toggleFields" id="toggleFields"
            title="{{ __('Disable Fields') }}" :checked="true" />
    </div>

    <fieldset id="subjectForm" :disabled="!toggleFields" class="grid grid-cols-2 gap-6 border p-5 my-3">
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
