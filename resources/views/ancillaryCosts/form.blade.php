<div x-data="ancillaryCostForm">
    <x-card class="rounded-2xl w-full" class_body="p-4">
        <div class="flex gap-2 items-center justify-start">
            <div class="flex w-1/4">
                @php
                    $initialCustomerId = old('customer_id', $ancillaryCost->customer_id ?? null);
                    $initialSelectedValue = $initialCustomerId ? "customer-$initialCustomerId" : null;
                    $hint = '<a class="link text-blue-500 hover:underline" href="' .route('customers.create') .'">' . __('Add Customer') . '</a>';
                @endphp

                <div class="flex flex-wrap w-3/4" x-data="{
                        customer_id: '{{ $initialCustomerId }}',
                        selectedValue: '{{ $initialSelectedValue }}',
                    }">
                    <span class="text-gray-500">{{ __('Customer') }}
                        <a href="{{ route('customers.create') }}"
                            class="btn btn-xs btn-ghost text-blue-500 hover:text-blue-700" title="{{ __('Add Customer') }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M12 14.25c-2.485 0-4.5 1.79-4.5 4s2.015 4 4.5 4 4.5-1.79 4.5-4-2.015-4-4.5-4zm0-2.25a3 3 0 100-6 3 3 0 000 6zm6-1.5h3m-1.5-1.5v3" />
                            </svg>
                        </a>
                    </span>

                    <x-select-box url="{{ route('ancillary-costs.search-customer') }}" :options="[['headerGroup' => 'customer', 'options' => $customers]]" x-model="selectedValue"
                        x-init="if (!selectedValue && customer_id) {
                            selectedValue = 'customer-' + customer_id;
                        }" placeholder="{{ __('Select Customer') }}" hint='{!! $hint !!}'
                        @selected="customer_id = $event.detail.id;" class="" />
                    <input type="hidden" x-bind:value="customer_id" name="customer_id">
                </div>
            </div>
            <div class="flex w-1/8">
                <div class="flex flex-wrap w-full">
                    @php
                        $initialInvoiceId = old('invoice_id', $ancillaryCost->invoice_id ?? null);
                        $initialSelectedValue = $initialInvoiceId ? "invoice-$initialInvoiceId" : null;
                    @endphp
                    <div class="flex flex-wrap w-full" x-data="{
                            invoice_id: '{{ $initialInvoiceId }}',
                            selectedValue: '{{ $initialSelectedValue }}',
                        }">
                        <span class="text-gray-500">{{ __('Invoice') }}</span>

                        <x-select-box url="{{ route('ancillary-costs.search-invoice') }}" :options="[['headerGroup' => 'invoice', 'options' => $invoices]]" x-model="selectedValue"
                            x-init="if (!selectedValue && invoice_id) {
                                selectedValue = 'invoice-' + invoice_id;
                            }" placeholder="{{ __('Select Invoice') }}"
                            @selected="invoice_id = $event.detail.id; loadInvoiceProducts(invoice_id);" class="" />
                        <input type="hidden" x-bind:value="invoice_id" name="invoice_id">
                    </div>
                </div>
            </div>
            <div class="flex w-1/4">
                <div class="flex flex-wrap">
                    <span class="flex flex-col flex-wrap text-gray-500 w-full"> {{ __('Cost Type') }} </span>
                    <select name="type" id="type" x-model="selectedCostType"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
                            focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-3 py-2">
                        <option value="">{{ __('Select Cost Type') }}</option>
                        @foreach (App\Enums\AncillaryCostType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex w-1/8">
                <x-text-input placeholder="0" title="{{ __('VAT') }} (%)" input_name="vat" x-model="vat"
                    input_value="{{ old('vat') ?? $ancillaryCost->vat }}" label_text_class="text-gray-500"
                    label_class="w-full" input_class="border-gray-300"></x-text-input>
            </div>
            <div class="flex w-1/8">
                <x-text-input data-jdp class="w-3/4" title="{{ __('date') }}" input_name="date"
                    placeholder="{{ __('date') }}" input_value="{{ old('date') ?? convertToJalali($ancillaryCost->date ?? now()) }}"
                    label_text_class="text-gray-500 text-nowrap" input_class="datePicker w-full"></x-text-input>
            </div>
    </x-card>

    <x-card class="mt-4 rounded-2xl w-full" class_body="p-4">
        <div class="flex overflow-x-auto overflow-y-hidden gap-2 items-center px-4 pb-2">
            <div class="text-sm flex-1 max-w-8 text-center text-gray-500">
                #
            </div>
            <div class="text-sm flex-1 min-w-48 text-center text-gray-500">
                {{ __('Product') }}
            </div>
            <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500">
                {{ __('Quantity') }}
            </div>
            <div class="text-sm flex-1 min-w-32 max-w-48 text-center text-gray-500">
                {{ __('Amount per unit') }}
            </div>
            <div class="text-sm flex-1 min-w-32 max-w-48 text-center text-gray-500">
                {{ __('Amount') }}
            </div>
        </div>
        <hr>
        <div class="max-h-96 overflow-y-auto">
            <template x-if="!availableProducts || availableProducts.length === 0">
                <div class="text-center py-8 text-gray-500">
                    <p>{{ __('Please select an invoice to see its products') }}</p>
                </div>
            </template>

            <template x-if="availableProducts && availableProducts.length > 0">
                <div>
                    <template x-for="(product, index) in availableProducts" :key="product.id">
                        <div class="flex gap-2 items-center px-4 py-3 border-b hover:bg-gray-50">
                            <div class="flex-1 text-center max-w-8">
                                <span class="text-gray-600" x-text="index + 1"></span>
                            </div>

                            <div class="flex-1 min-w-48 text-center">
                                <span class="text-gray-800" x-text="product.name"></span>
                                <input type="hidden" x-bind:name="'ancillaryCosts[' + index + '][product_id]'"
                                    x-bind:value="product.id">
                                <input type="hidden" x-bind:name="'ancillaryCosts[' + index + '][description]'"
                                    x-bind:value="selectedCostType">
                            </div>

                            <div class="flex-1 min-w-32 max-w-32">
                                <input type="text" x-bind:value="product.quantity ?? 0" readonly
                                    class="mt-1 block w-full rounded-md border-gray-200 bg-gray-100 text-gray-700 px-3 py-2 text-center" />
                            </div>

                            <div class="flex-1 min-w-32 max-w-48">
                                <input type="text"
                                    x-bind:value="calculateAmountPerUnit(product.id, product.quantity)" readonly
                                    class="mt-1 block w-full rounded-md border-gray-200 bg-gray-100 text-gray-700 px-3 py-2 text-center" />
                            </div>

                            <div class="flex-1 min-w-32 max-w-48">
                                <x-text-input placeholder="0" ::value="productAmounts[product.id] || 0"
                                    x-bind:name="'ancillaryCosts[' + index + '][amount]'"
                                    x-bind:disabled="!selectedCostType" label_text_class="text-gray-500"
                                    label_class="w-full" input_class="border-gray-300"
                                    x-on:input="updateProductAmount(product.id, $event.target.value)">
                                </x-text-input>
                            </div>

                    </template>
                </div>
            </template>
        </div>
        <hr>
        <div class="flex flex-row justify-end">
            <div class="flex justify-end px-4 gap-4 py-3">
                <div class="flex items-center gap-2 px-4 py-2 bg-white shadow-sm rounded-xl border border-gray-200">
                    <span class="text-sm font-medium text-gray-500">{{ __('Total') }} ({{ config('amir.currency') ?? __('Rial') }}):</span>
                    <span class="text-lg font-bold text-green-600" x-text="calculateTotal().toLocaleString('fa-IR')">
                        0
                    </span>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 bg-white shadow-sm rounded-xl border border-gray-200">
                    <span class="text-sm font-medium text-gray-500">{{ __('Total with VAT') }} ({{ config('amir.currency') ?? __('Rial') }}):</span>
                    <span class="text-lg font-bold text-green-600"
                        x-text="calculateTotalWithVat(Number(vat)).toLocaleString('fa-IR')">
                        0
                    </span>
                </div>
            </div>
        </div>
    </x-card>
</div>

<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ route('ancillary-costs.index') }}" type="submit" class="btn btn-default rounded-md">
        {{ __('cancel') }}
    </a>
    <button id="submitForm" type="submit" class="btn text-white btn-primary rounded-md">
        {{ __('save') }}
    </button>
    @can('ancillary-costs.approve')
        <button id="submitFormAndApprove" type="submit" name="approve" value="1"
            class="btn text-white btn-primary rounded-md">
            {{ __('save and approve') }} </button>
    @endcan
