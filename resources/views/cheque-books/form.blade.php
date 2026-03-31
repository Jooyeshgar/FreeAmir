<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <x-input title="{{ __('Title') }}" name="title" id="title" :value="old('title', $chequeBook->title ?? '')"
            placeholder="{{ __('Enter the title') }}" />
    </div>

    <div>
        <x-select title="{{ __('Bank Account') }}" name="bank_account_id" id="bank_account_id" :options="$bankAccounts"
            :selected="old('bank_account_id', $chequeBook->bank_account_id ?? '')" />
    </div>

    <div>
        <x-date-picker title="{{ __('Issued At') }}" name="issued_at" id="issued_at" :value="old('issued_at', convertToJalali($chequeBook->issued_at ?? now()))" />
    </div>

    <div class="flex items-center mt-8 gap-3">
        <label class="label cursor-pointer gap-3">
            <span class="label-text">{{ __('Active') }}</span>
            <input type="checkbox" name="is_active" value="1" class="toggle toggle-primary"
                @checked(old('is_active', $chequeBook->is_active ?? false)) />
        </label>
    </div>

    <div class="flex items-center mt-8 gap-3">
        <label class="label cursor-pointer gap-3">
            <span class="label-text">{{ __('Sayad') }}</span>
            <input type="checkbox" name="is_sayad" value="1" class="toggle toggle-primary"
                @checked(old('is_sayad', $chequeBook->is_sayad ?? false)) />
        </label>
    </div>

    <div class="md:col-span-3">
        <x-textarea title="{{ __('Description') }}" name="desc" id="desc" :value="old('desc', $chequeBook->desc ?? '')" />
    </div>
</div>
