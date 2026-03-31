<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <x-input title="{{ __('Cheque Book') }}" name="cheque_book_id" id="cheque_book_id" :value="$chequeBook->title ?? $cheque->chequeBook?->title" disabled />

    @php
        $initialCustomerId = old('customer_id', $cheque->customer_id ?? null);
        $initialSelectedValue = $initialCustomerId ? "customer-$initialCustomerId" : null;
        $hint =
            '<a class="link text-blue-500 hover:underline" href="' .
            route('customers.create') .
            '">' .
            __('Add Customer') .
            '</a>';
    @endphp

    <div class="h-full flex flex-wrap items-end" x-data="{
        customer_id: '{{ $initialCustomerId }}',
        selectedValue: '{{ $initialSelectedValue }}',
    }">
        <span class="label-text">{{ __('Customer') }}
            <a href="{{ route('customers.create') }}" class="btn btn-xs btn-ghost text-blue-500 hover:text-blue-700"
                title="{{ __('Add Customer') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 14.25c-2.485 0-4.5 1.79-4.5 4s2.015 4 4.5 4 4.5-1.79 4.5-4-2.015-4-4.5-4zm0-2.25a3 3 0 100-6 3 3 0 000 6zm6-1.5h3m-1.5-1.5v3" />
                </svg>
            </a>
        </span>

        <x-select-box url="{{ route('cheques.search-customer') }}" :options="[['headerGroup' => 'customer', 'options' => $customers]]" x-model="selectedValue"
            x-init="if (!selectedValue && customer_id) {
                selectedValue = 'customer-' + customer_id;
            }" placeholder="{{ __('Select Customer') }}" hint='{!! $hint !!}'
            @selected="customer_id = $event.detail.id;" />

        <input type="hidden" x-bind:value="customer_id" name="customer_id">
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

    @php
        $initialTransactionId = old('transaction_id', $cheque->transaction_id ?? null);
        $initialSelectedValue = $initialTransactionId ? "transaction-$initialTransactionId" : null;
    @endphp

    <div class="h-full flex flex-wrap items-end" x-data="{
        transaction_id: '{{ $initialTransactionId }}',
        selectedValue: '{{ $initialSelectedValue }}',
    }">
        <span class="label-text">{{ __('Transaction') }}</span>

        <x-select-box url="{{ route('cheques.search-transaction') }}" :options="[['headerGroup' => 'transaction', 'options' => $transactions]]" x-model="selectedValue"
            x-init="if (!selectedValue && transaction_id) {
                selectedValue = 'transaction-' + transaction_id;
            }" placeholder="{{ __('Select transaction') }}"
            @selected="transaction_id = $event.detail.id;" />

        <input type="hidden" x-bind:value="transaction_id" name="transaction_id">
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
