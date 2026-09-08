<x-card class_body="p-4">
    <div class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-4 xl:flex xl:items-center xl:justify-start">
        @include('invoices.forms.warehouse-select', ['warehouseClass' => 'w-full xl:w-1/6'])
        <x-input id="invoice_type" name="invoice_type" value="beginning_inventory" hidden />
        <x-text-input input_value="{{ old('invoice_id') ?? ($invoice->id ?? '') }}" input_name="invoice_id"
            label_text_class="text-gray-500" label_class="w-full hidden"></x-text-input>

        <div>
            <x-text-input input_name="title" title="{{ __('Invoice Name') }}"
                input_value="{{ old('title') ?? ($invoice->title ?? '') }}" placeholder="{{ __('Invoice Name') }}"
                label_text_class="text-gray-500" label_class="w-full"></x-text-input>
        </div>
        <div>
            <x-text-input x-data="{ invoice_number: '{{ formatDocumentNumber($invoice?->number ?? $previousInvoiceNumber + 1) }}' }"
                title="{{ __('Current Invoice Number') }}" x-model.number="invoice_number" x-bind:name="'invoice_number'"
                placeholder="{{ __('Current Invoice Number') }}" label_text_class="text-gray-500 text-nowrap"
                label_class="w-full"
                x-on:input="invoice_number = $store.utils.convertToEnglish($event.target.value);"
                x-effect="$el.value = $store.utils.localizeNumber($store.utils.formatNumber(invoice_number));">
            </x-text-input>
        </div>
        <div>
            <x-text-input data-jdp title="{{ __('date') }}" input_name="date" placeholder="{{ __('date') }}" readonly
                input_value="{{ old('date') ?? convertToJalali($invoice->date ?? jalali_to_gregorian((int) (config('active-company-fiscal-year') ?? toEnglish(jdate('Y'))), 1, 1, '-'), true) }}"
                label_text_class="text-gray-500 text-nowrap" label_class="w-full"
                input_class="datePicker"></x-text-input>
        </div>
    </div>
</x-card>
<x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-4" x-data="transactionForm">
    <div class="max-w-full overflow-x-auto overflow-y-hidden">
        <div class="w-max min-w-full">
            <div class="flex w-max min-w-full gap-2 items-center px-4">
                <div class="text-sm flex-1 max-w-8 text-center text-gray-500 pt-3">*</div>
                <div class="text-sm flex-1 min-w-24 max-w-64 text-center text-gray-500 pt-3">
                    <div
                        class="text-sm flex-1 min-w-24 max-w-64 text-center text-gray-500 pt-3 flex items-center justify-center gap-2">
                        <div class="flex items-center gap-3 ml-1">
                            {{ __('Product') }}
                            <a href="{{ route('products.create') }}" target="_blank"
                                class="flex items-center gap-1 btn btn-xs btn-ghost text-blue-500 hover:text-blue-700 dark:text-sky-300 dark:hover:text-sky-200 dark:hover:bg-sky-500/10"
                                title="{{ __('Create Product') }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M21 7.5l-9-4.5-9 4.5m18 0l-9 4.5m9-4.5v9l-9 4.5m0-9L3 7.5m9 4.5v9m-9-13.5v9l9 4.5" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="text-sm flex-1 min-w-80 text-center text-gray-500 pt-3">{{ __('description') }}</div>
                <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">{{ __('Quantity') }}</div>
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
                        <label class="sr-only">{{ __('Products') }}</label>

                        @php
                            $hint =
                                '<a class="link text-blue-500 dark:text-sky-300" href="' .
                                route('products.create') .
                                '" target="_blank">' .
                                __('Create Product') .
                                '</a>';
                        @endphp

                        <x-select-box url="{{ route('invoices.search-product-service') }}" :options="[['headerGroup' => 'product', 'options' => $products]]"
                            x-model="selectedValue" x-init="selectedValue = initItemSelection(transaction)" placeholder="{{ __('Select Product') }}"
                            @selected="selectItem(transaction, $event.detail.type, $event.detail.id)"
                            hint='{!! $hint !!}' />

                        <x-input name="" x-bind:name="'transactions[' + index + '][product_id]'" x-bind:value="transaction.product_id || ''" hidden />
                        <x-input name="" x-bind:name="'transactions[' + index + '][service_id]'" x-bind:value="transaction.service_id || ''" hidden />
                        <x-input name="" x-bind:name="'transactions[' + index + '][item_id]'" x-bind:value="transaction.item_id || ''" hidden />
                    </div>
                     <div class="flex-1 w-[200px]">
                        <x-text-input x-bind:value="transaction.desc" placeholder="{{ __('description') }}"
                            x-bind:name="'transactions[' + index + '][desc]'" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white"
                            x-bind:disabled="!transaction.product_id">
                        </x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="{{ localizeNumber('0') }}" x-model.number="transaction.quantity"
                            x-bind:name="'transactions[' + index + '][quantity]'"
                            x-bind:disabled="!transaction.product_id" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white"
                            x-on:input="transaction.quantity = $store.utils.cleanupNumber($event.target.value).split('.')[0]"
                            x-effect="$el.value = $store.utils.localizeNumber(($store.utils.cleanupNumber(transaction.quantity).split('.')[0]) || '')">
                        </x-text-input>
                    </div>
                    <x-input name="" x-bind:name="'transactions[' + index + '][off]'" value="0" hidden />
                    <x-input name="" x-bind:name="'transactions[' + index + '][vat]'" value="0" hidden />

                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="{{ localizeNumber('0') }}" x-model.number="transaction.unit"
                            x-bind:name="'transactions[' + index + '][unit]'"
                            x-bind:disabled="!transaction.product_id" label_text_class="text-gray-500"
                            label_class="w-full" input_class="border-white"
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

                <button class="flex justify-content gap-4 align-center w-full px-4" id="addTransaction"
                    @click="addTransaction; activeTab = transactions.length;" type="button">
                    <div
                        class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600 border-none btn w-full rounded-md btn-active">
                        <span class="text-2xl">+</span>
                        {{ __('Add Transaction') }}
                    </div>
                </button>
            </div>
        </div>
    </div>
    <hr style="">
    <div class="flex flex-col justify-end md:flex-row">
        <div class="flex flex-col justify-end px-4 gap-4 py-3 rounded-b-2xl md:flex-row">
            <div
                class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 dark:border-slate-700 dark:shadow-none shadow-sm rounded-xl border border-gray-200">
                <span class="text-sm font-medium text-gray-500 dark:text-slate-300">{{ __('Total Quantity') }}:</span>
                <span class="text-sm md:text-md lg:text-lg font-bold text-indigo-600 dark:text-indigo-300"
                    x-text="$store.utils.localizeNumber($store.utils.cleanupNumber(String(transactions.reduce((sum, t) => sum + (Number($store.utils.convertToEnglish(t.quantity)) || 0), 0))))">{{ localizeNumber('0') }}</span>
            </div>

            <div
                class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 dark:border-slate-700 dark:shadow-none shadow-sm rounded-xl border border-gray-200">
                <span
                    class="text-sm font-medium text-gray-500 dark:text-slate-300">{{ __('Total Sum') }}({{ config('amir.currency') ?? __('Rial') }}):
                </span>
                <span class="text-sm md:text-md lg:text-lg font-bold text-green-600 dark:text-emerald-300"
                    x-text="$store.utils.localizeNumber((
                        transactions.reduce((sum, t) => sum + (Number($store.utils.convertToEnglish(t.total)) || 0), 0)
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

