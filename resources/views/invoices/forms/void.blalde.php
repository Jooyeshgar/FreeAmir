<x-app-layout :title="__('Void') . ' ' . __('Invoice')">
    <form action="{{ route('invoices.void', $invoice) }}" method="POST">
        @csrf
        <div class="card-body">
            <h2 class="card-title">{{ __('Void') . ' ' . __('Invoice') }}</h2>

            <x-show-message-bags />

            <x-card class="rounded-2xl w-full" class_body="p-4">
                <div class="flex justify-start gap-2 mt-2">
                    <x-text-input data-jdp title="{{ __('Void date') }}" input_name="date" placeholder="{{ __('Void date') }}" readonly
                        input_value="{{ old('date') ?? convertToJalali(now(), true) }}"
                        label_text_class="text-gray-500 text-nowrap" input_class="datePicker"></x-text-input>

                    <x-text-input x-data="{ invoice_number: '{{ formatDocumentNumber($previousInvoiceNumber + 1) }}' }"
                        title="{{ __('Void Invoice Number') }}" x-model.number="invoice_number" x-bind:name="'invoice_number'"
                        placeholder="{{ __('Void Invoice Number') }}" label_text_class="text-gray-500 text-nowrap"
                        x-on:input="invoice_number = $store.utils.convertToEnglish($event.target.value);"
                        x-effect="$el.value = $store.utils.convertToFarsi($store.utils.formatNumber(invoice_number));">
                    </x-text-input>
                </div>
                <div class="divider text-lg font-semibold">{{ __('Void Invoice information') }}</div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="card bg-base-200 shadow-sm">
                        <div class="card-body p-4">
                            <h3 class="card-title text-sm font-medium text-gray-500 dark:text-slate-300">{{ __('Title') }}
                                {{ __('Invoice') }}</h3>
                            <p class="text-lg font-semibold text-gray-800 dark:text-slate-100">{{ __('Invoice') . ' ' . $invoice->invoice_type->label() . ' #' . formatDocumentNumber($invoice->number ?? $invoice->id) }}</p>
                        </div>
                    </div>
                    <div class="stats bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60 dark:from-slate-800 dark:to-sky-950/40 dark:border-sky-500/20 dark:ring-1 dark:ring-white/5">
                        <div class="stat">
                            <div class="stat-title text-blue-500 dark:text-sky-300">{{ __('Subtotal') }}
                                ({{ config('amir.currency') ?? __('Rial') }})</div>
                            <div class="stat-value text-blue-600 dark:text-sky-200 text-3xl">
                                {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0)) }}
                            </div>
                            <div class="stat-desc text-blue-400 dark:text-sky-400/80">{{ __('Before discounts and tax') }}</div>
                        </div>
                    </div>

                    <div
                        class="stats bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60 dark:from-slate-800 dark:to-amber-950/40 dark:border-amber-500/20 dark:ring-1 dark:ring-white/5">
                        <div class="stat">
                            <div class="stat-title text-amber-500 dark:text-amber-300">{{ __('Discounts') }}
                                ({{ config('amir.currency') ?? __('Rial') }})</div>
                            <div class="stat-value text-amber-600 dark:text-amber-200 text-3xl">
                                {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->unit_discount ?? 0), 0)) }}
                            </div>
                            <div class="stat-desc text-amber-400 dark:text-amber-400/80">{{ __('Total deductions') }}</div>
                        </div>
                    </div>

                    <div
                        class="stats bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60 dark:from-slate-800 dark:to-emerald-950/40 dark:border-emerald-500/20 dark:ring-1 dark:ring-white/5">
                        <div class="stat">
                            <div class="stat-title text-emerald-500 dark:text-emerald-300">{{ __('VAT') }}
                                ({{ config('amir.currency') ?? __('Rial') }})</div>
                            <div class="stat-value text-emerald-600 dark:text-emerald-200 text-3xl">
                                {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0)) }}
                            </div>
                            <div class="stat-desc text-emerald-400 dark:text-emerald-400/80">{{ __('Collected tax') }}</div>
                        </div>
                    </div>

                    <div
                        class="stats bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60 dark:from-slate-800 dark:to-indigo-950/40 dark:border-indigo-500/20 dark:ring-1 dark:ring-white/5">
                        <div class="stat">
                            <div class="stat-title text-indigo-500 dark:text-indigo-300">{{ __('Grand total') }}
                                ({{ config('amir.currency') ?? __('Rial') }})</div>
                            <div class="stat-value text-indigo-600 dark:text-indigo-200 text-3xl">
                                {{ formatNumber(($invoice->amount ?? 0) - ($invoice->subtraction ?? 0)) }}
                            </div>
                            <div class="stat-desc text-indigo-400 dark:text-indigo-400/80">{{ __('Payable amount') }}</div>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table w-full">
                        <thead class="bg-base-300">
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3 text-right">
                                    {{ $invoice->invoice_type == App\Enums\InvoiceType::BUY ? __('Product') : __('Product/Service') }}
                                </th>
                                <th class="px-4 py-3 text-right">{{ __('Description') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Quantity') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Unit price') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Discount') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('VAT') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoice->items as $index => $item)
                                <tr>
                                    <td class="px-4 py-3">{{ convertToFarsi($index + 1) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($item->itemable)
                                            <a href="{{ route($item->itemable instanceof App\Models\Product ? 'products.show' : 'services.show', $item->itemable) }}"
                                                class="link link-hover link-primary">
                                                {{ $item->itemable->name }}
                                            </a>
                                        @else
                                            <span class="text-gray-500 dark:text-slate-400">{{ __('Removed product/service') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $item->description }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($item->quantity ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($item->unit_price ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ formatNumber($item->unit_discount ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($item->vat ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        {{ formatNumber(($item->quantity ?? 0) * ($item->unit_price ?? 0) - ($item->unit_discount ?? 0) + ($item->vat ?? 0)) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-gray-500 dark:text-slate-400">
                                        {{ __('There are no items on this return invoice yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-base-300">
                            <tr>
                                <td colspan="8" class="px-4 py-3 text-right text-sm text-gray-600 dark:text-slate-300">
                                    {{ __('Total items: :count', ['count' => convertToFarsi($invoice->items->count() ?? 0)]) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="card-actions justify-end">
                    <a href="{{ url()->previous() }}" class="btn btn-ghost">{{ __('Back') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Void') }}</button>
                </div>
            </form>
        </x-card>
    </div>

    @pushOnce('scripts')
        <script type="module">
            jalaliDatepicker.startWatch({'persianDigits': true});
        </script>
    @endPushOnce
    
</x-app-layout>