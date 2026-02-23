<x-app-layout :title="__('Invoice') .
    ' ' .
    $invoice->invoice_type->label() .
    ' #' .
    formatDocumentNumber($invoice->number ?? $invoice->id)">

    <div class="card bg-base-100 shadow-xl">
        <div
            class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:text-white dark:from-gray-800 dark:to-gray-700 px-6 py-4 rounded-t-2xl border-b-2 border-primary/20">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                    {{ __('Invoice') }} {{ $invoice->invoice_type->label() }}
                    #{{ formatDocumentNumber($invoice->number ?? $invoice->id) }} - {{ $invoice->title }}
                </h2>
                <p class="mt-1 float-end">
                    {{ __('Issued on :date', ['date' => $invoice->date ? formatDate($invoice->date) : __('Unknown')]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2">
                <span class="badge badge-lg badge-primary gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h10a2 2 0 012 2v4a2 2 0 01-.586 1.414l-7 7a2 2 0 01-2.828 0l-4.586-4.586A2 2 0 014 12V5a2 2 0 012-2z" />
                    </svg>
                    {{ $invoice->invoice_type->label() }}
                </span>
                <span
                    class="badge badge-lg {{ $invoice->status->isApproved() ? 'badge-primary' : ($invoice->status->isRejected() ? 'badge-error' : 'badge-warning') }} gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $invoice->status->label() }}
                </span>
            </div>
        </div>

        <div class="card-body space-y-8">
            <x-show-message-bags />

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="stats shadow bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60">
                    <div class="stat">
                        <div class="stat-title text-blue-500">{{ __('Subtotal') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-blue-600 text-3xl">
                            {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0)) }}
                        </div>
                        <div class="stat-desc text-blue-400">{{ __('Before discounts and tax') }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60">
                    <div class="stat">
                        <div class="stat-title text-amber-500">{{ __('Discounts') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-amber-600 text-3xl">
                            {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->unit_discount ?? 0), 0)) }}
                        </div>
                        <div class="stat-desc text-amber-400">{{ __('Total deductions') }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60">
                    <div class="stat">
                        <div class="stat-title text-emerald-500">{{ __('VAT') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-emerald-600 text-3xl">
                            {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0)) }}
                        </div>
                        <div class="stat-desc text-emerald-400">{{ __('Collected tax') }}</div>
                    </div>
                </div>

                <div class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60">
                    <div class="stat">
                        <div class="stat-title text-indigo-500">{{ __('Grand total') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-indigo-600 text-3xl">
                            {{ formatNumber(($invoice->amount ?? 0) - ($invoice->subtraction ?? 0)) }}</div>
                        <div class="stat-desc text-indigo-400">{{ __('Payable amount') }}</div>
                    </div>
                </div>

                @if ($changeStatusValidation->hasErrors() || $changeStatusValidation->hasWarning())
                    <div class="stats bg-gradient-to-br gap-2 shadow col-span-4">
                        <x-show-messages :message="$changeStatusValidation->toDetailText()" type="alert" />
                    </div>
                @endif

            </div>
            @if ($invoice->description)
                <div>
                    <div class="divider text-lg font-semibold">{{ __('Notes') }}</div>
                    <div class="alert bg-base-200 shadow-sm">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                class="stroke-info shrink-0 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">
                                    {{ __('Customer') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">{{ $invoice->customer->name }}</p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">
                                    {{ __('Phone') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    {{ $invoice->customer->phone ? convertToFarsi($invoice->customer->phone) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">
                                    {{ __('Economic code') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    {{ $invoice->customer->ecnmcs_code ? convertToFarsi($invoice->customer->ecnmcs_code) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">
                                    {{ __('Postal code') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    {{ $invoice->customer->postal_code ? convertToFarsi($invoice->customer->postal_code) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow md:col-span-2 lg:col-span-4">
                            <div class="card-body p-4">
                                <h3 class="card-title text-xs uppercase tracking-wide text-gray-500">
                                    {{ __('Address') }}</h3>
                                <p class="text-sm font-medium text-gray-700 leading-relaxed">
                                    {{ $invoice->customer->address ?: '—' }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert bg-emerald-50 border border-emerald-200 text-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            class="stroke-current shrink-0 w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 1010 10A10 10 0 0012 2z" />
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
                                <tr class="hover">
                                    <td class="px-4 py-3">{{ convertToFarsi($index + 1) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($item->itemable)
                                            <a href="{{ route('products.show' ?? 'services.show', $item->itemable) }}"
                                                class="link link-hover link-primary">
                                                {{ $item->itemable->name }}
                                            </a>
                                        @else
                                            <span class="text-gray-500">{{ __('Removed product/service') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">{{ $item->description }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($item->quantity ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($item->unit_price ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($item->unit_discount ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ formatNumber($item->vat ?? 0) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        {{ formatNumber(($item->quantity ?? 0) * ($item->unit_price ?? 0) - ($item->unit_discount ?? 0) + ($item->vat ?? 0)) }}
                                    </td>
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
                                    {{ __('Total items: :count', ['count' => convertToFarsi($invoice->items->count())]) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if (in_array($invoice->invoice_type, [App\Enums\InvoiceType::BUY, App\Enums\InvoiceType::SELL]))
                <!-- Returned Invoice Information -->
                <div class="divider text-lg font-semibold">{{ __('Invoice') }} {{ __('Return from') }} {{ $invoice->invoice_type->label() }}</div>
                @if ($invoice->getReturnInvoice())
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="card bg-base-200 shadow-sm">
                            <div class="card-body p-4">
                                <h3 class="card-title text-sm font-medium text-gray-500">{{ __('Returned Invoice') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    <a href="{{ route('invoices.show', $invoice->getReturnInvoice()) }}" class="link link-hover link-primary">
                                        {{ $invoice->getReturnInvoice()?->title }} ({{ $invoice->getReturnInvoice()?->invoice_type->label() }} #{{ formatDocumentNumber($invoice->getReturnInvoice()?->number ?? $invoice->getReturnInvoice()?->id) }})
                                    </a>
                                </p>
                            </div>
                        </div>

                        <div class="stats shadow bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60">
                            <div class="stat">
                                <div class="stat-title text-blue-500">{{ __('Subtotal') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-blue-600 text-3xl">
                                    {{ formatNumber($invoice->getReturnInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc text-blue-400">{{ __('Before discounts and tax') }}</div>
                            </div>
                        </div>

                        <div class="stats shadow bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60">
                            <div class="stat">
                                <div class="stat-title text-amber-500">{{ __('Discounts') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-amber-600 text-3xl">
                                    {{ formatNumber($invoice->getReturnInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->unit_discount ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc text-amber-400">{{ __('Total deductions') }}</div>
                            </div>
                        </div>

                        <div class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60">
                            <div class="stat">
                                <div class="stat-title text-emerald-500">{{ __('VAT') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-emerald-600 text-3xl">
                                    {{ formatNumber($invoice->getReturnInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc text-emerald-400">{{ __('Collected tax') }}</div>
                            </div>
                        </div>

                        <div class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60">
                            <div class="stat">
                                <div class="stat-title text-indigo-500">{{ __('Grand total') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-indigo-600 text-3xl">
                                    {{ formatNumber(($invoice->getReturnInvoice()?->amount ?? 0) - ($invoice->getReturnInvoice()?->subtraction ?? 0)) }}</div>
                                <div class="stat-desc text-indigo-400">{{ __('Payable amount') }}</div>
                            </div>
                        </div>
                    </div>
                    {{-- <div class="divider text-sm font-semibold font-medium">{{ __('Returned Items') }}</div> --}}
                    <div class="overflow-x-auto shadow-lg rounded-lg">
                        <table class="table table-zebra w-full">
                            <thead class="bg-base-300">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3 text-right">
                                        @if ($invoice->invoice_type == App\Enums\InvoiceType::BUY)
                                            @if ($isServiceBuy) {{ __('Service') }} @endif
                                            {{ __('Product') }}
                                        @else
                                            {{ __('Product/Service') }}
                                        @endif
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
                                @forelse ($invoice->getReturnInvoice()?->items as $index => $item)
                                    <tr class="hover">
                                        <td class="px-4 py-3">{{ convertToFarsi($index + 1) }}</td>
                                        <td class="px-4 py-3">
                                            @if ($item->itemable)
                                                <a href="{{ route($item->itemable instanceof App\Models\Product ? 'products.show' : 'services.show', $item->itemable) }}"
                                                    class="link link-hover link-primary">
                                                    {{ $item->itemable->name }}
                                                </a>
                                            @else
                                                <span class="text-gray-500">{{ __('Removed product/service') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $item->description }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber($item->quantity ?? 0) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber($item->unit_price ?? 0) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber($item->unit_discount ?? 0) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber($item->vat ?? 0) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(($item->quantity ?? 0) * ($item->unit_price ?? 0) - ($item->unit_discount ?? 0) + ($item->vat ?? 0)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                                            {{ __('There are no items on this return invoice yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-base-300">
                                <tr>
                                    <td colspan="8" class="px-4 py-3 text-right text-sm text-gray-600">
                                        {{ __('Total items: :count', ['count' => convertToFarsi($invoice->getReturnInvoice()?->items->count() ?? 0)]) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert bg-base-200 shadow-sm">
                        <span>{{ __('This invoice does not returned yet.') }}</span>
                    </div>
                @endif
            @endif

            @if (in_array($invoice->invoice_type, [App\Enums\InvoiceType::RETURN_BUY, App\Enums\InvoiceType::RETURN_SELL]))
                <div class="divider text-lg font-semibold">{{ __('Invoice') }} {{$invoice->getReturnedInvoice()?->invoice_type->label() }}</div>
                @if ($invoice->getReturnedInvoice())
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="card bg-base-200 shadow-sm">
                            <div class="card-body p-4">
                                <h3 class="card-title text-sm font-medium text-gray-500">{{ __('Title') }} {{ __('Invoice') }}</h3>
                                <p class="text-lg font-semibold text-gray-800">
                                    <a href="{{ route('invoices.show', $invoice->getReturnedInvoice()) }}" class="link link-hover link-primary">
                                        {{ $invoice->getReturnedInvoice()?->title }} ({{ $invoice->getReturnedInvoice()?->invoice_type->label() }} #{{ formatDocumentNumber($invoice->getReturnedInvoice()?->number ?? $invoice->getReturnedInvoice()?->id) }})
                                    </a>
                                </p>
                            </div>
                        </div>

                        <div class="stats shadow bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60">
                            <div class="stat">
                                <div class="stat-title text-blue-500">{{ __('Subtotal') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-blue-600 text-3xl">
                                    {{ formatNumber($invoice->getReturnedInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc text-blue-400">{{ __('Before discounts and tax') }}</div>
                            </div>
                        </div>

                        <div class="stats shadow bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60">
                            <div class="stat">
                                <div class="stat-title text-amber-500">{{ __('Discounts') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-amber-600 text-3xl">
                                    {{ formatNumber($invoice->getReturnedInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->unit_discount ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc text-amber-400">{{ __('Total deductions') }}</div>
                            </div>
                        </div>

                        <div class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60">
                            <div class="stat">
                                <div class="stat-title text-emerald-500">{{ __('VAT') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-emerald-600 text-3xl">
                                    {{ formatNumber($invoice->getReturnedInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc text-emerald-400">{{ __('Collected tax') }}</div>
                            </div>
                        </div>

                        <div class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60">
                            <div class="stat">
                                <div class="stat-title text-indigo-500">{{ __('Grand total') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-indigo-600 text-3xl">
                                    {{ formatNumber(($invoice->getReturnedInvoice()?->amount ?? 0) - ($invoice->getReturnedInvoice()?->subtraction ?? 0)) }}</div>
                                <div class="stat-desc text-indigo-400">{{ __('Payable amount') }}</div>
                            </div>
                        </div>
                    </div>
                    {{-- <div class="divider text-sm font-semibold"> {{ __('Items') }} {{ __('Invoice') }} {{ $invoice->getReturnedInvoice()?->invoice_type->label() }}</div> --}}
                    <div class="overflow-x-auto shadow-lg rounded-lg">
                        <table class="table table-zebra w-full">
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
                                @forelse ($invoice->getReturnedInvoice()?->items as $index => $item)
                                    <tr class="hover">
                                        <td class="px-4 py-3">{{ convertToFarsi($index + 1) }}</td>
                                        <td class="px-4 py-3">
                                            @if ($item->itemable)
                                                <a href="{{ route($item->itemable instanceof App\Models\Product ? 'products.show' : 'services.show', $item->itemable) }}"
                                                    class="link link-hover link-primary">
                                                    {{ $item->itemable->name }}
                                                </a>
                                            @else
                                                <span class="text-gray-500">{{ __('Removed product/service') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $item->description }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber($item->quantity ?? 0) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber($item->unit_price ?? 0) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber($item->unit_discount ?? 0) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber($item->vat ?? 0) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber(($item->quantity ?? 0) * ($item->unit_price ?? 0) - ($item->unit_discount ?? 0) + ($item->vat ?? 0)) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                                            {{ __('There are no items on this return invoice yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-base-300">
                                <tr>
                                    <td colspan="8" class="px-4 py-3 text-right text-sm text-gray-600">
                                        {{ __('Total items: :count', ['count' => convertToFarsi($invoice->getReturnedInvoice()?->items->count() ?? 0)]) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert bg-base-200 shadow-sm">
                        <span>{{ __('This invoice has not any returned invoices.') }}</span>
                    </div>
                @endif
            @endif

            @if ($invoice->invoice_type === App\Enums\InvoiceType::BUY)
                <div>
                    <div class="divider text-lg font-semibold">{{ __('Ancillary Costs') }}</div>
                    @if ($invoice->ancillaryCosts->isNotEmpty())
                        <div class="mt-4">
                            <table class="table w-full">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3">{{ __('Doc Number') }}</th>
                                        <th class="px-4 py-3">{{ __('Cost Type') }}</th>
                                        <th class="px-4 py-3">{{ __('Status') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('VAT') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                                        <th class="px-4 py-3">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoice->ancillaryCosts as $ancillaryCost)
                                        <tr class="hover">
                                            <td class="px-4 py-3">
                                                <a class="link"
                                                    href="{{ route('invoices.ancillary-costs.show', [$invoice, $ancillaryCost]) }}">
                                                    {{ formatDocumentNumber($ancillaryCost->document?->number ?? ($ancillaryCost->document_id ?? ($ancillaryCost->number ?? $ancillaryCost->id))) }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3">{{ $ancillaryCost->type?->label() ?? '—' }}</td>
                                            <td class="px-4 py-3">{{ $ancillaryCost->status?->label() ?? '—' }}</td>
                                            <td class="px-4 py-3 text-right">
                                                {{ formatNumber((float) ($ancillaryCost->amount ?? 0) - (float) ($ancillaryCost->vat ?? 0)) }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                {{ formatNumber((float) ($ancillaryCost->vat ?? 0)) }}</td>
                                            <td class="px-4 py-3 text-right">
                                                {{ formatNumber((float) ($ancillaryCost->amount ?? 0)) }}</td>
                                            <td class="px-4 py-3">
                                                @php
                                                    $ancillaryChangeStatusValidation = \App\Services\AncillaryCostService::getChangeStatusValidation(
                                                        $ancillaryCost,
                                                    );
                                                    $ancillaryEditDeleteStatus = \App\Services\AncillaryCostService::getEditDeleteStatus(
                                                        $ancillaryCost,
                                                    );
                                                @endphp

                                                <div class="flex flex-wrap gap-2 items-center">
                                                    @can('ancillary-costs.approve')
                                                        @if ($ancillaryChangeStatusValidation['allowed'] ?? false)
                                                            <a href="{{ route('ancillary-costs.change-status', [$ancillaryCost, $ancillaryCost->status?->isApproved() ? 'unapprove' : 'approve']) }}"
                                                                class="btn btn-xs {{ $ancillaryCost->status?->isApproved() ? 'btn-warning' : 'btn-success' }}">
                                                                {{ __($ancillaryCost->status?->isApproved() ? 'Unapprove' : 'Approve') }}
                                                            </a>
                                                        @else
                                                            <span class="tooltip"
                                                                data-tip="{{ $ancillaryChangeStatusValidation['reason'] ?? '' }}">
                                                                <button
                                                                    class="btn btn-xs {{ $ancillaryCost->status?->isApproved() ? 'btn-warning' : 'btn-success' }} btn-disabled cursor-not-allowed">
                                                                    {{ __($ancillaryCost->status?->isApproved() ? 'Unapprove' : 'Approve') }}
                                                                </button>
                                                            </span>
                                                        @endif
                                                    @endcan

                                                    @if (
                                                        !empty($ancillaryEditDeleteStatus) &&
                                                            ($ancillaryEditDeleteStatus['allowed'] ?? false) &&
                                                            !$ancillaryCost->status?->isApproved())
                                                        @can('ancillary-costs.edit')
                                                            <a href="{{ route('invoices.ancillary-costs.edit', [$invoice, $ancillaryCost]) }}"
                                                                class="btn btn-xs btn-info">
                                                                {{ __('Edit') }}
                                                            </a>
                                                        @endcan
                                                        @can('ancillary-costs.delete')
                                                            <form class="m-0"
                                                                action="{{ route('invoices.ancillary-costs.destroy', [$invoice, $ancillaryCost]) }}"
                                                                method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-xs btn-error">
                                                                    {{ __('Delete') }}
                                                                </button>
                                                            </form>
                                                        @endcan
                                                    @elseif (!empty($ancillaryEditDeleteStatus) && !($ancillaryEditDeleteStatus['allowed'] ?? true))
                                                        <span class="tooltip"
                                                            data-tip="{{ $ancillaryEditDeleteStatus['reason'] ?? '' }}">
                                                            <button
                                                                class="btn btn-xs btn-info btn-disabled cursor-not-allowed">
                                                                {{ __('Edit') }}
                                                            </button>
                                                        </span>
                                                        <span class="tooltip"
                                                            data-tip="{{ $ancillaryEditDeleteStatus['reason'] ?? '' }}">
                                                            <button
                                                                class="btn btn-xs btn-error btn-disabled cursor-not-allowed">
                                                                {{ __('Delete') }}
                                                            </button>
                                                        </span>
                                                    @else
                                                        <span class="tooltip"
                                                            data-tip="{{ __('Unapprove the ancillary cost first to edit') }}">
                                                            <button
                                                                class="btn btn-xs btn-info btn-disabled cursor-not-allowed">
                                                                {{ __('Edit') }}
                                                            </button>
                                                        </span>
                                                        <span class="tooltip"
                                                            data-tip="{{ __('Unapprove the ancillary cost first to delete') }}">
                                                            <button
                                                                class="btn btn-xs btn-error btn-disabled cursor-not-allowed">
                                                                {{ __('Delete') }}
                                                            </button>
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert bg-base-200 shadow-sm">
                            <div>
                                <span>{{ __('No ancillary costs are attached to this invoice.') }}</span>
                            </div>
                        </div>
                    @endif
                    @can('ancillary-costs.create')
                        <div class="flex mt-2">
                            <a href="{{ route('invoices.ancillary-costs.create', $invoice) }}" class="btn btn-primary">
                                {{ __('Create Ancillary Cost') }}
                            </a>
                        </div>
                    @endcan
            @endif

            <div class="card-actions justify-between mt-4">
                <a href="{{ route('invoices.index', ['invoice_type' => $invoice->invoice_type, 'service_buy' => '1']) }}"
                    class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>

                <div class="flex flex-wrap gap-2">
                    @php
                        $isSellWorkflow = $invoice->invoice_type === App\Enums\InvoiceType::SELL;
                        $canApprove = ($isSellWorkflow ? false : $invoice->status->isPending()) || $invoice->status->isReadyToApprove() || $invoice->status->isUnapproved() || $invoice->status->isApprovedInactive();
                        $canUnapprove = $invoice->status->isApproved();
                        $canChangeStatus = $canApprove || $canUnapprove;
                    @endphp
                    <a href="{{ route('invoices.print', $invoice) }}" class="btn btn-outline gap-2" target="_blank"
                        rel="noopener">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 8h10M7 12h10m-7 8h4m-7-4h10V8a2 2 0 00-2-2h-2V4a2 2 0 00-2-2h-2a2 2 0 00-2 2v2H9a2 2 0 00-2 2v8z" />
                        </svg>
                        {{ __('Print PDF') }}
                    </a>

                    @can('invoices.approve')
                        @if ($isSellWorkflow && ($invoice->status->isPreInvoice() || $invoice->status->isRejected()))
                            <a href="{{ route('invoices.change-status', [$invoice, 'ready_to_approve']) }}"
                                class="btn btn-success gap-2">
                                {{ __('Ready to approve') }}
                            </a>
                        @endif

                        @if ($isSellWorkflow && $invoice->status->isPreInvoice())
                            <a href="{{ route('invoices.change-status', [$invoice, 'rejected']) }}"
                                class="btn btn-error gap-2">
                                {{ __('Reject') }}
                            </a>
                        @endif

                        @if ($canChangeStatus)
                            @if ($canApprove && $changeStatusValidation->hasErrors())
                                <a data-tip="{{ $changeStatusValidation->toText() }}"
                                    href="{{ route('invoices.conflicts', $invoice) }}"
                                    class="btn btn-accent inline-flex tooltip">
                                    {{ __('Fix Conflict') }}
                                </a>
                            @else
                                <a x-data="{}"
                                    @if ($changeStatusValidation->hasWarning()) @click.prevent="if (confirm(@js($changeStatusValidation->toText()))) { window.location.href = '{{ route('invoices.change-status', [$invoice, $canUnapprove ? 'unapproved' : 'approved']) }}?confirm=1' }" @endif
                                    data-tip="{{ $changeStatusValidation->toText() }}"
                                    href="{{ route('invoices.change-status', [$invoice, $canUnapprove ? 'unapproved' : 'approved']) }}"
                                    class="btn btn-primary gap-2 inline-flex tooltip {{ $canUnapprove ? 'btn-warning' : 'btn-success' }} {{ $canApprove && $changeStatusValidation->hasWarning() ? ' btn-outline ' : '' }}">
                                    {{ $canUnapprove ? __('Unapprove') : __('Approve') }}
                                </a>
                            @endif
                        @endif
                    @endcan

                    @if (!$invoice->status->isApproved())
                        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-primary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Edit invoice') }}
                        </a>
                    @else
                        <span class="tooltip" data-tip="{{ __('Editing is not allowed for approved invoices.') }}">
                            <button class="btn btn-primary gap-2 btn-disabled cursor-not-allowed">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                {{ __('Edit invoice') }}
                            </button>
                        </span>
                    @endif

                    @if ($invoice->document)
                        @can('documents.show')
                            <a href="{{ route('documents.show', $invoice->document) }}" class="btn btn-secondary gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m2 8H7a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v9a2 2 0 01-2 2z" />
                                </svg>
                                {{ formatDocumentNumber($invoice->document->number) }}
                            </a>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
