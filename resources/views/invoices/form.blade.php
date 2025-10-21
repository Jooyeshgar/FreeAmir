<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex gap-2 items-center justify-start" x-data="{
        selectedCustomerId: {{ old('customer_id', $invoice->customer_id ?? '') ?: 'null' }},
    }">
        <div class="flex w-1/4">
            <div class="flex flex-wrap">
                <span class="flex flex-col flex-wrap text-gray-500 w-full"> {{ __('Customer') }} </span>
                <select name="customer_id" id="customer_id" x-model="selectedCustomerId"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-3 py-2">
                    <option value="">{{ __('Select Customer') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" x-bind:selected="selectedCustomerId == {{ $invoice->customer_id }}">
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <input type="hidden" id="invoice_type" name="invoice_type" value="{{ $invoice_type }}">
        <div class="flex w-1/3">
            <x-text-input input_name="title" title="{{ __('Invoice Name') }}" 
                input_value="{{ old('title') ?? ($invoice->document->title ?? '') }}" 
                placeholder="{{ __('Invoice Name') }}"
                label_text_class="text-gray-500" label_class="w-1/2"></x-text-input>
        </div>
    </div>

    <div class="flex justify-start gap-2 mt-2">
        <x-text-input input_value="" input_name="invoice_id" label_text_class="text-gray-500" label_class="w-full hidden"></x-text-input>
        @if (!$invoice->exists)
            <x-text-input disabled="true" input_value="{{ formatDocumentNumber($previousInvoiceNumber) }}"
                title="{{ __('Previous Invoice Number') }}"
                placeholder="{{ __('Previous Invoice Number') }}" label_text_class="text-gray-500 text-nowrap"></x-text-input>
        @endif
        
        <x-text-input 
            input_value="{{ old('invoice_number') ?? formatDocumentNumber($invoice->number ?? ($previousInvoiceNumber + 1)) }}" 
            input_name="invoice_number"
            title="{{ __('Current Invoice Number') }}" 
            placeholder="{{ __('Current Invoice Number') }}" 
            label_text_class="text-gray-500 text-nowrap"></x-text-input>

        <x-text-input 
            input_value="{{ old('document_number') ?? formatDocumentNumber($invoice->document->number ?? ($previousDocumentNumber + 1)) }}" 
            input_name="document_number"
            title="{{ __('current document number') }}" 
            placeholder="{{ __('current document number') }}" 
            label_text_class="text-gray-500 text-nowrap"></x-text-input>

        <x-text-input data-jdp title="{{ __('date') }}" input_name="date" placeholder="{{ __('date') }}"
            input_value="{{ old('date') ?? convertToJalali($invoice->date ?? now()) }}" 
            label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
    </div>
</x-card>
<x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-4" x-data="transactionForm">
    <div class="flex overflow-x-auto overflow-y-hidden gap-2 items-center px-4">
        <div class="text-sm flex-1 max-w-8 text-center text-gray-500 pt-3">
            *
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-64 text-center text-gray-500 pt-3">
            {{ __('Product name') }}
        </div>
        <div class="text-sm flex-1 min-w-80 text-center text-gray-500 pt-3">
            {{ __('description') }}
        </div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">
            {{ __('Quantity') }}
        </div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">
            {{ __('OFF') }}
        </div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">
            {{ __('VAT') }}
        </div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">
            {{ __('Unit') }}
        </div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">
            {{ __('Total') }}
        </div>
    </div>
    <div class="h-96 overflow-y-auto">
        <div id="transactions" x-data="{ activeTab: {{ $total }} }">
            <template x-for="(transaction, index) in transactions" :key="transaction.id">
                <div :class="{ 'active': activeTab === index }" class="transaction flex gap-2 items-center px-4 pb-3" @click="activeTab = index" x-data="{
                    selectedId: transaction.product_id || null,
                    off: 0,
                }"
                    x-effect="
                        if (selectedId && !transaction.unit) {
                        transaction.unit = getProductPrice(Number(selectedId));
                    }">
                    <input type="text" x-bind:value="getProductSubjectId(selectedId)" x-bind:name="'transactions[' + index + '][subject_id]'" hidden>

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

                    <div class="flex-1 min-w-24 max-w-64">
                        <label class="sr-only">{{ __('Product') }}</label>
                        <select x-model="selectedId"
                            @change="
                                transaction.product_id = Number($event.target.value);
                                transaction.subject_id = getProductSubjectId(Number($event.target.value));
                                transaction.unit = getProductPrice(Number($event.target.value));
                                transaction.vat = getProductVat(Number($event.target.value));
                                transaction.off = 0;
                            "
                            x-bind:name="'transactions[' + index + '][product_id]'"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-3 py-2">
                            <option value="">{{ __('Select Product') }}</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 w-[200px]">
                        <x-text-input x-bind:value="transaction.desc" placeholder="{{ __('description') }}" x-bind:name="'transactions[' + index + '][desc]'"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white" x-bind:disabled="!selectedId"></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.quantity" x-bind:name="'transactions[' + index + '][quantity]'"
                            x-bind:disabled="!selectedId" label_text_class="text-gray-500" label_class="w-full" input_class="border-white">
                        </x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.off" x-bind:name="'transactions[' + index + '][off]'" x-bind:disabled="!selectedId"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white"
                            x-on:input="transaction.off = $store.utils.convertToEnglish($event.target.value); $event.target.value = $store.utils.formatNumber(transaction.off)">
                        </x-text-input>
                    </div>

                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.vat" x-bind:name="'transactions[' + index + '][vat]'"
                            x-bind:disabled="!selectedId" label_text_class="text-gray-500" label_class="w-full" input_class="border-white">
                        </x-text-input>
                    </div>

                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.unit" x-bind:name="'transactions[' + index + '][unit]'"
                            x-bind:disabled="!selectedId" label_text_class="text-gray-500" label_class="w-full" input_class="border-white"
                            x-on:input="transaction.unit = $store.utils.convertToEnglish($event.target.value); $event.target.value = $store.utils.formatNumber(transaction.unit)">
                        </x-text-input>
                    </div>

                    <div class="flex-1 min-w-32 max-w-32">
                        <x-text-input
                            x-bind:value="(transaction.total = (Number($store.utils.convertToEnglish(transaction.quantity)) || 0) *
                                (Number($store.utils.convertToEnglish(transaction.unit)) || 0) +
                                ((Number($store.utils.convertToEnglish(transaction.quantity)) || 0) *
                                    (Number($store.utils.convertToEnglish(transaction.unit)) || 0) *
                                    (Number($store.utils.convertToEnglish(transaction.vat)) / 100)) -
                                (Number($store.utils.convertToEnglish(transaction.off)) || 0)).toLocaleString()"
                            x-bind:name="'transactions[' + index + '][total]'" placeholder="0" label_text_class="text-gray-500" label_class="w-full"
                            input_class="border-white" readonly>
                        </x-text-input>
                    </div>
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
    <div class="flex flex-row justify-between" x-data="{ subtractionsInput: '{{ old('subtraction') ?? ($invoice->subtraction ?? 0) }}' }">
        <div class="flex justify-start px-4 gap-4 py-3 rounded-b-2xl">
            <x-text-input 
                placeholder="0" 
                label_text_class="text-gray-500" 
                label_class="w-full" 
                input_name="subtraction" 
                title="{{ __('Subtractions') }}"
                input_value="{{ old('subtraction') ?? ($invoice->subtraction ?? 0) }}"
                input_class="locale-number" 
                x-model="subtractionsInput" 
                @input="$event.target.value = $store.utils.formatNumber($event.target.value)">
            </x-text-input>
        </div>
        <div class="flex justify-end px-4 gap-4 py-3 rounded-b-2xl">
            <!-- Quantity Sum -->
            <div class="flex items-center gap-2 px-4 py-2 bg-white shadow-sm rounded-xl border border-gray-200">
                <span class="text-sm font-medium text-gray-500">{{ __('Total Quantity') }}:</span>
                <span class="text-lg font-bold text-indigo-600" x-text="transactions.reduce((sum, t) => sum + (Number(t.quantity) || 0), 0)">
                    0
                </span>
            </div>

            <!-- Total Sum -->
            <div class="flex items-center gap-2 px-4 py-2 bg-white shadow-sm rounded-xl border border-gray-200">
                <span class="text-sm font-medium text-gray-500">{{ __('Total Sum') }}:</span>
                <span class="text-lg font-bold text-green-600"
                    x-text="(
                        transactions.reduce((sum, t) => sum + (Number($store.utils.convertToEnglish(t.total)) || 0), 0)
                        - (Number($store.utils.cleanupNumber(subtractionsInput) || 0))
                    ).toLocaleString()">
                    0
                </span>
            </div>
        </div>
    </div>

