<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex gap-2 items-center justify-start">
        @php
            $initialReturnedInvoiceId = old(
                'returned_invoice_id',
                $invoice->returned_invoice_id ?? ($prefilledReturnedInvoiceId ?? null),
            );
            $initialSelectedValue = $initialReturnedInvoiceId ? "invoice-$initialReturnedInvoiceId" : null;
            $disableReturnedInvoiceSelect = $invoice->exists || ($lockReturnedInvoiceSelection ?? false);
        @endphp
        <div class="flex w-1/3">
            <div class="flex-wrap w-full" x-data="{
                returnedInvoiceId: '{{ $initialReturnedInvoiceId }}',
                invoiceCustomers: @js(collect($returnInvoices)->pluck('customer_id', 'id')),
                selectedValue: '{{ $initialSelectedValue }}',
            }">
                <span class="text-gray-500">{{ __('Returned Invoice') }}</span>
                <x-select-box url="{{ route('invoices.search', ['invoice_type' => 'return_buy']) }}" :options="[['headerGroup' => 'invoice', 'options' => $returnInvoices]]"
                    x-model="selectedValue" x-init="if (!selectedValue && returnedInvoiceId) {
                        selectedValue = 'invoice-' + returnedInvoiceId;
                    }
                    if (returnedInvoiceId) {
                        window.dispatchEvent(new CustomEvent('return-invoice-selected', { detail: { invoiceId: returnedInvoiceId } }));
                        const customerId = invoiceCustomers[returnedInvoiceId] ?? null;
                        if (customerId) {
                            window.__returnInvoiceCustomerId = customerId;
                            window.dispatchEvent(new CustomEvent('return-invoice-customer-selected', { detail: { customerId } }));
                        }
                    }" placeholder="{{ __('Select Invoice') }}"
                    @selected="
                        returnedInvoiceId = $event.detail.id;
                        window.dispatchEvent(new CustomEvent('return-invoice-selected', { detail: { invoiceId: returnedInvoiceId } }));
                        const customerId = $event.detail.customer_id ?? invoiceCustomers[returnedInvoiceId] ?? null;
                        if (customerId) {
                            window.__returnInvoiceCustomerId = customerId;
                            window.dispatchEvent(new CustomEvent('return-invoice-customer-selected', { detail: { customerId } }));
                        }
                    "
                    :disabled="$disableReturnedInvoiceSelect" />
                <x-input name="returned_invoice_id" x-bind:value="returnedInvoiceId" hidden />
            </div>
        </div>
        <x-input id="invoice_type" name="invoice_type" value="return_buy" hidden />
        <div class="flex w-1/3">
            <x-text-input input_name="title" title="{{ __('Invoice Name') }}"
                input_value="{{ old('title') ?? ($invoice->title ?? '') }}" placeholder="{{ __('Invoice Name') }}"
                label_text_class="text-gray-500" label_class="w-1/2"></x-text-input>
        </div>

        <div class="flex w-1/4">
            @php
                $matchedReturnedInvoice = $initialReturnedInvoiceId
                    ? collect($returnInvoices)->firstWhere('id', (int) $initialReturnedInvoiceId)
                    : null;
                $prefilledCustomerId = $matchedReturnedInvoice['customer_id'] ?? null;
                $initialCustomerId = old('customer_id', $invoice->customer_id ?? $prefilledCustomerId);
                $initialSelectedValue = $initialCustomerId ? "customer-$initialCustomerId" : null;
            @endphp

            <div class="flex flex-wrap w-3/4" x-data="{
                customer_id: '{{ $initialCustomerId }}',
                selectedValue: '{{ $initialSelectedValue }}',
                customerSelectRender: true,
                refreshCustomerSelect() {
                    this.customerSelectRender = false;
                    this.$nextTick(() => {
                        this.customerSelectRender = true;
                    });
                }
            }"
                @return-invoice-customer-selected.window="
                if ($event.detail?.customerId) {
                    customer_id = $event.detail.customerId;
                    selectedValue = 'customer-' + customer_id;
                    refreshCustomerSelect();
                }
            ">
                <span class="flex flex-wrap text-gray-500 w-full">{{ __('Customer') }}</span>

                <template x-if="customerSelectRender">
                    <x-select-box url="{{ route('invoices.search-customer') }}" :options="[['headerGroup' => 'customer', 'options' => $customers]]"
                        x-model="selectedValue" x-init="if (!selectedValue && customer_id) {
                            selectedValue = 'customer-' + customer_id;
                        }" placeholder="{{ __('Select Customer') }}"
                        @selected="customer_id = $event.detail.id;" :disabled="true" />
                </template>

                <x-input x-bind:value="customer_id" name="customer_id" hidden />
            </div>
        </div>
    </div>

    <div class="flex justify-start gap-2 mt-2">
        <x-text-input input_value="{{ old('invoice_id') ?? ($invoice->id ?? '') }}" input_name="invoice_id"
            label_text_class="text-gray-500" label_class="w-full hidden"></x-text-input>
        @if (!$invoice->exists)
            <x-text-input disabled="true" input_value="{{ formatDocumentNumber($previousInvoiceNumber) }}"
                title="{{ __('Previous Invoice Number') }}" placeholder="{{ __('Previous Invoice Number') }}"
                label_text_class="text-gray-500 text-nowrap"></x-text-input>
        @endif

        <x-text-input x-data="{ invoice_number: '{{ formatDocumentNumber($invoice?->number ?? $previousInvoiceNumber + 1) }}' }"
            title="{{ __('Current Invoice Number') }}" x-model.number="invoice_number" x-bind:name="'invoice_number'"
            placeholder="{{ __('Current Invoice Number') }}" label_text_class="text-gray-500 text-nowrap"
            x-on:input="invoice_number = $store.utils.convertToEnglish($event.target.value);"
            x-effect="$el.value = $store.utils.localizeNumber($store.utils.formatNumber(invoice_number));">
        </x-text-input>
 
        <x-text-input x-data="{ document_number: '{{ formatDocumentNumber($invoice->document?->number ?? $previousDocumentNumber + 1) }}' }"
            title="{{ __('current document number') }}" x-model.number="document_number" x-bind:name="'document_number'"
            placeholder="{{ __('current document number') }}" label_text_class="text-gray-500 text-nowrap"
            x-on:input="document_number = $store.utils.convertToEnglish($event.target.value);"
            x-effect="$el.value = $store.utils.localizeNumber($store.utils.formatNumber(document_number));">
        </x-text-input>

        <x-text-input data-jdp title="{{ __('date') }}" input_name="date" placeholder="{{ __('date') }}" readonly
            input_value="{{ old('date') ?? convertToJalali($invoice->date ?? now(), true) }}"
            label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
    </div>
