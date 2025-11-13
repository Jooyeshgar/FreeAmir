<x-app-layout :title="__('Invoice') . ' ' . $invoice->invoice_type->label() . ' #' . formatDocumentNumber($invoice->number ?? $invoice->id)">

    <div class="card bg-base-100 shadow-xl">
        <div class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                    {{ __('Invoice') }} {{ $invoice->invoice_type->label() }} #{{ formatDocumentNumber($invoice->number ?? $invoice->id) }}
                </h2>
                <p class="mt-1 float-end">
                    {{ __('Issued on :date', ['date' => $invoice->date ? formatDate($invoice->date) : __('Unknown')]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
                <span class="badge badge-lg badge-primary gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h10a2 2 0 012 2v4a2 2 0 01-.586 1.414l-7 7a2 2 0 01-2.828 0l-4.586-4.586A2 2 0 014 12V5a2 2 0 012-2z" />
                    </svg>
                    {{ $invoice->invoice_type->label() }}
                </span>
                <span class="badge badge-lg {{ $invoice->permanent ? 'badge-success' : 'badge-warning' }} gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $invoice->permanent ? __('Permanent') : __('Draft') }}
                </span>
            </div>
        </div>

        <div class="card-body space-y-8">
            <x-show-message-bags />

            @php
                $items = $invoice->items ?? collect();
                $subTotal = $items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0);
                $discountTotal = $items->reduce(fn($carry, $item) => $carry + ($item->unit_discount ?? 0), 0);
                $vatTotal = $items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0);
                $grandTotal = ($invoice->amount ?? 0) - ($invoice->subtraction ?? 0);
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="stats shadow bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60">
                    <div class="stat">
                        <div class="stat-title text-blue-500">{{ __('Subtotal') }}</div>
                        <div class="stat-value text-blue-600">{{ formatNumber($subTotal) }}</div>
                        <div class="stat-desc text-blue-400">{{ __('Before discounts and tax') }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60">
                    <div class="stat">
                        <div class="stat-title text-amber-500">{{ __('Discounts') }}</div>
                        <div class="stat-value text-amber-600">{{ formatNumber($discountTotal) }}</div>
                        <div class="stat-desc text-amber-400">{{ __('Total deductions') }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60">
                    <div class="stat">
                        <div class="stat-title text-emerald-500">{{ __('VAT') }}</div>
                        <div class="stat-value text-emerald-600">{{ formatNumber($vatTotal) }}</div>
                        <div class="stat-desc text-emerald-400">{{ __('Collected tax') }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60">
                    <div class="stat">
                        <div class="stat-title text-indigo-500">{{ __('Grand total') }}</div>
                        <div class="stat-value text-indigo-600">{{ formatNumber($grandTotal) }}</div>
                        <div class="stat-desc text-indigo-400">{{ __('Payable amount') }}</div>
                    </div>
                </div>
            </div>

            @if ($invoice->description)
                <div>
                    <div class="divider text-lg font-semibold">{{ __('Notes') }}</div>
                    <div class="alert bg-base-200 shadow-sm">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ $invoice->description }}</span>
                        </div>
                    </div>
                </div>
            @endif

            <div>
                <div class="divider text-lg font-semibold">{{ __('Customer Details') }}</div>
                @if ($invoice->customer)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Customer') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">{{ $invoice->customer->name }}</p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Phone') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">{{ $invoice->customer->phone ? convertToFarsi($invoice->customer->phone) : '—' }}</p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Economic code') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    {{ $invoice->customer->ecnmcs_code ? convertToFarsi($invoice->customer->ecnmcs_code) : '—' }}</p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Postal code') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    {{ $invoice->customer->postal_code ? convertToFarsi($invoice->customer->postal_code) : '—' }}</p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow md:col-span-2 lg:col-span-4">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">{{ __('Address') }}</h3>
                                <p class="text-sm font-medium text-gray-700 leading-relaxed">{{ $invoice->customer->address ?: '—' }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert bg-emerald-50 border border-emerald-200 text-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 1010 10A10 10 0 0012 2z" />
                        </svg>
                        <span>{{ __('No customer is attached to this invoice.') }}</span>
                    </div>
                @endif
            </div>

            <div>
                <div class="divider text-lg font-semibold">{{ __('Items') }}</div>
                <div class="overflow-x-auto shadow-lg rounded-lg">
                    <table class="table table-zebra w-full">
                        <thead class="bg-base-300">
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3 text-left">{{ $invoice->invoice_type == App\Enums\InvoiceType::BUY ? __('Product') : __('Product/Service') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Description') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Quantity') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Unit price') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Discount') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('VAT') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $index => $item)
                                @php
                                    $quantity = $item->quantity ?? 0;
                                    $unitPrice = $item->unit_price ?? 0;
                                    $discount = $item->unit_discount ?? 0;
                                    $vat = $item->vat ?? 0;
                                    $lineBase = $quantity * $unitPrice - $discount;
                                    $lineTotal = $lineBase + $vat;
                                    $type = $item->itemable_type === 'App\Models\Product' ? 'product' : ($item->itemable_type == 'App\Models\Service' ? 'service' : 'unknown');
                                @endphp
                                <tr class="hover">
                                    <td class="px-4 py-3">{{ convertToFarsi($index + 1) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($item->itemable)
                                            <a href="{{ route($type == 'product' ? 'products.show' : 'services.show', $item->itemable) }}" class="link link-hover link-primary">
                                                {{ $item->itemable->name }}
                                            </a>
                                        @else
                                            <span class="text-gray-500">{{ __('Removed product/service') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $item->description }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($quantity) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($unitPrice) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($discount) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($vat) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($lineTotal) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                                        {{ __('There are no items on this invoice yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-base-300">
                            <tr>
                                <td colspan="8" class="px-4 py-3 text-right text-sm text-gray-600">
                                    {{ __('Total items: :count', ['count' => convertToFarsi($items->count())]) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card-actions justify-between mt-4">
                <a href="{{ route('invoices.index') }}" class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('invoices.print', $invoice) }}" class="btn btn-outline gap-2" target="_blank" rel="noopener">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 8h10M7 12h10m-7 8h4m-7-4h10V8a2 2 0 00-2-2h-2V4a2 2 0 00-2-2h-2a2 2 0 00-2 2v2H9a2 2 0 00-2 2v8z" />
                        </svg>
                        {{ __('Print PDF') }}
                    </a>
                    
                    @php($editDeleteStatus = \App\Services\InvoiceService::getEditDeleteStatus($invoice))

                    @if($editDeleteStatus['allowed'])
                        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-primary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Edit invoice') }}
                        </a>
                    @else
                        <span class="tooltip" data-tip="{{ $editDeleteStatus['reason'] }}">
                            <button class="btn btn-primary gap-2 btn-disabled cursor-not-allowed" disabled title="{{ $editDeleteStatus['reason'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                {{ __('Edit invoice') }}
                            </button>
                        </span>
                    @endif
                    
                    @if ($invoice->document)
                        <a href="{{ route('documents.show', $invoice->document) }}" class="btn btn-secondary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m2 8H7a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v9a2 2 0 01-2 2z" />
                            </svg>
                            {{ formatDocumentNumber($invoice->document->number) }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
