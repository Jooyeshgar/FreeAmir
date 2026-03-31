<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-input title="{{ __('Cheque Book') }}" name="cheque_book_id" id="cheque_book_id" :value="$chequeBook->title ?? $cheque->chequeBook?->title" disabled />

    <div>
        <label class="label">
            <span class="label-text">{{ __('Customer') }}</span>
        </label>
        <select name="customer_id" class="select select-bordered w-full">
            <option value="">{{ __('Select') }}</option>
            @foreach ($customers as $id => $name)
                <option value="{{ $id }}" @selected(old('customer_id', $cheque->customer_id ?? '') == $id)>
                    {{ $name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input type="number" step="0.01" title="{{ __('Amount') }}" name="amount" id="amount"
            :value="old('amount', $cheque->amount ?? '')" />
    </div>

    <div>
        <x-date-picker title="{{ __('Write Date') }}" name="written_at" id="issued_at" :value="old('written_at', convertToJalali($cheque->written_at ?? now()))" />
    </div>

    <div>
        <x-date-picker title="{{ __('Due Date') }}" name="due_date" id="due_date" :value="old('due_date', convertToJalali($cheque->due_date ?? now()))" />
    </div>

    <div>
        <x-input title="{{ __('Serial') }}" name="serial" id="serial" :value="old('serial', $cheque->serial ?? '')" />
    </div>

    <div>
        <x-input type="number" title="{{ __('Cheque Number') }}" name="cheque_number" id="cheque_number"
            :value="old('cheque_number', $cheque->cheque_number ?? '')" />
    </div>

    <div>
        <x-input title="{{ __('Sayad Number') }}" name="sayad_number" id="sayad_number" :value="old('sayad_number', $cheque->sayad_number ?? '')" />
    </div>

    <div>
        <label class="label">
            <span class="label-text">{{ __('Transaction') }}</span>
        </label>
        <select name="transaction_id" class="select select-bordered w-full">
            <option value="">{{ __('Select') }}</option>
            @foreach ($transactions as $id => $label)
                <option value="{{ $id }}" @selected(old('transaction_id', $cheque->transaction_id ?? '') == $id)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="flex items-center mt-8 gap-3">
        <label class="label cursor-pointer gap-3">
            <span class="label-text">{{ __('Receivable') }}</span>
            <input type="checkbox" name="is_received" value="1" class="toggle toggle-primary"
                @checked(old('is_received', $cheque->is_received ?? false)) />
        </label>
    </div>

    <div class="md:col-span-2">
        <x-textarea title="{{ __('Description') }}" name="desc" id="desc" :value="old('desc', $cheque->desc ?? '')" />
    </div>
</div>
