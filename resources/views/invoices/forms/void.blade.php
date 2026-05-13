<x-app-layout :title="__('Void') . ' ' . __('Invoice')">
    <form action="{{ route('invoices.void', $invoice) }}" method="POST">
        @csrf
        <div class="card-body">
            <h2 class="card-title">{{ __('Void') . ' ' . __('Invoice') }}</h2>

            <x-show-message-bags />

            <x-card class="rounded-2xl w-full" class_body="p-4">
                <div class="flex justify-start gap-2 mt-2">
                    <x-text-input data-jdp title="{{ __('Void date') }}" input_name="date" placeholder="{{ __('Void date') }}" readonly
                        input_value="{{ old('date') ?? convertToJalali(now(), true) }}"
                        label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>

                    <x-text-input x-data="{ invoice_number: '{{ formatDocumentNumber($previousInvoiceNumber + 1) }}' }"
                        title="{{ __('Void Invoice Number') }}" x-model.number="invoice_number" x-bind:name="'invoice_number'"
                        placeholder="{{ __('Void Invoice Number') }}" label_text_class="text-gray-500 text-nowrap"
                        x-on:input="invoice_number = $store.utils.convertToEnglish($event.target.value);"
                        x-effect="$el.value = $store.utils.convertToFarsi($store.utils.formatNumber(invoice_number));">
                    </x-text-input>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="card bg-base-200 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="card-title text-sm font-medium text-gray-500 dark:text-slate-300">{{ __('Title') }}
                                {{ __('Invoice') }}</h3>
                            <p class="text-lg font-semibold text-gray-800 dark:text-slate-100">{{ __('Invoice') . ' ' . $invoice->invoice_type->label() . ' #' . formatDocumentNumber($invoice->number ?? $invoice->id) }}</p>
                        </div>
                    </div>
                </div>

                <div class="card-actions justify-end">
                    <a href="{{ url()->previous() }}" class="btn btn-ghost">{{ __('Back') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Void') }}</button>
                </div>
            </form>
        </x-card>
    </div>

    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endPushOnce
    
</x-app-layout>