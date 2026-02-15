<fieldset class="grid grid-cols-2 gap-6 border p-5 my-3">
    <legend> {{ __('Bank Account') }} </legend>
    <div class="col-span-2 md:col-span-1">
        <x-input name="name" id="name" title="{{ __('Name') }}" :value="old('name', $bankAccount->name ?? '')" placeholder="{{ __('Please enter the name') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input type="number" name="number" id="number" title="{{ __('Account Number') }}" :value="old('number', $bankAccount->number ?? '')"
            placeholder="{{ __('Please enter the account number') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input type="number" name="type" id="type" title="{{ __('Type') }}" :value="old('type', $bankAccount->type ?? '')" placeholder="{{ __('Please enter the account type') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="owner" id="owner" title="{{ __('Owner') }}" :value="old('owner', $bankAccount->owner ?? '')" placeholder="{{ __('Please enter the owner') }}" />
    </div>

    <div class="col-span-2">
        <x-textarea name="desc" id="desc" title="{{ __('Description') }}" placeholder="{{ __('Please enter the description') }}" :value="old('desc', $bankAccount->desc ?? '')" />
    </div>
</fieldset>

<fieldset class="grid grid-cols-2 gap-6 border p-5">
    <legend> {{ __('Bank Info') }} </legend>
    <div class="col-span-2 md:col-span-1">
        @php
            $initialBankId = old('bank_id', $bankAccount->bank_id ?? null);
            $initialSelectedValue = $initialBankId ? "bank-$initialBankId" : null;
            $hint = '<a class="link text-blue-500" href="' . route('banks.create') . '">اضافه کردن بانک</a>';
        @endphp
        <div class="h-full flex flex-wrap items-end" x-data="{ bank_id: '{{ $initialBankId }}', selectedValue: '{{ $initialSelectedValue }}' }">
            <span class="label-text">{{ __('Bank') }}</span>

            <x-select-box url="{{ route('bank-accounts.search-bank') }}" :options="[['headerGroup' => 'bank', 'options' => $banks]]" x-model="selectedValue"
                x-init="if (!selectedValue && bank_id) { selectedValue = 'bank-' + bank_id; }" placeholder="{{ __('Select Bank') }}" hint='{!! $hint !!}'
                @selected="bank_id = $event.detail.id;" class="" />

            <input type="hidden" x-bind:value="bank_id" name="bank_id">
        </div>
    </div>
    <div class="col-span-2 md:col-span-1">
        <x-input name="bank_branch" id="bank_branch" title="{{ __('Bank Branch') }}" :value="old('bank_branch', $bankAccount->bank_branch ?? '')" placeholder="{{ __('Please enter the bank branch') }}" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="bank_phone" id="bank_phone" title="{{ __('Bank Phone') }}" :value="old('bank_phone', $bankAccount->bank_phone ?? '')" placeholder="{{ __('Please enter the bank phone') }}" />
    </div>

    <div class="col-span-2">
        <x-textarea name="bank_address" id="bank_address" title="{{ __('Address') }}" placeholder="{{ __('Please enter the Bank Address') }}" :value="old('bank_address', $bankAccount->bank_address ?? '')" />
    </div>

    <div class="col-span-2 md:col-span-1">
        <x-input name="bank_web_page" id="bank_web_page" title="{{ __('Bank Website') }}" :value="old('bank_web_page', $bankAccount->bank_web_page ?? '')" placeholder="{{ __('Please enter the bank phone') }}" />
    </div>

</fieldset>
