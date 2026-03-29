<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input title="{{ __('Title') }}" name="title" id="title" :value="old('title', $chequeBook->title ?? '')" />
    </div>

    <div>
        <x-input type="date" title="{{ __('Issued At') }}" name="issued_at" id="issued_at" :value="old('issued_at', $chequeBook->issued_at ?? '')" />
    </div>

    <div>
        <label class="label">
            <span class="label-text">{{ __('Company') }}</span>
        </label>
        <select name="company_id" class="select select-bordered w-full">
            <option value="">{{ __('Select') }}</option>
            @foreach ($companies as $id => $name)
                <option value="{{ $id }}" @selected(old('company_id', $chequeBook->company_id ?? '') == $id)>
                    {{ $name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="label">
            <span class="label-text">{{ __('Bank Account') }}</span>
        </label>
        <select name="bank_account_id" class="select select-bordered w-full">
            <option value="">{{ __('Select') }}</option>
            @foreach ($bankAccounts as $id => $title)
                <option value="{{ $id }}" @selected(old('bank_account_id', $chequeBook->bank_account_id ?? '') == $id)>
                    {{ $title }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input title="{{ __('Status') }}" name="status" id="status" :value="old('status', $chequeBook->status ?? '')" />
    </div>

    <div class="flex items-center mt-8 gap-3">
        <label class="label cursor-pointer gap-3">
            <span class="label-text">{{ __('Is Sayad') }}</span>
            <input type="checkbox" name="is_sayad" value="1" class="toggle toggle-primary"
                @checked(old('is_sayad', $chequeBook->is_sayad ?? false)) />
        </label>
    </div>

    <div class="md:col-span-2">
        <x-textarea title="{{ __('Description') }}" name="desc" id="desc" :value="old('desc', $chequeBook->desc ?? '')" />
    </div>
</div>
