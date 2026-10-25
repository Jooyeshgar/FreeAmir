<div x-data="ancillaryCostForm">
    <x-card class="rounded-2xl w-full" class_body="p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-12 lg:items-end">
            <div class="flex w-full min-w-0 lg:col-span-4">
                @php
                    $initialCustomerId = old('customer_id', $ancillaryCost->customer_id ?? null);
                    $initialSelectedValue = $initialCustomerId ? "customer-$initialCustomerId" : null;
                    $hint = '<a class="link text-blue-500 hover:underline" href="' .route('customers.create') .'">' . __('Add Customer') . '</a>';
                @endphp

                <div class="flex flex-wrap w-full lg:w-3/4" x-data="{
                        customer_id: '{{ $initialCustomerId }}',
                        selectedValue: '{{ $initialSelectedValue }}',
                    }">
                    <span class="flex text-gray-500">{{ __('Customer') }}
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
                        @selected="customer_id = $event.detail.id;" />
                    <x-input x-bind:value="customer_id" name="customer_id" hidden />
                </div>
            </div>
            <div class="flex w-full min-w-0 lg:col-span-3">
                <div class="flex-wrap w-full">
                    <span class="text-gray-500 w-full"> {{ __('Cost Type') }} </span>
                    <select name="type" id="type" x-model="selectedCostType"
                        class="h-10 min-h-10 border border-slate-400 w-full rounded-md text-gray-500 px-2">
                        <option class="bg-base-100" value="">{{ __('Select Cost Type') }}</option>
                        @foreach (App\Enums\AncillaryCostType::cases() as $type)
                            <option class="bg-base-100" value="{{ $type->valueName() }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex w-full min-w-0 lg:col-span-1">
                <x-text-input placeholder="{{ localizeNumber('0') }}" title="{{ __('VAT') }} (%)" input_name="vat" x-model="vat"
                    @input="vat = $store.utils.convertToEnglish($event.target.value)"
                    x-effect="$el.value = $store.utils.localizeNumber($store.utils.formatNumber(vat))"
                    input_value="{{ old('vat') ?? $ancillaryCost->vat }}" label_text_class="text-gray-500"
                    label_class="w-full" input_class="border-gray-300"></x-text-input>
            </div>
            <div class="flex w-full min-w-0 lg:col-span-2">
                <div class="flex-wrap w-full">
                    <span class="label-text text-gray-500">{{ __('Allocation method') }}</span>
                    <select name="allocation_method" x-model="allocationMethod" @change="changeAllocationMethod()" class="select select-bordered w-full">
                        <option value="manual">{{ __('Manual') }}</option>
                        <option value="value">{{ __('Value') }}</option>
                        <option value="weight">{{ __('Weight') }}</option>
                    </select>
                </div>
            </div>
            <div x-show="allocationMethod !== 'manual'" x-cloak class="flex w-full min-w-0 lg:col-span-2">
                <x-text-input title="{{ __('Ancillary cost before VAT') }}" label_text_class="text-gray-500"
                    input_name="allocation_amount" x-model="allocationAmount"
                    @input="allocationAmount = $store.utils.convertToEnglish($event.target.value); allocate()"
                    x-effect="$el.value = $store.utils.localizeNumber($store.utils.formatNumber(allocationAmount))"
                    placeholder="{{ localizeNumber('0') }}" />
            </div>
        </div>
    </x-card>

    <x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-4">
        <div class="overflow-x-auto overflow-y-hidden lg:overflow-visible">
            <div class="w-max min-w-full">
        <div class="flex w-max min-w-full gap-2 items-center px-4 pb-2">
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
                        <div class="flex w-max min-w-full gap-2 items-center px-4 py-3 border-b">
                            <div class="flex-1 text-center max-w-8">
                                <span class="text-gray-500" x-text="$store.utils.localizeNumber(String(index + 1))"></span>
                            </div>

                            <div class="flex-1 min-w-48 text-center">
                                <span class="text-gray-500" x-text="product.name"></span>
                                <span x-show="allocationWarning(product)" x-cloak
                                    class="tooltip inline-block align-middle"
                                    x-bind:class="product.id === availableProducts[availableProducts.length - 1].id ? 'tooltip-top' : 'tooltip-bottom'"
                                    x-bind:data-tip="allocationWarning(product)">
                                    <button type="button" class="inline-flex items-center justify-center p-1 text-warning"
                                        x-bind:aria-label="allocationWarning(product)">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M10.3 3.9 2.1 18a2 2 0 0 0 1.7 3h16.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                                            <path stroke-linecap="round" d="M12 9v4m0 4h.01" />
                                        </svg>
                                    </button>
                                </span>
                                <x-input name="" x-bind:name="'ancillaryCosts[' + index + '][product_id]'"
                                    x-bind:value="product.id" hidden />
                                <x-input name="" x-bind:name="'ancillaryCosts[' + index + '][description]'"
                                    x-bind:value="selectedCostType" hidden />
                            </div>

                            <div class="flex-1 min-w-32 max-w-32">
                                <x-text-input x-bind:value="$store.utils.localizeNumber($store.utils.formatNumber(product.quantity ?? 0))" readonly
                                    label_class="w-full" input_class="border-gray-200 text-gray-500 text-center" />
                            </div>

                            <div class="flex-1 min-w-32 max-w-48">
                                <x-text-input x-bind:value="calculateAmountPerUnit(product.id, product.quantity)" readonly
                                    label_class="w-full" input_class="border-gray-200 text-gray-500 text-center" />
                            </div>

                            <div class="flex-1 min-w-32 max-w-48">
                                <x-text-input placeholder="{{ localizeNumber('0') }}"
                                    x-bind:value="$store.utils.localizeNumber($store.utils.formatNumber(productAmounts[product.id] ?? 0))"
                                    x-bind:name="'ancillaryCosts[' + index + '][amount]'"
                                    x-bind:readonly="allocationMethod !== 'manual'"
                                    label_text_class="text-gray-500"
                                    label_class="w-full" input_class="border-gray-300"
                                    x-on:input="updateProductAmount(product.id, $event.target.value)">
                                </x-text-input>
                            </div>

                        </div>
                    </template>
                </div>
            </template>
        </div>
            </div>
        </div>
        <hr>
        <div class="flex flex-row flex-wrap justify-end">
            <div class="flex flex-wrap justify-end px-4 gap-4 py-3">
                <div class="flex items-center gap-2 px-4 py-2 shadow-sm rounded-xl border border-gray-200 max-w-full">
                    <span class="text-sm font-medium text-gray-500">{{ __('Total') }} ({{ config('amir.currency') ?? __('Rial') }}):</span>
                    <span class="text-lg font-bold text-green-600" x-text="$store.utils.localizeNumber(calculateTotal().toLocaleString())">
                        {{ localizeNumber('0') }}
                    </span>
                </div>
                <div class="flex items-center gap-2 px-4 py-2 shadow-sm rounded-xl border border-gray-200 max-w-full">
                    <span class="text-sm font-medium text-gray-500">{{ __('Total with VAT') }} ({{ config('amir.currency') ?? __('Rial') }}):</span>
                    <span class="text-lg font-bold text-green-600"
                        x-text="$store.utils.localizeNumber(calculateTotalWithVat(vat).toLocaleString())">
                        {{ localizeNumber('0') }}
                    </span>
                </div>
            </div>
        </div>
    </x-card>
