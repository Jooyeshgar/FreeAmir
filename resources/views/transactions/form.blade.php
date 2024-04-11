
    <div id="transactions">
        <div class="transaction flex flex-wrap">
            <div class="w-full md:w-1/2 lg:w-1/3 p-3">
                <label for="subject_id" class="block text-gray-700 text-sm font-bold mb-2">Subject</label>
                <select name="transactions[0][subject_id]" id="subject_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" onchange="displaySubjectDetails(this)">
                    <option value="">Select a subject</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}"
                                data-title="{{ $subject->name }}"
                                data-type="{{ $subject->type }}"
                            {{ $transaction->subject_id == $subject->id ? 'selected' : '' }}>
                            {{ $subject->code }}/{{ $subject->name }}/{{ $subject->type }}
                        </option>
                    @endforeach

                </select>
            </div>
            <div class="w-full md:w-1/2 lg:w-1/3 p-3">
                <label for="user_id" class="block text-gray-700 text-sm font-bold mb-2">User</label>
                <select name="transactions[0][user_id]" id="user_id"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full md:w-1/2 lg:w-1/3 p-3">
                <label for="value" class="block text-gray-700 text-sm font-bold mb-2">Value</label>
                <input type="number" name="transactions[0][value]" id="value"
                       value="{{ $transaction->value }}"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="w-full p-3">
                <label for="desc" class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea name="transactions[0][desc]" id="desc"
                          class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">{{ $transaction->desc }}</textarea>
            </div>


            <button type="button" class=" bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded removeTransaction">Remove
            </button>
        </div>
    </div>

    <button type="button" id="addTransaction"
            class="my-5 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Add Transaction
    </button>

<script>
    document.getElementById('addTransaction').addEventListener('click', function() {
        var transactionsDiv = document.getElementById('transactions');
        var transactionDivs = transactionsDiv.getElementsByClassName('transaction');
        var lastTransactionDiv = transactionDivs[transactionDivs.length - 1];
        var newTransactionDiv = lastTransactionDiv.cloneNode(true);

        // Update the index in the name attribute
        var selects = newTransactionDiv.getElementsByTagName('select');
        for (var i = 0; i < selects.length; i++) {
            selects[i].name = selects[i].name.replace(/\[\d+\]/, '[' + transactionDivs.length + ']');
        }

        var inputs = newTransactionDiv.getElementsByTagName('input');
        for (var i = 0; i < inputs.length; i++) {
            inputs[i].name = inputs[i].name.replace(/\[\d+\]/, '[' + transactionDivs.length + ']');
        }

        var textareas = newTransactionDiv.getElementsByTagName('textarea');
        for (var i = 0; i < textareas.length; i++) {
            textareas[i].name = textareas[i].name.replace(/\[\d+\]/, '[' + transactionDivs.length + ']');
        }

        // Add the remove button event listener
        var removeButton = newTransactionDiv.getElementsByClassName('removeTransaction')[0];
        removeButton.addEventListener('click', function() {
            this.parentNode.remove();
        });

        // Append the new transaction div to the transactions div
        transactionsDiv.appendChild(newTransactionDiv);
    });


</script>
