<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex gap-2 items-center justify-start">
        <div class="flex w-1/4">

            @php
                $initialCustomerId = old('customer_id', $invoice->customer_id ?? null);
                $initialSelectedValue = $initialCustomerId ? "customer-$initialCustomerId" : null;
                $hint =
                    '<a class="link text-blue-500 hover:underline" href="' .
                    route('customers.create') .
                    '">' .
                    __('Add Customer') .
                    '</a>';
            @endphp

            <div class="flex flex-wrap w-3/4" x-data="{
                customer_id: '{{ $initialCustomerId }}',
                selectedValue: '{{ $initialSelectedValue }}',
            }">
                <span class="flex flex-wrap text-gray-500 w-full">{{ __('Customer') }}
                    <a href="{{ route('customers.create') }}"
                        class="btn btn-xs btn-ghost text-blue-500 hover:text-blue-700" title="{{ __('Add Customer') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M12 14.25c-2.485 0-4.5 1.79-4.5 4s2.015 4 4.5 4 4.5-1.79 4.5-4-2.015-4-4.5-4zm0-2.25a3 3 0 100-6 3 3 0 000 6zm6-1.5h3m-1.5-1.5v3" />
                        </svg>
                    </a>
                </span>

                <x-select-box url="{{ route('invoices.search-customer') }}" :options="[['headerGroup' => 'customer', 'options' => $customers]]" x-model="selectedValue"
                    x-init="if (!selectedValue && customer_id) {
                        selectedValue = 'customer-' + customer_id;
                    }" placeholder="{{ __('Select Customer') }}" hint='{!! $hint !!}'
                    @selected="customer_id = $event.detail.id;" class="" />

                <input type="hidden" x-bind:value="customer_id" name="customer_id">
            </div>
        </div>
        <input type="hidden" id="invoice_type" name="invoice_type"
            value="{{ $invoice->invoice_type ?? $invoice_type }}">
        <div class="flex w-1/3">
            <x-text-input input_name="title" title="{{ __('Invoice Name') }}"
                input_value="{{ old('title') ?? ($invoice->title ?? '') }}" placeholder="{{ __('Invoice Name') }}"
                label_text_class="text-gray-500" label_class="w-1/2"></x-text-input>
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

        <x-text-input
            input_value="{{ old('invoice_number') ?? formatDocumentNumber($invoice->number ?? $previousInvoiceNumber + 1) }}"
            input_name="invoice_number" title="{{ __('Current Invoice Number') }}"
            placeholder="{{ __('Current Invoice Number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>

        <x-text-input
            input_value="{{ old('document_number') ?? formatDocumentNumber($invoice->document?->number ?? $previousDocumentNumber + 1) }}"
            input_name="document_number" title="{{ __('current document number') }}"
            placeholder="{{ __('current document number') }}"
            label_text_class="text-gray-500 text-nowrap"></x-text-input>

        <x-text-input data-jdp title="{{ __('date') }}" input_name="date" placeholder="{{ __('date') }}"
            input_value="{{ old('date') ?? convertToJalali($invoice->date ?? now()) }}"
            label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
    </div>
