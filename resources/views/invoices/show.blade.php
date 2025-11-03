<x-app-layout :title="__('Invoice') . ' ' . $invoice->invoice_type->label() . ' #' . convertToFarsi($invoice->number ?? $invoice->id)">

    <div class="space-y-6">
        <x-show-message-bags />

        @php
            $typeLabels = [
                'buy' => __('Buy invoice'),
                'sell' => __('Sell invoice'),
                'return_buy' => __('Return buy invoice'),
                'return_sell' => __('Return sell invoice'),
            ];
            $items = $invoice->items ?? collect();
            $subTotal = $items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0);
            $discountTotal = $items->reduce(fn($carry, $item) => $carry + ($item->unit_discount ?? 0), 0);
            $vatTotal = $items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0);
            $grandTotal = ($invoice->amount ?? 0) - ($invoice->subtraction ?? 0);
        @endphp

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mt-5 ">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Invoice') }} {{ $invoice->invoice_type->label() }} #{{ convertToFarsi($invoice->number ?? $invoice->id) }}
                    <span class="badge {{ $invoice->permanent ? 'badge-success' : 'badge-warning' }}">
                        {{ $invoice->permanent ? __('Permanent') : __('Draft') }}
                    </span>
                </h2>
                <p class="text-sm text-gray-500">
                    {{ __('Issued on :date', ['date' => $invoice->date ? formatDate($invoice->date) : __('Unknown')]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('invoices.print', $invoice) }}" class="btn btn-outline" target="_blank" rel="noopener">
                    {{ __('Print PDF') }}
                </a>
                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-primary">
                    {{ __('Edit invoice') }}
                </a>
                @if ($invoice->document)
                    <a href="{{ route('documents.show', $invoice->document) }}" class="btn btn-primary">
                        {{ formatDocumentNumber($invoice->document->number) }}
                    </a>
                @endif
            </div>
        </div>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-blue-100 bg-white/80 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-500">{{ __('Subtotal') }}</p>
                <p class="mt-2 text-2xl font-semibold text-blue-800">{{ formatNumber($subTotal) }}</p>
            </div>
            <div class="rounded-xl border border-amber-100 bg-amber-50/80 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-500">{{ __('Discounts') }}</p>
                <p class="mt-2 text-2xl font-semibold text-amber-800">{{ formatNumber($discountTotal) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/80 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-500">{{ __('VAT') }}</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-800">{{ formatNumber($vatTotal) }}</p>
            </div>
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/80 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">{{ __('Grand total') }}</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-800">{{ formatNumber($grandTotal) }}</p>
            </div>
        </div>

        @if ($invoice->description)
            <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-slate-600">
                <p class="text-sm font-medium text-slate-500">{{ __('Notes') }}</p>
                <p class="mt-1 leading-relaxed">
                    {{ $invoice->description }}
                </p>
            </div>
        @endif

        <div class="card border border-slate-200 bg-white shadow-lg shadow-emerald-100/40">
            <div class="card-body">
                @if ($invoice->customer)
                    <div class="flex flex-col gap-4">
                        <p class="text-xl font-semibold text-emerald-600">

                        </p>
                        <dl class="grid grid-cols-1 gap-3 text-sm text-slate-600 md:grid-cols-4">
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Customer') }}</dt>
                                <dd class="text-sm font-semibold text-slate-700">
                                    {{ $invoice->customer->name }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Phone') }}</dt>
                                <dd class="text-sm font-semibold text-slate-700">
                                    {{ $invoice->customer->phone ? convertToFarsi($invoice->customer->phone) : '' }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Economic code') }}</dt>
                                <dd class="text-sm font-semibold text-slate-700">
                                    {{ $invoice->customer->ecnmcs_code ? convertToFarsi($invoice->customer->ecnmcs_code) : '' }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Postal code') }}</dt>
                                <dd class="text-sm font-semibold text-slate-700">
                                    {{ $invoice->customer->postal_code ? convertToFarsi($invoice->customer->postal_code) : '' }}
                                </dd>
                            </div>
                            <div class="flex flex-col gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 md:col-span-4">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ __('Address') }}</dt>
                                <dd class="text-sm font-semibold text-slate-700 leading-relaxed">
                                    {{ $invoice->customer->address ?: '' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                @else
                    <p class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-emerald-700">{{ __('No customer is attached to this invoice.') }}</p>
                @endif
            </div>
        </div>

        <div class="card border border-slate-200 bg-white shadow-lg shadow-indigo-100/50">
            <div class="card-body overflow-x-auto">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <h3 class="card-title text-lg text-indigo-600">{{ __('Items') }}</h3>
                    <span class="text-sm text-indigo-400">
                        {{ __('Total items: :count', ['count' => convertToFarsi($items->count())]) }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="table w-full mt-4">
                        <thead class="bg-indigo-50 text-indigo-600">
                            <tr class="text-xs uppercase tracking-wide">
                                <th class="text-left">#</th>
                                <th class="text-left">{{ __('Product') }}</th>
                                <th class="text-left">{{ __('Description') }}</th>
                                <th class="text-right">{{ __('Quantity') }}</th>
                                <th class="text-right">{{ __('Unit price') }}</th>
                                <th class="text-right">{{ __('Discount') }}</th>
                                <th class="text-right">{{ __('VAT') }}</th>
                                <th class="text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($items as $index => $item)
                                @php
                                    $quantity = $item->quantity ?? 0;
                                    $unitPrice = $item->unit_price ?? 0;
                                    $discount = $item->unit_discount ?? 0;
                                    $vat = $item->vat ?? 0;
                                    $lineBase = $quantity * $unitPrice - $discount;
                                    $lineTotal = $lineBase + $vat;
                                @endphp
                                <tr class="transition-colors hover:bg-indigo-50/70">
                                    <td>{{ convertToFarsi($index + 1) }}</td>
                                    <td>
                                        @if ($item->product)
                                            <a href="{{ route('products.show', $item->product) }}" class="link link-hover link-primary">
                                                {{ $item->product->name }}
                                            </a>
                                        @else
                                            <span class="text-gray-500">{{ __('Removed product') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->description }}</td>
                                    <td class="text-right">{{ formatNumber($quantity) }}</td>
                                    <td class="text-right">{{ formatNumber($unitPrice) }}</td>
                                    <td class="text-right">{{ formatNumber($discount) }}</td>
                                    <td class="text-right">{{ formatNumber($vat) }}</td>
                                    <td class="text-right">{{ formatNumber($lineTotal) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-6 text-center text-gray-500">
                                        {{ __('There are no items on this invoice yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
