<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex gap-2">
        <x-text-input name="title" title="{{ __('document name') }}" value="{{ old('title') ?? $document->title }}"
            placeholder="{{ __('document name') }}" label_text_class="text-gray-500" label_class="w-full"
            input_class="max-w-96"></x-text-input>
        <x-text-input value="{{ $document->id ?? '' }}" name="document_id" label_text_class="text-gray-500"
            label_class="w-full hidden"></x-text-input>
        <div class="flex-1"></div>
        <x-text-input disabled="true" value="{{ $previousDocumentNumber }}" name=""
            title="{{ __('previous document number') }}" placeholder="{{ __('previous document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input value="{{ old('number') ?? ($document->number ?? $previousDocumentNumber + 1) }}" name="number"
            title="{{ __('previous document number') }}" placeholder="{{ __('current document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>
        <x-text-input data-jdp title="{{ __('date') }}" name="date" placeholder="{{ __('date') }}"
            value="{{ old('date') ?? $document->jalali_date }}" label_text_class="text-gray-500 text-nowrap"
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
    <div class="h-96 overflow-y-auto px-4">
        <div id="transactions">
            @foreach ($transactions as $i => $transaction)
                <div class="transaction flex gap-2 items-center ">

                    <x-text-input value="{{ $transaction->id ?? '' }}"
                        name="transactions[{{ $i }}][transaction_id]" label_text_class="text-gray-500"
                        label_class="w-full hidden"></x-text-input>

                    <div class="flex-1 text-center  max-w-8 pb-3">
                        <span class="transaction-count">1</span>

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor"
                            class="px-2 size-8 rounded-md  h-10 flex justify-center items-center text-center  bg-red-500 hover:bg-red-700 text-white font-bold rounded removeTransaction text-center">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>

                    </div>
                    <div class="flex-1 min-w-24 max-w-24 pb-3">

                        <x-text-input value="{{ $transaction->subject ? $transaction->subject->code : '' }}"
                            id="value" name="transactions[{{ $i }}][code]"
                            label_text_class="text-gray-500" label_class="w-full"
                            input_class="value codeInput "></x-text-input>

                    </div>
                    <div class="selfSelectBoxContainer relative flex-1 min-w-80 max-w-80 pb-3">
                        <x-text-input name="transactions[{{ $i }}][subject_id]" value="" readonly
                            id="subject_id" label_text_class="text-gray-500" label_class="w-full"
                            input_class="subject_id codeSelectBox " onclick="openSelectBox(0)"></x-text-input>
                        <div
                            class="selfSelectBox hidden absolute z-[3] top-[40px] w-full h-[300px] bg-white overflow-auto px-4 pb-4 rounded-[16px] shadow-[0px_43px_27px_0px_#00000012]">
                            <div class="sticky top-0 left-0 right-0 w-full bg-white py-2">
                                <div class="relative">
                                    <x-text-input name="" value="" label_text_class="text-gray-500"
                                        label_class="w-full" input_class="pe-8 text-sm"
                                        placeholder="{{ __('Search... (heading code or name)') }}"></x-text-input>

                                    <span class="absolute block left-2 top-1/2 translate-y-[-50%]">
                                        <svg width="18" height="19" viewBox="0 0 18 19" fill="none"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path
                                                d="M2 7.5C2 4.73858 4.23858 2.5 7 2.5C9.76142 2.5 12 4.73858 12 7.5C12 10.2614 9.76142 12.5 7 12.5C4.23858 12.5 2 10.2614 2 7.5ZM7 0.5C3.13401 0.5 0 3.63401 0 7.5C0 11.366 3.13401 14.5 7 14.5C8.57234 14.5 10.0236 13.9816 11.1922 13.1064L16.2929 18.2071C16.6834 18.5976 17.3166 18.5976 17.7071 18.2071C18.0976 17.8166 18.0976 17.1834 17.7071 16.7929L12.6064 11.6922C13.4816 10.5236 14 9.07234 14 7.5C14 3.63401 10.866 0.5 7 0.5Z"
                                                fill="#242424" />
                                        </svg>
                                    </span>
                                </div>
                            </div>

                            <div class="overflow-auto h-[calc(100%-56px)] pe-1">
                                <div class="flex justify-between mt-2 font-bold text-xs">
                                    <span>
                                        {{ __('Title name') }}
                                    </span>

                                    <span>
                                        {{ __('Header code') }}
                                    </span>
                                </div>

                                <div class="mt-4 text-xs">
                                    @foreach ($subjects as $subject)
                                        <div class="w-full ps-2 mb-4">
                                            <div class="flex justify-between">
                                                <span>
                                                    {{ $subject->name }}
                                                </span>

                                                <span>
                                                    {{ $subject->id }}
                                                </span>
                                            </div>

                                            <div class="ps-1 mt-4">
                                                <div class="border-s-[1px] ps-4 border-[#ADB5BD]">
                                                    <a href="javascript:void(0)"
                                                        class="selfSelectBoxItems flex justify-between" onclick="fillInput(this, 0)">
                                                        <span class="selfItemTitle">
                                                            {{ $subject->name }}
                                                        </span>

                                                        <span class="selfItemCode">
                                                            {{ $subject->id }}
                                                        </span>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        {{-- <select name="transactions[{{ $i }}][subject_id]" id="subject_id"
                            class="codeSelectBox hidden rounded-md max-h-10 min-h-10 select select-bordered border-slate-400 disabled:background-slate-700 w-full max-w-42 focus:outline-none ">
                            <option value="">{{ __('Select a subject') }}</option>
                            @foreach ($subjects as $subject)
                                <option {{ $subject->parent_id ? '' : 'disabled' }} value="{{ $subject->id }}" data-title="{{ $subject->name }}"
                                    data-type="{{ $subject->type }}" {{ $transaction->subject_id == $subject->id ? 'selected' : '' }}>
                                    {{ $subject->name }} {{ $subject->type == 'both' ? '' : '- (' . $subject->type . ')' }}
                                </option>
                            @endforeach

                        </select> --}}
                    </div>
                    <div class="flex-1 min-w-80 pb-3">
                        <x-text-input value="{{ $transaction->desc }}"
                            placeholder="{{ __('this document\'s row description') }}" id="desc"
                            name="transactions[{{ $i }}][desc]" label_text_class="text-gray-500"
                            label_class="w-full" input_class=""></x-text-input>

                    </div>

                    <div class="flex-1 min-w-24 max-w-24 pb-3">
                        <x-text-input value="{{ $transaction->value < 0 ? -1 * $transaction->value : '' }}"
                            placeholder="0" id="debit" name="transactions[{{ $i }}][debit]"
                            label_text_class="text-gray-500" label_class="w-full"
                            input_class="debitInput"></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-24 pb-3">
                        <x-text-input value="{{ $transaction->value >= 0 ? $transaction->value : '' }}"
                            placeholder="0" id="credit" name="transactions[{{ $i }}][credit]"
                            label_text_class="text-gray-500" label_class="w-full"
                            input_class="creditInput"></x-text-input>

                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex justify-content gap-4 align-center">
            <div class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active"
                id="addTransaction">
                <span class="text-2xl">+</span>
                {{ __('Add Transaction') }}
            </div>
        </div>
    </div>

    <hr style="">
    <div class="flex justify-end px-4 gap-2">
        <span class="min-w-24 text-center text-gray-500" id="debitSum">1000</span>
        <span class="min-w-24 text-center text-gray-500" id="creditSum">2000</span>
    </div>
