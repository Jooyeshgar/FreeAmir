<style>
    label:nth-child(n + 6){
        display: none !important;
    }
</style>
<hr>
<div id="transactions">
    <div class="transaction flex flex-wrap items-center justify-center">
        <div class="flex-1 min-w-42 p-3">
            <label for="subject_id" class="block text-gray-700 text-sm font-bold mb-2">code</label>
            <input type="text" name="transactions[0][code]" id="value"
                   value="{{ $transaction->subject?$transaction->subject->code:'' }}"
                   class="codeInput shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">

        </div>
        <div class="flex-1 min-w-42 p-3">
            <label for="subject_id" class="block text-gray-700 text-sm font-bold mb-2">Subject</label>
            <select name="transactions[0][subject_id]" id="subject_id"
                    class="codeSelectBox shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <option value="">Select a subject</option>
                @foreach($subjects as $subject)
                    <option {{$subject->parent_id?'':'disabled'}} value="{{ $subject->id }}"
                            data-title="{{ $subject->name }}"
                            data-type="{{ $subject->type }}"
                        {{ $transaction->subject_id == $subject->id ? 'selected' : '' }}>
                        {{ $subject->name }}  {{$subject->type=='both'?'':('- ('.$subject->type.')')}}
                    </option>
                @endforeach

            </select>
        </div>
        <div class="flex-1 min-w-42 p-3">
            <label for="desc" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
            <input name="transactions[0][desc]" id="desc"
                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                   value="{{ $transaction->desc }}">
        </div>

        <div class="flex-1 min-w-42 p-3">
            <label for="debit" class="block text-gray-700 text-sm font-bold mb-2">Debit</label>
            <input type="text" name="transactions[0][debit]" id="debit"
                   value="{{ $transaction->debit }}"
                   class="debitInput shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div class="flex-1 min-w-42 p-3">
            <label for="credit" class="block text-gray-700 text-sm font-bold mb-2">Credit</label>
            <input type="text" name="transactions[0][credit]" id="credit"
                   value="{{ $transaction->credit }}"
                   class="creditInput shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
        </div>
        <div class="flex-1 min-w-42 p-3">
            <label for="value" class="block text-gray-700 text-sm font-bold mb-2">action</label>

            <button type="button" style="line-height: 0;padding: 20px 0px"
                    class=" w-full btn-sm  bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded removeTransaction text-center">
                Remove
            </button>
        </div>
    </div>
</div>
<hr>

<div class="flex justify-content gap-4 align-center">
    <button type="button" id="addTransaction"
                                                       class=" flex-1 my-5 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Add Transaction
    </button>
    <button type="button" id="creditSum"
            class="my-5 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">0
    </button>
    <button type="button" id="debitSum"
            class="my-5 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">0
    </button>
</div>

<script>
    var subjects = {!! json_encode($subjects) !!};
    p2e = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d))

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
        }
    }

    function debitInputChange(e, creditInput) {
        let value = e.target.value
        value = p2e(value)
        e.target.value = parseInt(value) > 0 ? parseInt(value) : null
        if (value <= 0) e.target.value = null; else if (value > 0) creditInput.value = null
        updateSumCalculation()
    }

    function creditInputChange(e, debitInput) {
        let value = e.target.value
        value = p2e(value)
        e.target.value = parseInt(value) > 0 ? parseInt(value) : null
        if (value <= 0) e.target.value = null; else if (value > 0) debitInput.value = null
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


    document.getElementById('addTransaction').addEventListener('click', function () {
        var transactionsDiv = document.getElementById('transactions');
        var transactionDivs = transactionsDiv.getElementsByClassName('transaction');
        var lastTransactionDiv = transactionDivs[transactionDivs.length - 1];
        var newTransactionDiv = lastTransactionDiv.cloneNode(true);
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
    });


</script>