</x-card>
<x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-4" x-data="transactionForm">
    <div class="flex flex-wrap overflow-x-auto overflow-y-hidden gap-2 items-center px-4">
        <div class="text-sm flex-1 max-w-8 text-center text-gray-500 pt-3">
            *
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-64 text-center text-gray-500 pt-3">
            <div
                class="text-sm flex-1 min-w-24 max-w-64 text-center text-gray-500 pt-3 
            flex items-center justify-center gap-2">
                <div class="flex items-center gap-3 ml-1">
                    @if (!$isServiceBuy)
                        {{ __('Product') }}
                        <a href="{{ route('products.create') }}"
                            class="flex items-center gap-1 btn btn-xs btn-ghost text-blue-500 hover:text-blue-700"
                            title="{{ __('Create Product') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M21 7.5l-9-4.5-9 4.5m18 0l-9 4.5m9-4.5v9l-9 4.5m0-9L3 7.5m9 4.5v9m-9-13.5v9l9 4.5" />
                            </svg>
                        </a>
                    @else
                        {{ __('Services') }}
                        <a href="{{ route('services.create') }}"
                            class="flex items-center gap-1 btn btn-xs btn-ghost text-blue-500 hover:text-blue-700"
                            title="{{ __('Create Service') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M15.232 5.232a4 4 0 015.536 5.536l-7.768 7.768a4 4 0 01-5.536-5.536l1.232-1.232" />
                            </svg>
                        </a>
                    @endif

                    @if (($invoice->invoice_type ?? $invoice_type) === App\Enums\InvoiceType::SELL || ($invoice_type ?? null) === 'sell')
                        {{ __('Services') }}
                        <a href="{{ route('services.create') }}"
                            class="flex items-center gap-1 btn btn-xs btn-ghost text-blue-500 hover:text-blue-700"
                            title="{{ __('Create Service') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M15.232 5.232a4 4 0 015.536 5.536l-7.768 7.768a4 4 0 01-5.536-5.536l1.232-1.232" />
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
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
                <div :class="{ 'active': activeTab === index }" class="transaction flex gap-2 items-center px-4 pb-3"
                    @click="activeTab = index">
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

                    <div class="flex-1 min-w-24 max-w-64">
                        <label class="sr-only">{{ __('Product/Service') }}</label>

                        @php
                            $isSellType =
                                $invoice->invoice_type == App\Enums\InvoiceType::SELL || $invoice_type == 'sell';
                            $options = [
                                [
                                    'headerGroup' => 'product',
                                    'options' => $isServiceBuy ? [] : $products,
                                ],
                                [
                                    'headerGroup' => 'service',
                                    'options' => $isSellType || $isServiceBuy ? $services : [],
                                ],
                            ];
                            $hint = !$isServiceBuy
                                ? '<a class="link text-blue-500" href="' .
                                    route('products.create') .
                                    '">' .
                                    __('Create Product') .
                                    '</a>'
                                : '<a class="link text-blue-500" href="' .
                                    route('services.create') .
                                    '">' .
                                    __('Create Service') .
                                    '</a>';
                            $hint2 = $isSellType
                                ? '<a class="link text-blue-500" href="' .
                                    route('services.create') .
                                    '">' .
                                    __('Create Service') .
                                    '</a>'
                                : '';

                            $placeholder = $isSellType ? __('Select Product/Service') : __('Select Product');
                            $placeholder = $isServiceBuy ? __('Select Service') : $placeholder;
                        @endphp

                        <x-select-box url="{{ route('invoices.search-product-service') }}" :options="$options"
                            x-model="selectedValue" x-init="selectedValue = initItemSelection(transaction)" placeholder="{{ $placeholder }}"
                            @selected="selectItem(transaction, $event.detail.type, $event.detail.id)"
                            hint='{!! $hint !!}' hint2='{!! $hint2 !!}' />

                        <input type="hidden" x-bind:value="transaction.product_id || ''"
                            x-bind:name="'transactions[' + index + '][product_id]'">
                        <input type="hidden" x-bind:value="transaction.service_id || ''"
                            x-bind:name="'transactions[' + index + '][service_id]'">
                        <input type="hidden" x-bind:value="transaction.item_id || ''"
                            x-bind:name="'transactions[' + index + '][item_id]'">
                    </div>
                    <div class="flex-1 w-[200px]">
                        <x-text-input x-bind:value="transaction.desc" placeholder="{{ __('description') }}"
                            x-bind:name="'transactions[' + index + '][desc]'" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white"
                            x-bind:disabled="!transaction.product_id && !transaction.service_id"></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.quantity"
                            x-bind:name="'transactions[' + index + '][quantity]'"
                            x-bind:disabled="(!transaction.product_id && !transaction.service_id) || transaction.service_id"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white">
                        </x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.off"
                            x-bind:name="'transactions[' + index + '][off]'"
                            x-bind:disabled="!transaction.product_id && !transaction.service_id"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white"
                            x-on:input="transaction.off = $store.utils.convertToEnglish($event.target.value); $event.target.value = $store.utils.formatNumber(transaction.off)">
                        </x-text-input>
                    </div>

                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.vat"
                            x-bind:name="'transactions[' + index + '][vat]'"
                            x-bind:disabled="!transaction.product_id && !transaction.service_id"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white">
                        </x-text-input>
                    </div>

                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="transaction.unit"
                            x-bind:name="'transactions[' + index + '][unit]'"
                            x-bind:disabled="!transaction.product_id && !transaction.service_id"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white"
                            x-on:input="transaction.unit = $store.utils.convertToEnglish($event.target.value); $event.target.value = $store.utils.formatNumber(transaction.unit)">
                        </x-text-input>
                    </div>

                    <div class="flex-1 min-w-32 max-w-32">
                        <x-text-input x-bind:value="calcTotal(transaction)"
                            x-bind:name="'transactions[' + index + '][total]'" placeholder="0"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white"
                            readonly>
                        </x-text-input>
                    </div>
                </div>
            </template>
        </div>

        <button class="flex justify-content gap-4 align-center w-full px-4" id="addTransaction"
            @click="addTransaction; activeTab = transactions.length;" type="button">
            <div class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active">
                <span class="text-2xl">+</span>
                {{ __('Add Transaction') }}
            </div>
        </button>
    </div>
    <hr style="">
    <div class="flex flex-row justify-between" x-data="{ subtractionsInput: '{{ old('subtraction') ?? ($invoice->subtraction ?? 0) }}' }">
        <div class="flex justify-start px-4 gap-4 py-3 rounded-b-2xl">
            <x-text-input placeholder="0" label_text_class="text-gray-500" label_class="w-full"
                input_name="subtraction" title="{{ __('Subtractions') }}"
                input_value="{{ old('subtraction') ?? ($invoice->subtraction ?? 0) }}" input_class="locale-number"
                x-model="subtractionsInput"
                @input="$event.target.value = $store.utils.formatNumber($event.target.value)">
            </x-text-input>
        </div>
        <div class="flex justify-end px-4 gap-4 py-3 rounded-b-2xl">
            <!-- Quantity Sum -->
            <div class="flex items-center gap-2 px-4 py-2 bg-white shadow-sm rounded-xl border border-gray-200">
                <span class="text-sm font-medium text-gray-500">{{ __('Total Quantity') }}:</span>
                <span class="text-lg font-bold text-indigo-600"
                    x-text="transactions.reduce((sum, t) => sum + (Number(t.quantity) || 0), 0)">
                    0
                </span>
            </div>

            <!-- Total Sum -->
            <div class="flex items-center gap-2 px-4 py-2 bg-white shadow-sm rounded-xl border border-gray-200">
                <span class="text-sm font-medium text-gray-500">{{ __('Total Sum') }}
                    ({{ config('amir.currency') ?? __('Rial') }}): </span>
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
        <x-textarea name="description" id="description" title="{{ __('description') }}" :value="old('description', $invoice->description ?? '')" />
    </div>