</x-card>
<div class="mt-2 flex gap-2 justify-end">
    <a href="{{ route('documents.index') }}" type="submit" class="btn btn-default rounded-md"> {{ __('cancel') }}
    </a>
    <button type="submit" class="btn btn-default rounded-md"> {{ __('save and create new document') }} </button>
    <button type="submit" class="btn text-white btn-primary rounded-md"> {{ __('save and close form') }} </button>
</div>
<script type="module">
    jalaliDatepicker.startWatch({});
</script>
<script>
    var t = 0;
    var subjects = {!! json_encode($subjects) !!};
    var p2e = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))

    function onCodeInputChange(e, selectBox) {
        let code = e.target.value
        code = p2e(code)
        e.target.value = code
        let itemIndex = subjects.findIndex(i => (code === i.code && i.parent_id))
        if (itemIndex !== -1) selectBox.value = subjects[itemIndex].id;
    }

    function onCodeSelectBoxChange(e, codeInput) {
        let id = e.target.value
        let itemIndex = subjects.findIndex(i => parseInt(id) === parseInt(i.id))
        if (itemIndex !== -1) codeInput.value = subjects[itemIndex].code;
    }

    function deleteAction() {
        if (document.getElementsByClassName('removeTransaction').length > 1) {
            this.parentNode.parentNode.remove();
            updateTransactionCounter()
        }
    }

    function activeRow(e) {
        console.log(e.currentTarget)
        deactivateAllTransactionRow()
        e.currentTarget.classList.remove('deactivated-transaction-row')
    }

    function debitInputChange(e, creditInput) {
        let value = e.target.value
        value = p2e(value)
        e.target.value = parseInt(value) > 0 ? parseInt(value) : null
        if (value <= 0) e.target.value = null;
        else if (value > 0) creditInput.value = null
        updateSumCalculation()
    }

    function creditInputChange(e, debitInput) {
        let value = e.target.value
        value = p2e(value)
        e.target.value = parseInt(value) > 0 ? parseInt(value) : null
        if (value <= 0) e.target.value = null;
        else if (value > 0) debitInput.value = null
        updateSumCalculation();
    }

    function updateSumCalculation() {
        let debits = Array.from(document.getElementsByClassName('debitInput'))
        let credits = Array.from(document.getElementsByClassName('creditInput'))
        let sumDebit = 0;
        let sumCredit = 0;
        debits.map(i => i.value > 0 ? sumDebit += parseInt(i.value) : '')
        credits.map(i => i.value > 0 ? sumCredit += parseInt(i.value) : '')
        document.getElementById('creditSum').innerText = sumCredit
        document.getElementById('debitSum').innerText = sumDebit
    }

    updateSumCalculation()
    @isset($transaction->id)
        deactivateAllTransactionRow()
    @endisset
    var codeInputs = document.getElementById('transactions').getElementsByClassName('codeInput')
    var codeSelectBoxs = document.getElementById('transactions').getElementsByClassName('codeSelectBox')
    var removeButtons = document.getElementById('transactions').getElementsByClassName('removeTransaction')
    var debitInputs = document.getElementById('transactions').getElementsByClassName('debitInput')
    var creditInputs = document.getElementById('transactions').getElementsByClassName('creditInput')

    for (var i = 0; i < codeInputs.length; i++) {
        let codeInput = codeInputs[i];
        let codeSelectBox = codeSelectBoxs[i];
        let removeButton = removeButtons[i];
        let debitInput = debitInputs[i];
        let creditInput = creditInputs[i];
        codeInput.addEventListener('keyup', (e) => onCodeInputChange(e, codeSelectBox))
        codeSelectBox.addEventListener('change', (e) => onCodeSelectBoxChange(e, codeInput))
        removeButton.addEventListener('click', deleteAction)
        debitInput.addEventListener('keyup', (e) => debitInputChange(e, creditInput))
        creditInput.addEventListener('keyup', (e) => creditInputChange(e, debitInput))
    }

    function deactivateAllTransactionRow() {
        let transactionsDiv = document.getElementById('transactions');
        let transactionDivs = transactionsDiv.getElementsByClassName('transaction');
        // Array.from(transactionDivs).map(i => i.classList.add('deactivated-transaction-row'))
    }

    function updateTransactionCounter() {
        Array.from(document.getElementsByClassName('transaction-count')).map((element, index) => element.innerText =
            index + 1)
    }

    document.getElementById('addTransaction').addEventListener('click', function() {
        t = t + 1;
        var transactionsDiv = document.getElementById('transactions');
        var transactionDivs = transactionsDiv.getElementsByClassName('transaction');
        var lastTransactionDiv = transactionDivs[transactionDivs.length - 1];
        var newTransactionDiv = lastTransactionDiv.cloneNode(true);
        deactivateAllTransactionRow();
        // newTransactionDiv.classList.remove('deactivated-transaction-row');
        // Update the index in the name attribute
        var selects = newTransactionDiv.getElementsByTagName('select');
        for (var i = 0; i < selects.length; i++) {
            selects[i].name = selects[i].name.replace(/\[\d+\]/, '[' + transactionDivs.length + ']');
            selects[i].value = ''
        }

        var inputs = newTransactionDiv.getElementsByTagName('input');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].name = inputs[i].name.replace(/\[\d+\]/, '[' + transactionDivs.length + ']');
            inputs[i].value = ''
        }


        // Add the remove button event listener
        var removeButton = newTransactionDiv.getElementsByClassName('removeTransaction')[0];
        removeButton.addEventListener('click', deleteAction);

        newTransactionDiv.querySelector('.subject_id').setAttribute('onclick', 'openSelectBox(' + t + ')')

        newTransactionDiv.querySelector('.selfSelectBoxItems').setAttribute('onclick', 'fillInput(this, ' + t + ')')

        // Add code onchange event listener
        var codeInput = newTransactionDiv.getElementsByClassName('codeInput')[0];
        var codeSelectBox = newTransactionDiv.getElementsByClassName('codeSelectBox')[0];
        codeInput.addEventListener('keyup', (e) => onCodeInputChange(e, codeSelectBox));
        codeSelectBox.addEventListener('change', (e) => onCodeSelectBoxChange(e, codeInput));


        // Add code onchange event listener
        var debitInput = newTransactionDiv.getElementsByClassName('debitInput')[0];
        var creditInput = newTransactionDiv.getElementsByClassName('creditInput')[0];
        debitInput.addEventListener('keyup', (e) => debitInputChange(e, creditInput));
        creditInput.addEventListener('keyup', (e) => creditInputChange(e, debitInput));


        // Append the new transaction div to the transactions div
        transactionsDiv.appendChild(newTransactionDiv);
        updateTransactionCounter()
    });

    function openSelectBox(index) {
        document.querySelectorAll(".selfSelectBox")[index].style.display = "block";
    }

    function fillInput(t, index) {
        let selfItemTitle = t.querySelector(".selfItemTitle").innerText;
        let selfItemCode = t.querySelector(".selfItemCode").innerText;
        document.querySelectorAll(".subject_id")[index].value = selfItemTitle;
        document.querySelectorAll(".value")[index].value = selfItemCode;
    }

    document.addEventListener("click", function(event) {
        let isClickInside = event.target.closest(".selfSelectBoxContainer");

        if (!isClickInside) {
            document.querySelectorAll(".selfSelectBox").forEach(function(box) {
                box.style.display = "none";
            });
        }
    });
</script>
