<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex gap-2">
        <x-text-input name="title" title="{{ __('document name') }}" value="{{ old('title') ?? $document->title }}"
            placeholder="{{ __('document name') }}" label_text_class="text-gray-500" label_class="w-full"
            input_class="max-w-96"></x-text-input>
        <x-text-input value="{{ $document->id ?? '' }}" name="document_id" label_text_class="text-gray-500"
            label_class="w-full hidden"></x-text-input>
        <div class="flex-1"></div>
        <x-text-input disabled="true" value="{{ formatDocumentNumber($previousDocumentNumber) }}" name=""
            title="{{ __('previous document number') }}" placeholder="{{ __('previous document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input
            value="{{ old('number') ?? formatDocumentNumber($document->number ?? $previousDocumentNumber + 1) }}"
            name="number" title="{{ __('current document number') }}" placeholder="{{ __('current document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input data-jdp title="{{ __('date') }}" name="date" placeholder="{{ __('date') }}"
            value="{{ old('date') ?? $document->FormattedDate }}" label_text_class="text-gray-500 text-nowrap"
            input_class="datePicker"></x-text-input>
    </div>
</x-card>

<x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-0">

    <div class="flex overflow-x-auto overflow-y-hidden  gap-2 items-center px-4  ">
        <div class="text-sm flex-1 max-w-8  text-center text-gray-500 pt-3 ">
            *
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3 ">
            {{ __('chapter code') }}
        </div>
        <div class="text-sm flex-1 min-w-80 max-w-80 text-center text-gray-500 pt-3 ">
            {{ __('chapter title') }}
        </div>
        <div class="text-sm flex-1 min-w-80 text-center text-gray-500 pt-3 ">
            {{ __('description') }}
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3 ">
            {{ __('debit') }}
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-24 text-center text-gray-500 pt-3 ">
            {{ __('credit') }}
        </div>
    </div>
    <div class="h-96 overflow-y-auto" x-data="{
        transactions: [
            { id: 1, name: '', amount: '' }
        ],
        addTransaction() {
            const newId = this.transactions.length ? this.transactions[this.transactions.length - 1].id + 1 : 1;
            this.transactions.push({ id: newId, name: '', amount: '' });
        },
        removeTransaction(index) {
            this.transactions.splice(index, 1);
        }
    }">
        <div id="transactions">
            <template x-for="(transaction, index) in transactions" :key="transaction.id">
                @foreach ($transactions as $i => $transaction)
                    <div class="transaction flex gap-2 items-center px-4 " id="originalTransactions"
                        x-data="{ hover: false }" @mouseenter="hover = true" @mouseleave="hover = false">

                        <x-text-input value="{{ $transaction->id ?? '' }}"
                            name="transactions[{{ $i }}][transaction_id]" label_text_class="text-gray-500"
                            label_class="w-full hidden"></x-text-input>

                        <div class="relative flex-1 text-center max-w-8 pt-2 pb-5 transaction-count-container">
                            <span class="transaction-count block" x-text="index + 1"></span>

                            <button @click="removeTransaction(index)" x-show="hover" type="button"
                                class="absolute left-0 top-0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor"
                                    class="px-2 size-8 rounded-md  h-10 flex justify-center items-center text-center bg-red-500 hover:bg-red-700 text-white font-bold removeTransaction">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>

                        </div>
                        <div class="flex-1 min-w-24 max-w-24 pb-3">

                            <x-text-input
                                value="{{ $transaction->subject ? $transaction->subject->formattedCode() : '' }}"
                                id="value" name="transactions[{{ $i }}][code]"
                                label_text_class="text-gray-500" label_class="w-full"
                                input_class="border-white hover:border-slate-400 value codeInput "></x-text-input>

                        </div>
                        <x-subject-select-box :subjects="$subjects" :name="'transactions[' . $i . '][subject_id]'"
                            :value="$transaction->subject_id ?? ''"></x-subject-select-box>
                        <div class="flex-1 w-[200px] pb-3">
                            <x-text-input value="{{ $transaction->desc }}"
                                placeholder="{{ __('this document\'s row description') }}" id="desc"
                                name="transactions[{{ $i }}][desc]" label_text_class="text-gray-500"
                                label_class="w-full" input_class="border-white hover:border-slate-400 "></x-text-input>

                        </div>

                        <div class="flex-1 min-w-24 max-w-32 pb-3">
                            <x-text-input value="{{ $transaction->debit ? $transaction->debit : '0' }}" placeholder="0" id="debit"
                                name="transactions[{{ $i }}][debit]" label_text_class="text-gray-500"
                                label_class="w-full"
                                input_class="border-white hover:border-slate-400 debitInput"></x-text-input>
                        </div>
                        <div class="flex-1 min-w-24 max-w-32 pb-3">
                            <x-text-input value="{{ $transaction->credit ? $transaction->credit : '0' }}" placeholder="0" id="credit"
                                name="transactions[{{ $i }}][credit]" label_text_class="text-gray-500"
                                label_class="w-full"
                                input_class="border-white hover:border-slate-400 creditInput"></x-text-input>

                        </div>
                    </div>
                @endforeach
            </template>
        </div>

        <button class="flex justify-content gap-4 align-center w-full px-4" id="addTransaction" @click="addTransaction" type="button">
            <div class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active">
                <span class="text-2xl">+</span>
                {{ __('Add Transaction') }}
            </div>
        </button>
    </div>

    <hr style="">
    <div class="flex justify-end px-4 gap-2">
        <span class="min-w-24 text-center text-gray-500" id="debitSum">0</span>
        <span class="min-w-24 text-center text-gray-500" id="creditSum">0</span>
    </div>
</x-card>
<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ route('documents.index') }}" type="submit" class="btn btn-default rounded-md"> {{ __('cancel') }}
    </a>
    <button type="submit" class="btn btn-default rounded-md"> {{ __('save and create new document') }} </button>
    <button type="submit" class="btn text-white btn-primary rounded-md"> {{ __('save and close form') }} </button>
</div>

<script type="module">
    jalaliDatepicker.startWatch({});
</script>
<script>
    function openSelectBox(e) {
        document.querySelectorAll(".selfSelectBox").forEach(function(e) {
            e.style.display = "none"
        }), e.querySelector(".selfSelectBox").style.display = "block"
    }

    function fillInput(e, t) {
        let n = e.querySelector(".selfItemTitle").innerText,
            a = e.querySelector(".selfItemCode").innerText,
            s = e.querySelector(".selfItemId").innerText;
        document.querySelectorAll(".subject_name")[t].value = n, document.querySelectorAll(".subject_id")[t].value = s,
            document.querySelectorAll(".value")[t].value = a
    }
</script>
