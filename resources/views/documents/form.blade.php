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
    <div class="h-96 overflow-y-auto px-4">
        <div id="transactions">
            @foreach ($transactions as $i => $transaction)
                <div class="transaction flex gap-2 items-center ">

                    <x-text-input value="{{ $transaction->id ?? '' }}"
                        name="transactions[{{ $i }}][transaction_id]" label_text_class="text-gray-500"
                        label_class="w-full hidden"></x-text-input>

                    <div class="flex-1 text-center max-w-8 pb-3 transaction-count-container">
                        <span class="transaction-count">1</span>

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor"
                            class="px-2 size-8 rounded-md  h-10 flex justify-center items-center text-center bg-red-500 hover:bg-red-700 text-white font-bold removeTransaction">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>

                    </div>
                    <div class="flex-1 min-w-24 max-w-24 pb-3">

                        <x-text-input value="{{ $transaction->subject ? $transaction->subject->formattedCode() : '' }}"
                            id="value" name="transactions[{{ $i }}][code]"
                            label_text_class="text-gray-500" label_class="w-full"
                            input_class="value codeInput "></x-text-input>

                    </div>
                    <x-subject-select-box :subjects="$subjects" :name="'transactions[' . $i . '][subject_id]'" :value="$transaction->subject_id ?? ''"></x-subject-select-box>
                    <div class="flex-1 w-[200px] pb-3">
                        <x-text-input value="{{ $transaction->desc }}"
                            placeholder="{{ __('this document\'s row description') }}" id="desc"
                            name="transactions[{{ $i }}][desc]" label_text_class="text-gray-500"
                            label_class="w-full" input_class=""></x-text-input>

                    </div>

                    <div class="flex-1 min-w-24 max-w-32 pb-3">
                        <x-text-input value="{{ $transaction->debit }}" placeholder="0" id="debit"
                            name="transactions[{{ $i }}][debit]" label_text_class="text-gray-500"
                            label_class="w-full" input_class="debitInput"></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32 pb-3">
                        <x-text-input value="{{ $transaction->credit }}" placeholder="0" id="credit"
                            name="transactions[{{ $i }}][credit]" label_text_class="text-gray-500"
                            label_class="w-full" input_class="creditInput"></x-text-input>

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
    var p2e = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))
    var codeInputs = document.getElementById('transactions').getElementsByClassName('codeInput')
    var codeSelectBoxs = document.getElementById('transactions').getElementsByClassName('codeSelectBox')
    var removeButtons = document.getElementById('transactions').getElementsByClassName('removeTransaction')
    var debitInputs = document.getElementById('transactions').getElementsByClassName('debitInput')
    var creditInputs = document.getElementById('transactions').getElementsByClassName('creditInput')
    const csrf = document.querySelector('meta[name="csrf_token"]').getAttribute('content')
    let searchInputs = document.querySelectorAll('.searchInput')
    let resultDivs = document.querySelectorAll(".resultDiv")
    let searchResultDivs = document.querySelectorAll(".searchResultDiv")

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
        Array.from(transactionDivs).map(i => i.classList.add('deactivated-transaction-row'))
    }

    function updateTransactionCounter() {
        Array.from(document.getElementsByClassName('transaction-count')).map((element, index) => element
            .innerText = index + 1)
    }

    document.getElementById('addTransaction').addEventListener('click', function() {
        var transactionsDiv = document.getElementById('transactions');
        var transactionDivs = transactionsDiv.getElementsByClassName('transaction');
        var lastTransactionDiv = transactionDivs[transactionDivs.length - 1];
        var newTransactionDiv = lastTransactionDiv.cloneNode(true);
        var transactionCount = newTransactionDiv.querySelector('.transaction-count').innerText
        var selfSelectBoxItems = newTransactionDiv.querySelectorAll('.selfSelectBoxItems')
        selfSelectBoxItems.forEach(selfSelectBoxItem => {
            selfSelectBoxItem.setAttribute('onclick', `fillInput(this, '${transactionCount}')`)
        })

        deactivateAllTransactionRow();
        newTransactionDiv.classList.remove('deactivated-transaction-row');
        // Update the index in the name attribute
        var selects = newTransactionDiv.getElementsByTagName('select');
        for (var i = 0; i < selects.length; i++) {
            selects[i].name = selects[i].name.replace(/\[\d+\]/, '[' + transactionDivs.length +
                ']');
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
        countInputs()
    });

    function openSelectBox(thisOne) {
        document.querySelectorAll(".selfSelectBox").forEach(function(box) {
            box.style.display = "none";
        });
        thisOne.querySelector(".selfSelectBox").style.display = "block";
    }

    function fillInput(thisOne, index) {
        let selfItemTitle = thisOne.querySelector(".selfItemTitle").innerText;
        let selfItemCode = thisOne.querySelector(".selfItemCode").innerText;
        let selfItemId = thisOne.querySelector(".selfItemId").innerText;
        document.querySelectorAll(".subject_name")[index].value = selfItemTitle;
        document.querySelectorAll(".subject_id")[index].value = selfItemId;
        document.querySelectorAll(".value")[index].value = selfItemCode;
    }

    document.addEventListener("click", function(event) {
        let isClickInside = event.target.closest(".selfSelectBoxContainer");

        if (!isClickInside) {
            document.querySelectorAll(".selfSelectBox").forEach(function(box) {
                box.style.display = "none";
                resultDivs.forEach((resultDiv, i) => {
                    searchInputs[i].value = ''
                    resultDiv.style.display = "block"
                    searchResultDivs[i].style.display = "none"
                })
            });
        }
    });

    function countInputs() {
        searchInputs = document.querySelectorAll('.searchInput')
        resultDivs = document.querySelectorAll(".resultDiv")
        searchResultDivs = document.querySelectorAll(".searchResultDiv")
        voidInputSearch()
    }

    function voidInputSearch() {
        searchInputs.forEach((searchInput, i) => {
            searchInput.addEventListener('input', (event) => debouncedSearch(event, i))
        })
    }

    function debounce(func, delay) {
        let timeout
        return function(...args) {
            clearTimeout(timeout)
            timeout = setTimeout(() => {
                func.apply(this, args)
            }, delay);
        }
    }

    function searchQuery(query, i) {
        fetch("/subjects/search", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrf
                },
                body: JSON.stringify({
                    query
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error("خطا در دریافت پاسخ")
                }
                return response.json()
            })
            .then(data => {
                resultDivs[i].style.display = "none"
                searchResultDivs[i].style.display = "block"
                if (data.length == 0) {
                    searchResultDivs[i].innerHTML = '<span class="block text-center">چیزی پیدا نشد!</span>'
                } else {
                    data.forEach(element => {
                        let targetName = element.name
                        let targetCode = element.code
                        let counter = 0
                        if (element.sub_subjects.length == 0) {
                            let createElement = `
                        <div class="w-full ps-2 mb-4">
                            <div class="flex justify-between">
                                <span>
                                    ${targetName}
                                </span>

                                <span>
                                    ${targetCode}
                                </span>
                            </div>
                        </div>
                        `
                            searchResultDivs[i].innerHTML = createElement
                        } else {
                            let subs = element.sub_subjects
                            let createElement = `
                        <div class="w-full ps-2 mb-4">
                            <div class="flex justify-between">
                                <span>
                                    ${targetName}
                                </span>

                                <span>
                                    ${targetCode}
                                </span>
                            </div>
                        </div>
                        <div class="ps-1 mt-4">
                            <div class="border-s-[1px] ps-7 border-[#ADB5BD]" id="sub-${counter}"></div>
                        </div>
                        `
                            searchResultDivs[i].innerHTML = createElement

                            subs.forEach(sub => {
                                let subContainer = document.getElementById(`sub-${counter}`)
                                let createElement = `
                                    <a href="javascript:void(0)"
                                        class="selfSelectBoxItems flex justify-between mb-4"
                                        onclick="fillInput(this, '${counter}')">
                                        <span class="selfItemTitle">
                                            ${sub.name}
                                        </span>
                                        <span class="selfItemCode">
                                            ${sub.code}
                                        </span>
                                        <span class="selfItemId hidden">${sub.id}</span>
                                    </a>
                                    `
                                subContainer.innerHTML += createElement
                            })
                            counter += 1
                        }
                    });
                }
            })
            .catch(error => {
                console.error("خطایی رخ داده: ", error)
            })
    }

    const debouncedSearch = debounce(function(event, i) {
        const query = event.target.value
        if (query) {
            searchQuery(query, i)
        } else {
            resultDivs[i].style.display = "block"
            searchResultDivs[i].style.display = "none"
        }
    }, 500)
    voidInputSearch()
</script>