<div class="flex gap-2 justify-end">
    <a href="{{ route('invoices.index', ['invoice_type' => 'beginning_inventory']) }}" type="submit" class="btn btn-sm lg:btn-md btn-default rounded-md dark:bg-slate-700 dark:text-slate-100 dark:border-slate-600 dark:hover:bg-slate-600">{{ __('cancel') }}</a>
    @if ($invoice->exists)
        @can('invoices.destroy')
            <button type="submit" form="delete-beginning-inventory-form"
                class="btn btn-sm lg:btn-md btn-error rounded-md"
                onclick="return confirm('{{ __('Are you sure?') }}')">{{ __('Delete') }}</button>
        @endcan
    @endif
    <button id="submitForm" type="submit" class="btn btn-sm lg:btn-md text-white btn-primary rounded-md">{{ __('save') }}</button>
</div>

@pushOnce('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('transactionForm', () => ({
                transactions: {!! json_encode($transactions, JSON_UNESCAPED_UNICODE) !!},
                products: {!! json_encode($products, JSON_UNESCAPED_UNICODE) !!},
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
                        off: 0,
                        vat: 0,
                        desc: ''
                    });
                },
                getProductPrice(productId) {
                    const product = this.products.find(p => p.id == productId);
                    if (!product) return 0;
                    return product.average_cost;
                },
                getProductInventorySubjectId(productId) {
                    const product = this.products.find(p => p.id == productId);
                    if (!product) return null;
                    return product.inventory_subject_id;
                },
                initItemSelection(transaction) {
                    const id = transaction.product_id ?? null;
                    transaction.item_type = 'product';
                    transaction.item_id = id ? `product-${id}` : '';
                    return id ? `product-${id}` : '';
                },
                selectItem(transaction, type, id) {
                    transaction.product_id = id;
                    transaction.item_type = type;
                    transaction.item_id = `${type}-${id}`;
                    transaction.inventory_subject_id = this.getProductInventorySubjectId(id);
                    const isEditable = !this.isEditing || transaction.unit == null;
                    if (isEditable) {
                        transaction.unit = this.getProductPrice(id);
                        transaction.quantity = 1;
                    }
                    transaction.off = 0;
                    transaction.vat = 0;
                },
                calcTotal(t) {
                    const qty = Number(this.$store.utils.convertToEnglish(t.quantity)) || 0;
                    const unit = Number(this.$store.utils.convertToEnglish(t.unit)) || 0;
                    t.total = qty * unit;
                    return this.$store.utils.localizeNumber(t.total.toLocaleString());
                }
            }));
        });
    </script>
@endPushOnce