</x-card>
<x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-4" x-data="transactionForm">
    <div class="flex flex-wrap overflow-x-auto overflow-y-hidden gap-2 items-center px-4">
        <div class="text-sm flex-1 max-w-8 text-center text-gray-500 pt-3">*</div>
        <div class="text-sm flex-1 min-w-24 max-w-64 text-center text-gray-500 pt-3">
            <div
                class="text-sm flex-1 min-w-24 max-w-64 text-center text-gray-500 pt-3 flex items-center justify-center gap-2">
                <div class="flex items-center gap-3 ml-1">
                    @if (!$isReturnServiceBuy)
                        {{ __('Product') }}
                    @else
                        {{ __('Services') }}
                    @endif
                </div>
            </div>
        </div>
        <div class="text-sm flex-1 min-w-80 text-center text-gray-500 pt-3">{{ __('description') }}</div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">{{ __('Quantity') }}</div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">{{ __('OFF') }}</div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">{{ $invoice->exists ? __('VAT') : __('VAT') . ' (%)' }}</div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">{{ __('Unit') }}</div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">{{ __('Total') }}</div>
    </div>
    <div class="min-h-96">
        <div id="transactions" x-data="{ activeTab: {{ $total }} }">
            <template x-for="(transaction, index) in transactions" :key="transaction.id">
                <div :class="{ 'active': activeTab === index }" class="transaction flex gap-2 items-center px-4 pb-3"
                    @click="activeTab = index">
                    <div class="relative flex-1 text-center max-w-8 pt-2 pb-2 transaction-count-container">
                        <span class="transaction-count block"
                            x-text="$store.utils.localizeNumber(String(index + 1))"></span>
                        <button @click.stop="transactions.splice(index, 1)" type="button"
                            class="absolute left-0 top-0 removeButton">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor"
                                class="px-2 size-8 rounded-md h-10 flex justify-center items-center text-center bg-red-500 hover:bg-red-700 dark:bg-red-500/80 dark:hover:bg-red-500 text-white font-bold removeTransaction">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 min-w-24 max-w-64">
                        <label class="sr-only">{{ __('Product/Service') }}</label>

                        @php
                            $options = [
                                [
                                    'headerGroup' => 'product',
                                    'options' => $isReturnServiceBuy ? [] : $products,
                                ],
                                [
                                    'headerGroup' => 'service',
                                    'options' => $isReturnServiceBuy ? $services : [],
                                ],
                            ];
                        @endphp

                        <x-select-box url="{{ route('invoices.search-product-service') }}" :options="$options"
                            x-model="selectedValue" x-init="selectedValue = initItemSelection(transaction)"
                            placeholder="{{ $isReturnServiceBuy ? __('Select Service') : __('Select Product') }}"
                            :disabled="true" @selected="selectItem(transaction, $event.detail.type, $event.detail.id)"
                            />

                        <x-input name="" x-bind:name="'transactions[' + index + '][product_id]'" x-bind:value="transaction.product_id || ''" hidden />
                        <x-input name="" x-bind:name="'transactions[' + index + '][service_id]'" x-bind:value="transaction.service_id || ''" hidden />
                        <x-input name="" x-bind:name="'transactions[' + index + '][item_id]'" x-bind:value="transaction.item_id || ''" hidden />
                    </div>
                    <div class="flex-1 w-[200px]">
                        <x-text-input x-bind:value="transaction.desc" placeholder="{{ __('description') }}"
                            x-bind:name="'transactions[' + index + '][desc]'" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white"
                            x-bind:disabled="!transaction.product_id && !transaction.service_id">
                        </x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="{{ localizeNumber('0') }}" x-model.number="transaction.quantity"
                            x-bind:name="'transactions[' + index + '][quantity]'"
                            x-bind:disabled="(!transaction.product_id && !transaction.service_id)"
                            x-bind:readonly="{{ $isReturnServiceBuy ? 'true' : 'false' }}"
                            x-bind:class="{{ $isReturnServiceBuy ? '\'bg-base-200 opacity-60 cursor-not-allowed\'' : '\'\''}}"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white"
                            x-on:input="transaction.quantity = $store.utils.cleanupNumber($event.target.value).split('.')[0]"
                            x-effect="$el.value = $store.utils.localizeNumber(($store.utils.cleanupNumber(transaction.quantity).split('.')[0]) || '')">
                        </x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="{{ localizeNumber('0') }}" x-model.number="transaction.off"
                            x-bind:name="'transactions[' + index + '][off]'"
                            x-bind:disabled="!transaction.product_id && !transaction.service_id"
                            x-bind:readonly="true" x-bind:class="'bg-base-200 opacity-60 cursor-not-allowed'"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white"
                            x-on:input="transaction.off = $store.utils.convertToEnglish($event.target.value)"
                            x-effect="$el.value = $store.utils.localizeNumber($store.utils.formatNumber(transaction.off))">
                        </x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="{{ localizeNumber('0') }}" x-model.number="transaction.vat"
                            x-bind:name="'transactions[' + index + '][vat]'"
                            x-bind:disabled="!transaction.product_id && !transaction.service_id"
                            x-bind:readonly="true" x-bind:class="'bg-base-200 opacity-60 cursor-not-allowed'"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white"
                            x-on:input="transaction.vat = $store.utils.convertToEnglish($event.target.value)"
                            x-effect="$el.value = $store.utils.localizeNumber($store.utils.formatNumber(transaction.vat))">
                        </x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="{{ localizeNumber('0') }}" x-model.number="transaction.unit"
                            x-bind:name="'transactions[' + index + '][unit]'"
                            x-bind:disabled="!transaction.product_id && !transaction.service_id"
                            x-bind:readonly="true" x-bind:class="'bg-base-200 opacity-60 cursor-not-allowed'"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white"
                            x-on:input="transaction.unit = $store.utils.convertToEnglish($event.target.value)"
                            x-effect="$el.value = $store.utils.localizeNumber($store.utils.formatNumber(transaction.unit))">
                        </x-text-input>
                    </div>
                    <div class="flex-1 min-w-32 max-w-32">
                        <x-text-input x-bind:value="calcTotal(transaction)"
                            x-bind:name="'transactions[' + index + '][total]'"
                            placeholder="{{ localizeNumber('0') }}" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white" readonly>
                        </x-text-input>
                    </div>
                </div>
            </template>
        </div>
    </div>
    <hr style="">
    <div class="flex flex-row justify-between" x-data="{ subtractionsInput: '{{ old('subtraction') ?? ($invoice->subtraction ?? 0) }}' }">
        <div class="flex justify-start px-4 gap-4 py-3 rounded-b-2xl">
            <x-text-input placeholder="{{ localizeNumber('0') }}" label_text_class="text-gray-500"
                label_class="w-full" input_name="subtraction" title="{{ __('Subtractions') }}"
                input_value="{{ old('subtraction') ?? ($invoice->subtraction ?? 0) }}" input_class="locale-number"
                x-model="subtractionsInput"
                @input="subtractionsInput = $store.utils.cleanupNumber($event.target.value)"
                x-effect="$el.value = $store.utils.localizeNumber($store.utils.formatNumber(subtractionsInput))">
            </x-text-input>
        </div>
        <div class="flex justify-end px-4 gap-4 py-3 rounded-b-2xl">
            <div
                class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 dark:border-slate-700 dark:shadow-none shadow-sm rounded-xl border border-gray-200">
                <span class="text-sm font-medium text-gray-500 dark:text-slate-300">{{ __('Total Quantity') }}:</span>
                <span class="text-lg font-bold text-indigo-600 dark:text-indigo-300"
                    x-text="$store.utils.localizeNumber($store.utils.cleanupNumber(String(transactions.reduce((sum, t) => sum + (Number($store.utils.convertToEnglish(t.quantity)) || 0), 0))))">{{ localizeNumber('0') }}</span>
            </div>

            <div
                class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 dark:border-slate-700 dark:shadow-none shadow-sm rounded-xl border border-gray-200">
                <span class="text-sm font-medium text-gray-500 dark:text-slate-300">{{ __('Total Sum') }}
                    ({{ config('amir.currency') ?? __('Rial') }}): </span>
                <span class="text-lg font-bold text-green-600 dark:text-emerald-300"
                    x-text="$store.utils.localizeNumber((
                        transactions.reduce((sum, t) => sum + (Number($store.utils.convertToEnglish(t.total)) || 0), 0)
                        - (Number($store.utils.cleanupNumber(subtractionsInput) || 0))
                    ).toLocaleString())">
                    {{ localizeNumber('0') }}
                </span>
            </div>
        </div>
    </div>

