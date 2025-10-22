<x-card class="rounded-2xl w-full" class_body="p-4">
    <div class="flex gap-2 items-center justify-start" x-data="{
        selectedInvoiceId: {{ old('invoice_id' ?? $invoice->id ?? '') ?: 'null' }},
    }">
        <div class="flex w-1/4">
            <div class="flex flex-wrap">
                <span class="flex flex-col flex-wrap text-gray-500 w-full"> {{ __('Invoice') }} </span>
                <select name="invoice_id" id="invoice_id" x-model="selectedInvoiceId"
                    @change="console.log(fetchInvoiceProducts(selectedInvoiceId))"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-3 py-2">
                    <option value="">{{ __('Select Invoice') }}</option>
                    @foreach ($invoices as $invoice)
                        <option value="{{ $invoice->id }}" x-bind:selected="selectedInvoiceId == {{ $invoice->id }}">
                            {{ $invoice->number }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex justify-start gap-2 mt-2">
            <x-text-input data-jdp title="{{ __('date') }}" input_name="date" placeholder="{{ __('date') }}"
                input_value="{{ old('date') ?? convertToJalali($invoice->date ?? now()) }}" 
                label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>
        </div>
    </div>
    
</x-card>
<x-card class="mt-4 rounded-2xl w-full" class_body="p-0 pt-0 mt-4" x-data="ancillaryCostForm">
    <div class="flex overflow-x-auto overflow-y-hidden gap-2 items-center px-4">
        <div class="text-sm flex-1 max-w-8 text-center text-gray-500 pt-3">
            *
        </div>
        <div class="text-sm flex-1 min-w-24 max-w-64 text-center text-gray-500 pt-3">
            {{ __('Product name') }}
        </div>
        <div class="text-sm flex-1 min-w-80 text-center text-gray-500 pt-3">
            {{ __('Description') }}
        </div>
        <div class="text-sm flex-1 min-w-32 max-w-32 text-center text-gray-500 pt-3">
            {{ __('Amount') }}
        </div>
    </div>
    <div class="h-96 overflow-y-auto">
        <div id="ancillaryCosts" x-data="{ activeTab: {{ $total }} }">
            <template x-for="(ancillaryCost, index) in ancillaryCosts" :key="ancillaryCost.id">
                <div :class="{ 'active': activeTab === index }" class="transaction flex gap-2 items-center px-4 pb-3" @click="activeTab = index" x-data="{
                    selectedId: ancillaryCost.product_id || null,
                    off: 0,
                }">
                    <div class="relative flex-1 text-center max-w-8 pt-2 pb-2 transaction-count-container">
                        <span class="transaction-count block" x-text="index + 1"></span>
                        <button @click.stop="ancillaryCosts.splice(index, 1)" type="button" class="absolute left-0 top-0 removeButton">
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
                                ancillaryCost.product_id = Number($event.target.value);
                            "
                            x-bind:name="'ancillaryCosts[' + index + '][product_id]'"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-3 py-2">
                            <option value="">{{ __('Select Product') }}</option>
                            <template x-for="product in availableProducts" :key="product.id">
                                <option :value="product.id" x-text="product.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex-1 w-[200px]">
                        <x-text-input x-bind:value="ancillaryCost.description" placeholder="{{ __('Description') }}" x-bind:name="'ancillaryCosts[' + index + '][description]'"
                            label_text_class="text-gray-500" label_class="w-full" input_class="border-white" x-bind:disabled="!selectedId"></x-text-input>
                    </div>
                    <div class="flex-1 min-w-24 max-w-32">
                        <x-text-input placeholder="0" x-model.number="ancillaryCost.amount" x-bind:name="'ancillaryCosts[' + index + '][amount]'"
                            x-bind:disabled="!selectedId" label_text_class="text-gray-500" label_class="w-full" input_class="border-white">
                        </x-text-input>
                    </div>
                </div>
        </div>
        </template>

        <button class="flex justify-content gap-4 align-center w-full px-4" id="addAncillaryCost" @click="addAncillaryCost; activeTab = ancillaryCosts.length;"
            type="button">
            <div class="bg-gray-200 max-h-10 min-h-10 hover:bg-gray-300 border-none btn w-full rounded-md btn-active">
                <span class="text-2xl">+</span>
                {{ __('Add Ancillary Cost') }}
            </div>
        </button>
    </div>
    </div>
    <hr style="">
    <div class="flex flex-row justify-between">
        <div class="flex justify-end px-4 gap-4 py-3 rounded-b-2xl">
            <!-- Total Sum -->
            <div class="flex items-center gap-2 px-4 py-2 bg-white shadow-sm rounded-xl border border-gray-200">
                <span class="text-sm font-medium text-gray-500">{{ __('Total Sum') }}:</span>
                <span class="text-lg font-bold text-green-600"
                    x-text="(
                        ancillaryCosts.reduce((sum, t) => sum + (Number($store.utils.convertToEnglish(t.total)) || 0), 0)
                    ).toLocaleString()">
                    0
                </span>
            </div>
        </div>
    </div>

</x-card>

<div class="mt-4 flex gap-2 justify-end">
    <a href="{{ route('ancillary-costs.index') }}" type="submit" class="btn btn-default rounded-md"> {{ __('cancel') }}
    </a>
    <button id="submitForm" type="submit" class="btn text-white btn-primary rounded-md">
        {{ __('save and close form') }} </button>
</div>

@pushOnce('scripts')
<script type="module">
    jalaliDatepicker.startWatch();

    document.addEventListener('alpine:init', () => {
        Alpine.store('productData', {
            availableProducts: [],
        });

        Alpine.data('ancillaryCostForm', () => ({
            ancillaryCosts: {!! json_encode($ancillaryCosts, JSON_UNESCAPED_UNICODE) !!},
            get availableProducts() {
                return Alpine.store('productData').availableProducts;
            },

            addAncillaryCost() {
                const newId = this.ancillaryCosts.length
                    ? this.ancillaryCosts[this.ancillaryCosts.length - 1].id + 1
                    : 1;
                this.ancillaryCosts.push({
                    id: newId,
                    date: '',
                    product_id: '',
                    invoice_id: '',
                    description: '',
                    amount: 0,
                });
            },
            async fetchInvoiceProducts(invoiceId) {
                if (!invoiceId) {
                    Alpine.store('productData').availableProducts = [];
                    return;
                }

                try {
                    const response = await fetch(`/ancillary-costs/get-products/${invoiceId}`);
                    const data = await response.json();

                    if (Array.isArray(data)) {
                        Alpine.store('productData').availableProducts = data;
                    } else if (data.products && Array.isArray(data.products)) {
                        Alpine.store('productData').availableProducts = data.products;
                    } else if (data.product && Array.isArray(data.product)) {
                        Alpine.store('productData').availableProducts = data.product;
                    } else {
                        Alpine.store('productData').availableProducts = [];
                        console.warn('Unexpected response format:', data);
                    }
                } catch (error) {
                    console.error('Error fetching products:', error);
                    Alpine.store('productData').availableProducts = [];
                }
            },
        }));
    });

    

    document.addEventListener('alpine:initialized', () => {
        const invoiceIdSelect = document.getElementById('invoice_id');
        if (invoiceIdSelect && invoiceIdSelect.value) {
            fetchInvoiceProducts(invoiceIdSelect.value);
        }
    });
</script>
@endPushOnce