</div>

@pushOnce('scripts')
    <script type="module">
        jalaliDatepicker.startWatch();
    </script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('ancillaryCostForm', () => ({
                availableProducts: [],
                productAmounts: {},
                selectedInvoiceId: {{ old('invoice_id') ?? ($ancillaryCost->invoice_id ?? 'null') }},
                selectedCostType: '{{ old('type') ?? ($ancillaryCost->type ?? '') }}',
                selectedCustomerId: {{ old('customer_id') ?? ($ancillaryCost->customer_id ?? 'null') }},
                vat: '{{ old('vat') ?? ($ancillaryCost->vat ?? 0) }}',

                init() {
                    // If editing and invoice_id exists, load products
                    if (this.selectedInvoiceId) {
                        this.loadInvoiceProducts(this.selectedInvoiceId);
                    }

                    // Load existing amounts if editing
                    const existingCosts = {!! json_encode($ancillaryCostItems ?? [], JSON_UNESCAPED_UNICODE) !!};
                    if (existingCosts && existingCosts.length > 0) {
                        existingCosts.forEach(cost => {
                            if (cost.product_id && cost.amount) {
                                this.productAmounts[cost.product_id] = cost.amount;
                            }
                        });
                    }
                },

                updateProductAmount(productId, value) {
                    const numValue = this.$store.utils.convertToEnglish(value);
                    this.productAmounts[productId] = numValue;
                },

                calculateTotal() {
                    return Object.values(this.productAmounts).reduce((sum, amount) => {
                        return sum + (Number(this.$store.utils.convertToEnglish(amount)) || 0);
                    }, 0);
                },

                calculateTotalWithVat(vat) {
                    const total = this.calculateTotal();
                    const vatPercent = Number(this.$store.utils.convertToEnglish(vat)) || 0;

                    return total + (total * vatPercent / 100);
                },

                calculateAmountPerUnit(productId, quantity) {
                    const productQuantity = Number(this.$store.utils.convertToEnglish(quantity ?? 0)) ||
                        0;
                    if (!productQuantity) return Number(this.$store.utils.convertToEnglish(0));
                    const amount = Number(this.$store.utils.convertToEnglish(this.productAmounts[
                        productId] ?? 0)) || 0;
                    const perUnit = amount / productQuantity;
                    return perUnit.toLocaleString('fa-IR', {
                        maximumFractionDigits: 2
                    });
                },

                loadInvoiceProducts(invoiceId) {
                    if (!invoiceId) {
                        this.availableProducts = [];
                        this.productAmounts = {};
                        return;
                    }

                    fetch(`/ancillary-costs/get-products/${invoiceId}`)
                        .then(response => response.json())
                        .then(data => {
                            this.availableProducts = data.products;
                            // Initialize amounts for new products
                            data.products.forEach(product => {
                                if (!(product.id in this.productAmounts)) {
                                    this.productAmounts[product.id] = 0;
                                }
                            });
                        })
                        .catch(error => {
                            console.error('Error loading products:', error);
                            this.availableProducts = [];
                            this.productAmounts = {};
                        });
                }
            }));
        });
    </script>
@endPushOnce
