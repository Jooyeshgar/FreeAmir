<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex gap-2">
        <x-text-input input_name="title" title="{{ __('Invoice name') }}" input_value="{{ old('title') ?? '' }}"
            placeholder="{{ __('Invoice name') }}" label_text_class="text-gray-500" label_class="w-full"
            input_class="max-w-96"></x-text-input>
        <x-text-input input_value="" input_name="invoice_id" label_text_class="text-gray-500"
            label_class="w-full hidden"></x-text-input>
        <div class="flex-1"></div>

        <x-text-input disabled="true" input_value="{{ formatDocumentNumber($previousDocumentNumber) }}" input_name=""
            title="{{ __('previous document number') }}" placeholder="{{ __('previous document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input input_value="{{ old('number') ?? formatDocumentNumber($previousDocumentNumber + 1) }}"
            input_name="document_number" title="{{ __('current document number') }}"
            placeholder="{{ __('current document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>

        <x-text-input disabled="true" input_value="{{ formatDocumentNumber($previousInvoiceNumber) }}" input_name=""
            title="{{ __('previous invoice number') }}" placeholder="{{ __('previous invoice number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input input_value="{{ old('number') ?? formatDocumentNumber($previousInvoiceNumber + 1) }}"
            input_name="invoice_number" title="{{ __('current invoice number') }}"
            placeholder="{{ __('current invoice number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>

        <x-text-input data-jdp title="{{ __('date') }}" input_name="date" placeholder="{{ __('date') }}"
            input_value="{{ old('date') ?? '' }}" label_text_class="text-gray-500 text-nowrap"
            input_class="datePicker"></x-text-input>
    </div>
</x-card>
<x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-4" x-data="transactionForm">
    <div class="flex overflow-x-auto overflow-y-hidden gap-2 items-center px-4">
        <div class="text-sm flex-1 max-w-8 text-center text-gray-500 pt-3">
            *
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-32 text-center text-gray-500 pt-3">
            {{ __('chapter code') }}
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-64 text-center text-gray-500 pt-3">
            {{ __('chapter title') }}
        </div>
        <div class="text-sm flex-1 min-w-80 text-center text-gray-500 pt-3">
            {{ __('description') }}
        </div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">
            {{ __('quantity') }}
        </div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">
            {{ __('unit') }}
        </div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">
            {{ __('total') }}
        </div>
    </div>
    <div class="h-96 overflow-y-auto">
        <div id="transactions" x-data="{ activeTab: {{ $total }} }">
            <template x-for="(transaction, index) in transactions" :key="transaction.id">
                <div :class="{ 'active': activeTab === index }" class="transaction flex gap-2 items-center px-4 pb-3" @click="activeTab = index" x-data="{
                    selectedName: transaction.subject,
                    selectedCode: transaction.code,
                    selectedId: transaction.subject_id,
                }">
                    <input type="text" x-bind:value="transaction.transaction_id" x-bind:name="'transactions[' + index + '][transaction_id]'" hidden>
                    <input type="text" x-bind:value="selectedCode" x-bind:name="'transactions[' + index + '][code]'" hidden>
                    <input type="text" x-bind:value="selectedId" x-bind:name="'transactions[' + index + '][subject_id]'" hidden>

                    <div class="relative flex-1 text-center max-w-8 pt-2 pb-2 transaction-count-container">
                        <span class="transaction-count block" x-text="index + 1"></span>
                        <button @click.stop="transactions.splice(index, 1)" type="button" class="absolute left-0 top-0 removeButton">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                class="px-2 size-8 rounded-md h-10 flex justify-center items-center text-center bg-red-500 hover:bg-red-700 text-white font-bold removeTransaction">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input x-bind:value="$store.utils.formatCode(selectedCode)" label_text_class="text-gray-500" label_class="w-full"
                            input_class="border-white value codeInput "></x-text-input>
                    </div>
                    <div>
                        <x-subject-select-box :subjects="$subjects"></x-subject-select-box>
                    </div>
                    <div class="flex-1 w-[200px]">
                        <x-text-input x-bind:value="transaction.desc" placeholder="{{ __('this invoices row description') }}"
                            x-bind:name="'transactions[' + index + '][desc]'" label_text_class="text-gray-500" label_class="w-full"
                            input_class="border-white "></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.quantity" x-bind:name="'transactions[' + index + '][quantity]'"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white quantityInput"
                            x-on:input="transaction.quantity = convertToEnglish($event.target.value)"></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.unit" x-bind:name="'transactions[' + index + '][unit]'"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white unitInput"
                            x-on:input="transaction.unit = convertToEnglish($event.target.value)"></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input x-model.number="transaction.total" placeholder="0" x-bind:name="'transactions[' + index + '][total]'"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white totalInput"
                            x-on:input="transaction.total = convertToEnglish($event.target.value)"></x-text-input>
                    </div>
                </div>
            </template>

            <button class="flex justify-content gap-4 align-center w-full px-4" id="addTransaction" @click="addTransaction; activeTab = transactions.length;"
                type="button">
                <div class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active">
                    <span class="text-2xl">+</span>
                    {{ __('Add Transaction') }}
                </div>
            </button>
        </div>
    </div>
    <hr style="">
    <div class="flex justify-end px-4 gap-2">
        <span class="min-w-24 text-center text-gray-500" id="quantitySum"
            x-text="transactions.reduce((sum, transaction) => sum + (Number(convertToEnglish(transaction.quantity || 0)) ), 0)">0</span>
        <span class="min-w-24 text-center text-gray-500" id="totalSum"
            x-text="transactions.reduce((sum, transaction) => sum + (Number(convertToEnglish(transaction.total || 0))), 0)">0</span>
    </div>
</x-card>
<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ route('invoices.index') }}" type="submit" class="btn btn-default rounded-md"> {{ __('cancel') }}
    </a>
    <button id="submitFormPlus" type="button" class="btn btn-default rounded-md">
        {{ __('save and create new invoice') }}
    </button>
    <button id="submitForm" type="submit" class="btn text-white btn-primary rounded-md">
        {{ __('save and close form') }} </button>
</div>

@pushOnce('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('transactionForm', () => ({
                transactions: {!! json_encode($transactions, JSON_UNESCAPED_UNICODE) !!},
                addTransaction() {
                    const newId = this.transactions.length ? this.transactions[this.transactions.length - 1].id + 1 : 1;
                    this.transactions.push({
                        id: newId,
                        name: '',
                        subject: '',
                        code: '',
                        subject_id: '',
                        quantity: '',
                        total: '',
                        desc: ''
                    });
                }
            }));
        });
    </script>
@endPushOnce