</x-card>

<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex justify-center gap-2 mt-2">
        <x-textarea name="description" id="description" title="{{ __('description') }}" :value="old('description', $invoice->description ?? '')" />
    </div>
</x-card>

<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ route('invoices.index', ['invoice_type' => 'return_buy']) }}" type="submit"
        class="btn btn-default rounded-md dark:bg-slate-700 dark:text-slate-100 dark:border-slate-600 dark:hover:bg-slate-600">{{ __('cancel') }}</a>
    <button id="submitForm" type="submit" class="btn text-white btn-primary rounded-md">{{ __('save') }}
    </button>

    @can('invoices.approve')
        <button id="submitFormAndApprove" type="submit" name="approve" value="1"
            class="btn text-white btn-primary rounded-md">{{ __('save and approve') }}</button>
    @endcan
</div>

@pushOnce('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('transactionForm', () => ({
                transactions: {!! json_encode($transactions, JSON_UNESCAPED_UNICODE) !!},
                products: {!! json_encode($products, JSON_UNESCAPED_UNICODE) !!},
                services: {!! json_encode($services, JSON_UNESCAPED_UNICODE) !!},
                isEditing: {{ $invoice->exists ? 'true' : 'false' }},
                selectedReturnInvoiceId: {!! json_encode(
                    old('returned_invoice_id', $invoice->returned_invoice_id ?? ($prefilledReturnedInvoiceId ?? null)),
                    JSON_UNESCAPED_UNICODE,
                ) !!},
                init() {
                    window.addEventListener('return-invoice-selected', (event) => {
                        const invoiceId = event?.detail?.invoiceId;
                        this.selectedReturnInvoiceId = invoiceId;

                        if (!this.isEditing) {
                            this.loadInvoiceItems(invoiceId);
                        }
                    });

                    if (this.selectedReturnInvoiceId && !this.isEditing) {
                        this.loadInvoiceItems(this.selectedReturnInvoiceId);
                    }
                },
                addTransaction() {
                    const newId = this.transactions.length ? this.transactions[this.transactions
                        .length - 1].id + 1 : 1;
                    this.transactions.push({
                        id: newId,
                        name: '',
                        subject: '',
                        inventory_subject_id: '',
                        service_id: null,
                        product_id: null,
                        quantity: 1,
                        unit: null,
                        total: 0,
                        vat: null,
                        desc: ''
                    });
                },
                loadInvoiceItems(invoiceId) {
                    if (!invoiceId) {
                        this.transactions = [];
                        return;
                    }

                    fetch(`/invoices/get-items/${invoiceId}`)
                        .then(response => response.json())
                        .then(data => {
                            this.transactions = data.map(item => ({
                                id: item.id,
                                product_id: item.product_id,
                                service_id: item.service_id,
                                quantity: item.quantity,
                                unit: item.unit,
                                total: item.total,
                                vat: item.vat,
                                desc: item.desc,
                                inventory_subject_id: item.inventory_subject_id,
                                item_type: item.product_id ? 'product' : (item
                                    .service_id ? 'service' : null),
                                item_id: item.product_id ?
                                    `product-${item.product_id}` : (item.service_id ?
                                        `service-${item.service_id}` : '')
                            }));
                        })
                        .catch(error => {
                            console.error('Error loading invoice items:', error);
                            this.transactions = [];
                        });
                },
                getProductPrice(productId) {
                    const product = this.products.find(p => p.id == productId);
                    if (!product) return 0;
                    return product.average_cost;
                },
                getServicePrice(serviceId) {
                    const service = this.services.find(s => s.id == serviceId);
                    if (!service) return 0;
                    return service.selling_price || 0;
                },
                getProductVat(productId) {
                    const product = this.products.find(p => p.id == productId);
                    const productGroup = product.productGroup;

                    if (!product || !productGroup) return 0;
                    if (product.vat == null) {
                        return productGroup.vat;
                    }
                    return product.vat;
                },
                getServiceVat(serviceId) {
                    const service = this.services.find(s => s.id == serviceId);
                    const serviceGroup = service.serviceGroup;

                    if (!service) return 0;
                    if (service.vat !== null && service.vat !== undefined) {
                        return service.vat;
                    }
                    if (serviceGroup && serviceGroup.vat !== null) {
                        return serviceGroup.vat;
                    }
                    return 0;
                },
                calcVatValue(rate, qty, unit, off) {
                    const subtotal = Number(qty) * Number(unit);
                    return ((subtotal - Number(off)) * Number(rate)) / 100;
                },
                getProductInventorySubjectId(productId) {
                    const product = this.products.find(p => p.id == productId);
                    if (!product) return null;
                    return product.inventory_subject_id;
                },
                getServiceSubjectId(serviceId) {
                    const service = this.services.find(s => s.id == serviceId);
                    if (!service) return null;
                    return service.subject_id;
                },
                initItemSelection(transaction) {
                    const id = transaction.product_id ?? transaction.service_id ?? null;
                    const type = transaction.product_id ? 'product' : (transaction.service_id ?
                        'service' : null);
                    transaction.item_type = type;
                    transaction.item_id = id ? `${type}-${id}` : '';
                    return id ? `${type}-${id}` : '';
                },
                selectItem(transaction, type, id) {
                    const isProduct = type === 'product';
                    transaction.product_id = isProduct ? id : null;
                    transaction.service_id = isProduct ? null : id;
                    transaction.item_type = type;
                    transaction.item_id = `${type}-${id}`;
                    transaction.inventory_subject_id = isProduct ? this.getProductInventorySubjectId(
                        id) : null;

                    const isEditable = !this.isEditing || transaction.unit == null || transaction.vat ==
                        null;
                    if (isEditable) {
                        transaction.unit = isProduct ? this.getProductPrice(id) : this.getServicePrice(
                            id);
                        transaction.quantity = 1;
                        transaction.off = 0;
                        const vatRate = isProduct ? this.getProductVat(id) : this.getServiceVat(id);
                        transaction.vat = this.isEditing ? this.calcVatValue(vatRate, transaction
                            .quantity, transaction.unit, transaction.off) : vatRate;
                    }
                },
                calcTotal(t) {
                    const qty = Number(this.$store.utils.convertToEnglish(t.quantity)) || 0;
                    const unit = Number(this.$store.utils.convertToEnglish(t.unit)) || 0;
                    const off = Number(this.$store.utils.convertToEnglish(t.off)) || 0;
                    const vat = Number(this.$store.utils.convertToEnglish(t.vat)) || 0;

                    const subtotal = qty * unit;
                    const vatIsValue = this.isEditing;
                    t.total = vatIsValue ? subtotal - off + vat : subtotal + ((subtotal - off) * vat /
                        100) - off;
                    return this.$store.utils.localizeNumber(t.total.toLocaleString());
                }
            }));
        });
    </script>
@endPushOnce