</x-card>

<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ route('invoices.index', ['invoice_type' => $invoice->invoice_type ?? $invoice_type]) }}"
        type="submit" class="btn btn-default rounded-md">
        {{ __('cancel') }}
    </a>
    <button id="submitForm" type="submit" class="btn text-white btn-primary rounded-md">
        {{ __('save') }} </button>

    @php
        $resolvedInvoiceType = $invoice->invoice_type ?? $invoice_type;
        $isSellType = $resolvedInvoiceType == App\Enums\InvoiceType::SELL || $resolvedInvoiceType == 'sell';
        $approveLabel = $isSellType ? __('save and ready to approve') : __('save and approve');
    @endphp
    @can('invoices.approve')
        <button id="submitFormAndApprove" type="submit" name="approve" value="1" class="btn text-white btn-primary rounded-md">
            {{ $approveLabel }}
        </button>
    @endcan
</div>

@pushOnce('scripts')
    <script type="module">
        jalaliDatepicker.startWatch();
    </script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('transactionForm', () => ({
                transactions: {!! json_encode($transactions, JSON_UNESCAPED_UNICODE) !!},
                products: {!! json_encode($products, JSON_UNESCAPED_UNICODE) !!},
                services: {!! json_encode($services, JSON_UNESCAPED_UNICODE) !!},
                invoice_type: {!! json_encode($invoice->invoice_type ?? $invoice_type, JSON_UNESCAPED_UNICODE) !!},
                isEditing: {{ $invoice->exists ? 'true' : 'false' }},
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
                getProductPrice(productId) {
                    const product = this.products.find(p => p.id == productId);
                    if (!product) return 0;
                    return (this.invoice_type == 'sell') ? product.selling_price : product
                        .average_cost;
                },
                getServicePrice(serviceId) {
                    const service = this.services.find(s => s.id == serviceId);
                    if (!service) return 0;
                    return service.selling_price || 0;
                },
                getProductVat(productId) {
                    const product = this.products.find(p => p.id == productId);
                    const productGroup = product.product_group;

                    if (!product || !productGroup) return 0;
                    if (product.vat == null) {
                        return productGroup.vat;
                    }
                    return product.vat;
                },
                getServiceVat(serviceId) {
                    const service = this.services.find(s => s.id == serviceId);
                    const serviceGroup = service.service_group;

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
                    transaction.inventory_subject_id = isProduct ? this.getProductInventorySubjectId(id) : null;

                    const isEditable = !this.isEditing || transaction.unit == null || transaction.vat ==
                        null;
                    if (isEditable) {
                        transaction.unit = isProduct ? this.getProductPrice(id) : this.getServicePrice(id);
                        transaction.quantity = 1;
                        transaction.off = 0;
                        const vatRate = isProduct ? this.getProductVat(id) : this.getServiceVat(id);
                        transaction.vat = this.isEditing ? this.calcVatValue(vatRate, transaction.quantity, transaction.unit, transaction.off) : vatRate;
                    }
                },
                calcTotal(t) {
                    const qty = Number(this.$store.utils.convertToEnglish(t.quantity)) || 0;
                    const unit = Number(this.$store.utils.convertToEnglish(t.unit)) || 0;
                    const off = Number(this.$store.utils.convertToEnglish(t.off)) || 0;
                    const vat = Number(this.$store.utils.convertToEnglish(t.vat)) || 0;

                    const subtotal = qty * unit;
                    t.total = this.isEditing ? subtotal - off + vat : subtotal + ((subtotal - off) * vat / 100) - off;
                    return t.total.toLocaleString();
                }
            }));
        });
    </script>
@endPushOnce