</div>

<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ isset($invoice) && $invoice ? route('invoices.show', $invoice) : route('ancillary-costs.index') }}" type="submit" class="btn btn-default rounded-md">
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
        jalaliDatepicker.startWatch({'persianDigits': true});
    </script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('ancillaryCostForm', () => ({
                availableProducts: [],
                productAmounts: {},
                allocationMethod: 'manual',
                allocationAmount: @json(old('allocation_amount', 0)),

                get zeroWeightProducts() {
                    return this.availableProducts.filter(product => Number(product.weight) <= 0);
                },

                allocationWarning(product) {
                    if (this.allocationMethod === 'weight' && Number(product.weight) <= 0) {
                        return @json(__('For this product, weight is not defined, so it will be excluded from allocation.'));
                    }
                    return '';
                },

                basis(product) {
                    return Math.max(0, this.allocationMethod === 'weight'
                        ? Number(product.quantity) * Number(product.weight)
                        : Number(product.value));
                },

                get allocationBasisTotal() {
                    return this.availableProducts.reduce((total, product) => total + this.basis(product), 0);
                },

                changeAllocationMethod() {
                    if (this.allocationMethod !== 'manual') {
                        if (!this.number(this.allocationAmount)) this.allocationAmount = this.calculateTotal();
                        this.allocate();
                    }
                },

                number(value) {
                    return Number(this.$store.utils.convertToEnglish(value)) || 0;
                },

                allocate() {
                    if (this.allocationMethod === 'manual') return;
                    const totalBasis = this.allocationBasisTotal;
                    const cents = Math.round(Math.max(0, this.number(this.allocationAmount)) * 100);
                    // Round cumulative shares so the saved rows sum to the entered amount.
                    let cumulativeBasis = 0;
                    let allocatedCents = 0;
                    this.productAmounts = Object.fromEntries(this.availableProducts.map(product => {
                        cumulativeBasis += this.basis(product);
                        const cumulativeCents = totalBasis > 0 ? Math.round(cents * cumulativeBasis / totalBasis) : 0;
                        const share = cumulativeCents - allocatedCents;
                        allocatedCents = cumulativeCents;
                        return [product.id, share / 100];
                    }));
                },
                selectedInvoiceId: {{ old('invoice_id') ?? ($ancillaryCost->invoice_id ?? 'null') }},
                selectedCostType: '{{ old('type') ?? ($ancillaryCost->type?->valueName() ?? '') }}',
                selectedCustomerId: {{ old('customer_id') ?? ($ancillaryCost->customer_id ?? 'null') }},
                vat: '{{ old('vat') ?? ($ancillaryCost->vat ?? 0) }}',

                init() {
                    // If editing and invoice_id exists, load products
                    if (this.selectedInvoiceId) {
                        this.loadInvoiceProducts(this.selectedInvoiceId);
                    }

                    // Load existing amounts if editing
                    const existingCosts = @json(old('ancillaryCosts', $ancillaryCostItems ?? []));
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
                    if (!productQuantity) return this.$store.utils.localizeNumber('0');
                    const amount = Number(this.$store.utils.convertToEnglish(this.productAmounts[
                        productId] ?? 0)) || 0;
                    const perUnit = amount / productQuantity;
                    return this.$store.utils.localizeNumber(perUnit.toLocaleString(undefined, {
                        maximumFractionDigits: 2
                    }));
                },

                loadInvoiceProducts(invoiceId) {
                    if (!invoiceId) {
                        this.availableProducts = [];
                        this.productAmounts = {};
                        return;
                    }

                    fetch(`/invoices/ancillary-costs/get-products/${invoiceId}`)
                        .then(response => response.json())
                        .then(data => {
                            this.availableProducts = data.products;
                            this.productAmounts = Object.fromEntries(data.products.map(product =>
                                [product.id, this.productAmounts[product.id] ?? 0]));
                            this.allocate();
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
