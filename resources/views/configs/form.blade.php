<div class="grid grid-cols-1 md:grid-cols-4">
    <x-checkbox name="toggleFields" id="toggleFields" title="{{ __('Disable Fields') }}" :checked="true" />
</div>
<fieldset id="companyForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
    <legend>{{ __('Company Info') }}</legend>
    <div class="col-span-2 md:col-span-1">
        <x-input name="company-name" id="company-name" title="{{ __('Company Name') }}" :value="old('company-name', $configs['company-name'] ?? '')" />
    </div>
    <div class="col-span-2 md:col-span-1 flex gap-x-4">
        <label for="co_logo">
            {{ __('Company Logo') }}
        </label>
        <input type="file" id="co_logo" name="co_logo" class="file-input w-full max-w-xs" accept="image/*" />
    </div>
    <img class="block w-12 h-auto rounded-full"
        src="{{ asset('storage/' . (old('co_logo') ?? ($configs['co_logo'] ?? ''))) }}"
        alt="{{ old('co_logo', $configs['co_logo'] ?? '') }}" />
    <div class="col-span-2">
        <x-textarea name="co_address" id="co_address" title="{{ __('Company Address') }}" :value="old('co_address', $configs['co_address'] ?? '')" />
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-input name="co_economical_code" id="co_economical_code" title="{{ __('Economical Code') }}" :value="old('co_economical_code', $configs['co_economical_code'] ?? '')" />
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-input name="co_national_code" id="co_national_code" title="{{ __('National Code') }}" :value="old('co_national_code', $configs['co_national_code'] ?? '')" />
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-input name="co_postal_code" id="co_postal_code" title="{{ __('Postal Code') }}" :value="old('co_postal_code', $configs['co_postal_code'] ?? '')" />
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-input name="co_phone_number" id="co_phone_number" title="{{ __('Company Phone') }}" :value="old('co_phone_number', $configs['co_phone_number'] ?? '')" />
    </div>
</fieldset>

<fieldset id="subjectForm" class="grid grid-cols-2 gap-6 border p-5 my-3">
    <legend>{{ __('Subject Info') }}</legend>

    <div class="col-span-2 md:col-span-1">
        <x-select name="cust_subject" id="cust_subject" title="{{ __('Subject Type') }}" :options="$subjects->pluck('name', 'id')" :value="old('subject_type', $configs['subject_type'] ?? '')" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-select name="cust_subject" id="cust_subject" title="{{ __('Banks') }}" :options="$banks->pluck('name', 'id')" :value="old('bank', $configs['bank'] ?? '')" />
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
    
    <div class="col-span-2 md:col-span-1">
        <x-input name="sell_free" id="sell_free" title="{{ __('Sales Tax') }}" :value="old('sell_free', $configs['sell_free'] ?? '')" />
    </div>
</fieldset>

<script>
    toggleFormFields('companyForm', true);
    toggleFormFields('subjectForm', true);

    document.getElementById('toggleFields').addEventListener('change', function() {
        toggleFormFields('companyForm', this.checked);
        toggleFormFields('subjectForm', this.checked);
    });

    function toggleFormFields(formId, disable) {
        let formFields = document.getElementById(formId).elements;
        for (let i = 0; i < formFields.length; i++) {
            formFields[i].disabled = disable;
        }
    }
</script>
