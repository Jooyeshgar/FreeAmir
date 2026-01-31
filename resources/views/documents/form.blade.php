@php
    $subjectIds = $document->transactions->pluck('subject_id')->toArray();
@endphp

<x-card class="rounded-2xl w-full" class_body="p-4">
    <x-text-input type="text" input_value="{{ $subjectIds ? implode(',', $subjectIds) : 0 }}" input_class="subjectIds"
        hidden></x-text-input>
    <div class="flex gap-2">
        <x-text-input input_name="title" title="{{ __('document name') }}"
            input_value="{{ old('title') ?? $document->title }}" placeholder="{{ __('document name') }}"
            label_text_class="text-gray-500" label_class="w-full" input_class="max-w-96"></x-text-input>
        <x-text-input input_value="{{ $document->id ?? '' }}" input_name="document_id" label_text_class="text-gray-500"
            label_class="w-full hidden"></x-text-input>
        <div class="flex-1"></div>
        <x-text-input disabled="true" input_value="{{ formatDocumentNumber($previousDocumentNumber) }}" input_name=""
            title="{{ __('previous document number') }}" placeholder="{{ __('previous document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input input_value="{{ old('number') ?? ($document->exists ? formatDocumentNumber($document->number) : null) }}"
            input_name="number" title="{{ __('current document number') }}"
            placeholder="{{ __('current document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input data-jdp title="{{ __('date') }}" input_name="date" placeholder="{{ __('date') }}"
            input_value="{{ old('date') ?? convertToJalali($document->date ?? now()) }}"
            label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
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
        <div class="text-sm flex-1 min-w-80 max-w-80 text-center text-gray-500 pt-3">
            {{ __('chapter title') }}
        </div>
        <div class="text-sm flex-1 min-w-80 text-center text-gray-500 pt-3">
            {{ __('description') }}
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3">
            {{ __('debit') }}
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3">
            {{ __('credit') }}
        </div>
    </div>
    <div class="min-h-96 overflow-y-auto">
        <div id="transactions" x-data="{ activeTab: {{ $total }} }">
            <template x-for="(transaction, index) in transactions" :key="transaction.id">
                <div :class="{ 'active': activeTab === index }" class="transaction flex gap-2 items-center px-4 pb-3"
                    @click="activeTab = index" x-data="{
                        selectedName: transaction.subject,
                        selectedCode: transaction.code,
                        selectedId: transaction.subject_id,
                    }">
                    <input type="hidden" x-bind:value="transaction.transaction_id"x-bind:name="'transactions[' + index + '][transaction_id]'">

                    <div class="relative flex-1 text-center max-w-8 pt-2 pb-2 transaction-count-container">
                        <span class="transaction-count block" x-text="index + 1"></span>
                        <button @click.stop="transactions.splice(index, 1)" type="button"
                            class="absolute left-0 top-0 removeButton">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor"
                                class="px-2 size-8 rounded-md h-10 flex justify-center items-center text-center bg-red-500 hover:bg-red-700 text-white font-bold removeTransaction">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input x-bind:value="$store.utils.formatCode(selectedCode)"
                            label_text_class="text-gray-500" label_class="w-full"
                            input_class="border-white value codeInput "></x-text-input>
                    </div>
                    <div>
                        <x-subject-select url="{{ route('documents.search-subjects') }}" :subjects="$subjects"
                            x-bind:selected_id="selectedId" x-bind:selected_name="selectedName"
                            x-bind:selected_code="selectedCode" placeholder="{{ __('Select a subject') }}"
                            @selected="
                                selectedName = $event.detail.name;
                                selectedCode = $event.detail.code;
                                selectedId = $event.detail.id;
                            "
                            class="w-80 max-w-80" />

                        <input type="hidden" x-bind:value="selectedCode" x-bind:name="'transactions[' + index + '][code]'">
                        <input type="hidden" x-bind:value="selectedId" x-bind:name="'transactions[' + index + '][subject_id]'">
                        <input type="hidden" x-bind:value="selectedName" x-bind:name="'transactions[' + index + '][subject]'">
                    </div>
                    <div class="flex-1 w-[200px]">
                        <x-text-input x-bind:value="transaction.desc"
                            placeholder="{{ __('this document\'s row description') }}"
                            x-bind:name="'transactions[' + index + '][desc]'" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white "></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-bind:value="$store.utils.formatNumber(transaction.debit)"
                            x-bind:name="'transactions[' + index + '][debit]'" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white debitInput"
                            x-on:input="transaction.debit = $store.utils.convertToEnglish($event.target.value); $event.target.value = $store.utils.formatNumber(transaction.debit)"></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-bind:value="$store.utils.formatNumber(transaction.credit)"
                            x-bind:name="'transactions[' + index + '][credit]'" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white creditInput"
                            x-on:input="transaction.credit = $store.utils.convertToEnglish($event.target.value); $event.target.value = $store.utils.formatNumber(transaction.credit)"></x-text-input>
                    </div>
                </div>
            </template>

            <button class="flex justify-content gap-4 align-center w-full px-4" id="addTransaction"
                @click="addTransaction; activeTab = transactions.length;" type="button">
                <div
                    class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active">
                    <span class="text-2xl">+</span>
                    {{ __('Add Transaction') }}
                </div>
            </button>
        </div>
    </div>
    <hr style="">
    <div class="flex justify-end px-4 gap-2" x-data="{
        get debitTotal() {
            return transactions.reduce((sum, transaction) => sum + (Number($store.utils.convertToEnglish(transaction.debit || 0))), 0);
        },
        get creditTotal() {
            return transactions.reduce((sum, transaction) => sum + (Number($store.utils.convertToEnglish(transaction.credit || 0))), 0);
        },
        get balance() {
            return this.creditTotal - this.debitTotal;
        }
    }">
        <div class="flex items-center gap-2">
            <span class="text-gray-500">{{ __('Balance') }}:</span>
            <span class="min-w-24 text-center text-gray-500" id="diffSum"
                x-text="balance.toLocaleString()">0</span>
        </div>
        <span class="min-w-24 text-center text-gray-500" id="debitSum" x-text="debitTotal.toLocaleString()">0</span>
        <span class="min-w-24 text-center text-gray-500" id="creditSum"
            x-text="creditTotal.toLocaleString()">0</span>
    </div>
</x-card>
<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ route('documents.index') }}" type="submit" class="btn btn-default rounded-md"> {{ __('cancel') }}
    </a>
    <button id="submitFormPlus" type="button" class="btn btn-default rounded-md">
        {{ __('save and create new document') }}
    </button>
    <button id="submitForm" type="submit" class="btn text-white btn-primary rounded-md">
        {{ __('save and close form') }} </button>
</div>

@pushOnce('scripts')
    <script type="module">
        jalaliDatepicker.startWatch();
    </script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('transactionForm', () => ({
                transactions: {!! json_encode($transactions, JSON_UNESCAPED_UNICODE) !!},
                addTransaction() {
                    const newId = this.transactions.length ? this.transactions[this.transactions
                        .length - 1].id + 1 : 1;
                    this.transactions.push({
                        id: newId,
                        name: '',
                        subject: '',
                        code: '',
                        subject_id: '',
                        debit: '',
                        credit: '',
                        desc: ''
                    });
                }
            }));
        });
    </script>
@endPushOnce
