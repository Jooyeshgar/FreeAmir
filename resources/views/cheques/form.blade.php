<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input type="number" step="0.01" title="{{ __('Amount') }}" name="amount" id="amount" :value="old('amount', $cheque->amount ?? '')" />
    </div>

    <div>
        <x-input type="date" title="{{ __('Write Date') }}" name="wrt_date" id="wrt_date" :value="old('wrt_date', $cheque->wrt_date ?? '')" />
    </div>

    <div>
        <x-input type="date" title="{{ __('Due Date') }}" name="due_date" id="due_date" :value="old('due_date', $cheque->due_date ?? '')" />
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
        <label class="label">
            <span class="label-text">{{ __('Cheque Book') }}</span>
        </label>
        <select name="cheque_book_id" class="select select-bordered w-full">
            <option value="">{{ __('Select') }}</option>
            @foreach ($chequeBooks as $id => $title)
                <option value="{{ $id }}" @selected(old('cheque_book_id', $cheque->cheque_book_id ?? '') == $id)>
                    {{ $title }}
                </option>
            @endforeach
        </select>
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
            <span class="label-text">{{ __('Is Received') }}</span>
            <input type="checkbox" name="is_received" value="1" class="toggle toggle-primary"
                @checked(old('is_received', $cheque->is_received ?? false)) />
        </label>
    </div>

    <div class="md:col-span-2">
        <x-textarea title="{{ __('Description') }}" name="desc" id="desc" :value="old('desc', $cheque->desc ?? '')" />
    </div>
</div>
