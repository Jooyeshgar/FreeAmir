<x-app-layout :title="__('Send Moadian')">
    <form action="{{ route('invoices.send-moadian', $invoice) }}" method="POST">
        @csrf

        <div class="card-body">
            <h2 class="card-title">{{ __('Payment Information') }}</h2>
            <x-show-message-bags />

            <x-card class="w-full" class_body="p-4">
                <div class="flex justify-start gap-2">
                    <x-text-input data-jdp title="{{ __('Transaction date') }}" input_name="transaction_date" placeholder="{{ __('Transaction date') }}" readonly
                            input_value="{{ old('transaction_date') ?? convertToJalali(now(), true) }}"
                            label_text_class="text-gray-500 text-nowrap" input_class="datePicker">
                    </x-text-input>
                    <x-text-input x-data="{ transaction_reference_number: '{{ null }}' }"
                        title="{{ __('Transaction Reference Number') }}" x-model.number="transaction_reference_number" x-bind:name="'transaction_reference_number'"
                        placeholder="{{ __('Transaction Reference Number') }}" label_text_class="text-gray-500 text-nowrap"
                        x-on:input="transaction_reference_number = $store.utils.convertToEnglish($event.target.value);"
                        x-effect="$el.value = $store.utils.convertToFarsi($store.utils.formatNumber(transaction_reference_number));">
                    </x-text-input>
                </div>
                <div class="card-actions justify-end">
                    <button type="submit" class="btn btn-primary">{{ __('Send Moadian') }}</button>
                </div>
            </x-card>
        </div>
    </form>

    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endPushOnce
</x-app-layout>