</x-card>

<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex justify-center gap-2 mt-2">
        <x-textarea name="description" id="description" title="{{ __('description') }}" 
            :value="old('description', $invoice->description ?? '')" />
    </div>
</x-card>

<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ route('invoices.index', ['invoice_type' => $invoice_type]) }}" type="submit" class="btn btn-default rounded-md"> {{ __('cancel') }}
    </a>
    <button id="submitForm" type="submit" class="btn text-white btn-primary rounded-md">
        {{ __('save and close form') }} </button>
</div>

@pushOnce('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('transactionForm', () => ({
                transactions: {!! json_encode($transactions, JSON_UNESCAPED_UNICODE) !!},
                products: {!! json_encode($products, JSON_UNESCAPED_UNICODE) !!},
                productGroups: {!! json_encode($productGroups, JSON_UNESCAPED_UNICODE) !!},
                invoice_type: {!! json_encode($invoice_type, JSON_UNESCAPED_UNICODE) !!},
                addTransaction() {
                    const newId = this.transactions.length ? this.transactions[this.transactions
                        .length - 1].id + 1 : 1;
                    this.transactions.push({
                        id: newId,
                        name: '',
                        subject: '',
                        subject_id: '',
                        quantity: 0,
                        unit: 0,
                        total: 0,
                        vat: 0,
                        desc: ''
                    });
                },
                getProductPrice(productId) {
                    const product = this.products.find(p => p.id == productId);
                    if (!product) return 0;
                    return (this.invoice_type == 'sell') ? product.selling_price : product.purchace_price;
                },
                getProductVat(productId) {
                    const product = this.products.find(p => p.id == productId);
                    const productGroup = this.productGroups.find(pg => pg.id == product.group);
                    if (!product || !productGroup) return 0;
                    if (product.vat == null) {
                        return productGroup.vat;
                    }
                    return product.vat;
                },
                getProductSubjectId(productId) {
                    const product = this.products.find(p => p.id == productId);
                    if (!product) return null;
                    return product.subject_id;
                }
            }));
        });
    </script>
@endPushOnce