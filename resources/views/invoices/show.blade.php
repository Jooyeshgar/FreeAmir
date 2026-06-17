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
                    class="badge badge-lg {{ $invoice->status->isApproved() ? 'badge-primary' : ($invoice->status->isPaid() ? 'badge-success' : ($invoice->status->isPartiallyPaid() ? 'badge-info' : ($invoice->status->isRejected() ? 'badge-error' : 'badge-warning'))) }} gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $invoice->status->label() }}
                </span>

                @if ($isMoadianSendable && $invoice->status->isApprovedOrSettled())
                    <a class="badge badge-lg link"
                        href="{{ route('invoices.moadian-histories.show', $invoice) }}">{{ __('Moadian Histories') }}</a>
                @endif

                @php
                    $voidInvoice = $invoice->voidInvoice;
                    $voidedInvoice = $invoice->voidedInvoice;
                @endphp

                @if ($voidInvoice || $voidedInvoice)
                    <span class="badge badge-lg badge-error gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 2h6l5 5v13a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2h3z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 10l6 6m0-6l-6 6" />
                        </svg>
                        @if ($voidInvoice)
                            <a href="{{ route('invoices.show', $voidInvoice) }}"
                                class="link">{{ __('This invoice is voided.') }}</a>
                        @else
                            <a href="{{ route('invoices.show', $voidedInvoice) }}"
                                class="link">{{ __('The void invoice of sell invoice number #:number', [
                                    'number' => formatDocumentNumber($voidedInvoice->number),
                                ]) }}</a>
                        @endif
                    </span>
                @endif
            </div>
        </div>

        <div class="card-body space-y-8">
            <x-show-message-bags />

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    class="stats shadow bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60 dark:from-slate-800 dark:to-sky-950/40 dark:border-sky-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                    <div class="stat">
                        <div class="stat-title text-blue-500 dark:text-sky-300">{{ __('Subtotal') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-blue-600 dark:text-sky-200 text-3xl">
                            {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0)) }}
                        </div>
                        <div class="stat-desc text-blue-400 dark:text-sky-400/80">{{ __('Before discounts and tax') }}
                        </div>
                    </div>
                </div>

                <div
                    class="stats shadow bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60 dark:from-slate-800 dark:to-amber-950/40 dark:border-amber-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
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
                    class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60 dark:from-slate-800 dark:to-emerald-950/40 dark:border-emerald-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                    <div class="stat">
                        <div class="stat-title text-emerald-500 dark:text-emerald-300">{{ __('VAT') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-emerald-600 dark:text-emerald-200 text-3xl">
                            {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0)) }}
                        </div>
                        <div class="stat-desc text-emerald-400 dark:text-emerald-400/80">{{ __('Collected tax') }}
                        </div>
                    </div>
                </div>

                <div
                    class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60 dark:from-slate-800 dark:to-indigo-950/40 dark:border-indigo-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                    <div class="stat">
                        <div class="stat-title text-indigo-500 dark:text-indigo-300">{{ __('Grand total') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value text-indigo-600 dark:text-indigo-200 text-3xl">
                            {{ formatNumber(($invoice->amount ?? 0) - ($invoice->subtraction ?? 0)) }}</div>
                        <div class="stat-desc text-indigo-400 dark:text-indigo-400/80">{{ __('Payable amount') }}</div>
                    </div>
                </div>

                @if ($changeStatusValidation->hasErrors() || $changeStatusValidation->hasWarning())
                    <div class="stats bg-base-100 gap-2 shadow col-span-4">
                        <x-show-messages :message="$changeStatusValidation->toDetailText()" type="alert" />
                    </div>
                @endif

            </div>
            @if ($invoice->description)
                <div>
                    <div class="divider text-lg font-semibold">{{ __('Notes') }}</div>
                    <div class="alert bg-base-200">
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
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Customer') }}</h3>
                                <p class="text-lg font-semibold text-gray-800 dark:text-slate-100">
                                    {{ $invoice->customer->name }}</p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Phone') }}</h3>
                                <p class="text-lg font-semibold text-gray-800 dark:text-slate-100">
                                    {{ $invoice->customer->phone ? localizeNumber($invoice->customer->phone) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Economic code') }}</h3>
                                <p class="text-lg font-semibold text-gray-800 dark:text-slate-100">
                                    {{ $invoice->customer->ecnmcs_code ? localizeNumber($invoice->customer->ecnmcs_code) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Postal code') }}</h3>
                                <p class="text-lg font-semibold text-gray-800 dark:text-slate-100">
                                    {{ $invoice->customer->postal_code ? localizeNumber($invoice->customer->postal_code) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow md:col-span-2 lg:col-span-4">
                            <div class="card-body p-4">
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Address') }}</h3>
                                <p class="text-sm font-medium text-gray-700 dark:text-slate-200 leading-relaxed">
                                    {{ $invoice->customer->address ?: '—' }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div
                        class="alert bg-emerald-50 border border-emerald-200 text-emerald-700 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-200">
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
                <div>
                    <table class="table w-full">
                        <thead>
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
                                <tr class="hover:bg-base-300">
                                    <td class="px-4 py-3">{{ localizeNumber($index + 1) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($item->itemable)
                                            <a href="{{ route($item->itemable instanceof App\Models\Product ? 'products.show' : 'services.show', $item->itemable) }}"
                                                class="link link-hover link-primary">
                                                {{ $item->itemable->name }}
                                            </a>
                                        @else
                                            <span
                                                class="text-gray-500 dark:text-slate-400">{{ __('Removed product/service') }}</span>
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
                                    <td colspan="8"
                                        class="px-4 py-6 text-center text-gray-500 dark:text-slate-400">
                                        {{ __('There are no items on this invoice yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8"
                                    class="px-4 py-3 text-right text-sm text-gray-600 dark:text-slate-300">
                                    {{ __('Total items: :count', ['count' => localizeNumber($invoice->items->count())]) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if (in_array($invoice->invoice_type, [App\Enums\InvoiceType::BUY, App\Enums\InvoiceType::SELL]) &&
                    !$invoice->voidInvoice)
                <!-- Returned Invoice Information -->
                <div>
                    @if ($invoice->getReturnInvoice())
                        <div class="divider text-lg font-semibold">{{ __('Invoice') }}
                            {{ __('Return from') }}{{ $invoice->invoice_type->label() }}</div>
                        <div>
                            <table class="table w-full">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3">#</th>
                                        <th class="px-4 py-3 text-right">{{ __('Invoice Number') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('Date') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($invoice->getReturnInvoice() as $index => $returnedInvoice)
                                        <tr class="hover:bg-base-300">
                                            <td class="px-4 py-3">{{ localizeNumber($index + 1) }}</td>
                                            <td class="px-4 py-3">
                                                <a href="{{ route('invoices.show', $returnedInvoice) }}"
                                                    class="link link-hover link-primary">
                                                    {{ formatDocumentNumber($returnedInvoice->number) }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-right">{{ formatDate($returnedInvoice->date) }}
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                {{ formatNumber($returnedInvoice->amount) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8"
                                                class="px-4 py-6 text-center text-gray-500 dark:text-slate-400">
                                                {{ __('There are no return invoices yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if (!in_array($invoice->invoice_type, [App\Enums\InvoiceType::RETURN_BUY, App\Enums\InvoiceType::RETURN_SELL]))
                            <div class="mt-2 text-right {{ $invoice->status->isApproved() ? '' : 'tooltip' }}"
                                data-tip="{{ $invoice->status->isApproved() ? '' : __('Only approved invoices can be returned.') }}">
                                @if ($invoice->invoice_type === App\Enums\InvoiceType::BUY)
                                    <a href="{{ route('invoices.create', ['invoice_type' => 'return_buy', 'returned_invoice_id' => $invoice->id, 'service_buy' => $isServiceBuy ? '1' : null]) }}"
                                        class="btn btn-primary {{ $invoice->status->isApproved() ? '' : 'btn-disabled' }}">
                                        {{ __('Create return buy invoice') }}
                                    </a>
                                @elseif ($invoice->invoice_type === App\Enums\InvoiceType::SELL)
                                    <a href="{{ route('invoices.create', ['invoice_type' => 'return_sell', 'returned_invoice_id' => $invoice->id]) }}"
                                        class="btn btn-primary {{ $invoice->status->isApproved() ? '' : 'btn-disabled' }}">
                                        {{ __('Create return sell invoice') }}
                                    </a>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            @endif

            @if (in_array($invoice->invoice_type, [App\Enums\InvoiceType::RETURN_BUY, App\Enums\InvoiceType::RETURN_SELL]))
                <div class="divider text-lg font-semibold">{{ __('Invoice') }}
                    {{ $invoice->getReturnedInvoice()?->invoice_type->label() }}</div>
                @if ($invoice->getReturnedInvoice())
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h3 class="card-title text-sm font-medium text-gray-500 dark:text-slate-300">
                                    {{ __('Title') }}
                                    {{ __('Invoice') }}</h3>
                                <p class="text-lg font-semibold text-gray-800 dark:text-slate-100">
                                    <a href="{{ route('invoices.show', $invoice->getReturnedInvoice()) }}"
                                        class="link link-hover link-primary">
                                        {{ $invoice->getReturnedInvoice()?->title }}
                                        ({{ $invoice->getReturnedInvoice()?->invoice_type->label() }}
                                        #{{ formatDocumentNumber($invoice->getReturnedInvoice()?->number ?? $invoice->getReturnedInvoice()?->id) }})
                                    </a>
                                </p>
                            </div>
                        </div>

                        <div
                            class="stats shadow bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60 dark:from-slate-800 dark:to-sky-950/40 dark:border-sky-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                            <div class="stat">
                                <div class="stat-title text-blue-500 dark:text-sky-300">{{ __('Subtotal') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-blue-600 dark:text-sky-200 text-3xl">
                                    {{ formatNumber($invoice->getReturnedInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc text-blue-400 dark:text-sky-400/80">
                                    {{ __('Before discounts and tax') }}</div>
                            </div>
                        </div>

                        <div
                            class="stats shadow bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60 dark:from-slate-800 dark:to-amber-950/40 dark:border-amber-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                            <div class="stat">
                                <div class="stat-title text-amber-500 dark:text-amber-300">{{ __('Discounts') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-amber-600 dark:text-amber-200 text-3xl">
                                    {{ formatNumber($invoice->getReturnedInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->unit_discount ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc text-amber-400 dark:text-amber-400/80">
                                    {{ __('Total deductions') }}</div>
                            </div>
                        </div>

                        <div
                            class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60 dark:from-slate-800 dark:to-emerald-950/40 dark:border-emerald-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                            <div class="stat">
                                <div class="stat-title text-emerald-500 dark:text-emerald-300">{{ __('VAT') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-emerald-600 dark:text-emerald-200 text-3xl">
                                    {{ formatNumber($invoice->getReturnedInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc text-emerald-400 dark:text-emerald-400/80">
                                    {{ __('Collected tax') }}</div>
                            </div>
                        </div>

                        <div
                            class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60 dark:from-slate-800 dark:to-indigo-950/40 dark:border-indigo-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                            <div class="stat">
                                <div class="stat-title text-indigo-500 dark:text-indigo-300">{{ __('Grand total') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value text-indigo-600 dark:text-indigo-200 text-3xl">
                                    {{ formatNumber(($invoice->getReturnedInvoice()?->amount ?? 0) - ($invoice->getReturnedInvoice()?->subtraction ?? 0)) }}
                                </div>
                                <div class="stat-desc text-indigo-400 dark:text-indigo-400/80">
                                    {{ __('Payable amount') }}</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <table class="table w-full">
                            <thead>
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
                                    <tr class="hover:bg-base-300">
                                        <td class="px-4 py-3">{{ localizeNumber($index + 1) }}</td>
                                        <td class="px-4 py-3">
                                            @if ($item->itemable)
                                                <a href="{{ route($item->itemable instanceof App\Models\Product ? 'products.show' : 'services.show', $item->itemable) }}"
                                                    class="link link-hover link-primary">
                                                    {{ $item->itemable->name }}
                                                </a>
                                            @else
                                                <span
                                                    class="text-gray-500 dark:text-slate-400">{{ __('Removed product/service') }}</span>
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
                                        <td colspan="8"
                                            class="px-4 py-6 text-center text-gray-500 dark:text-slate-400">
                                            {{ __('There are no items on this return invoice yet.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="8"
                                        class="px-4 py-3 text-right text-sm text-gray-600 dark:text-slate-300">
                                        {{ __('Total items: :count', ['count' => localizeNumber($invoice->getReturnedInvoice()?->items->count() ?? 0)]) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert bg-base-200">
                        <span>{{ __('This invoice has not any returned invoices.') }}</span>
                    </div>
                @endif
            @endif

            @if ($invoice->invoice_type === App\Enums\InvoiceType::BUY && !$isServiceBuy)
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
                                        <tr class="hover:bg-base-300">
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
                                                            <form method="POST"
                                                                action="{{ route('ancillary-costs.change-status', [$ancillaryCost, $ancillaryCost->status?->isApproved() ? 'unapprove' : 'approve']) }}"
                                                                class="inline-block">
                                                                @csrf
                                                                <button type="submit" x-data="{}"
                                                                    class="btn btn-xs {{ $ancillaryCost->status?->isApproved() ? 'btn-warning' : 'btn-success' }}">
                                                                    {{ __($ancillaryCost->status?->isApproved() ? 'Unapprove' : 'Approve') }}
                                                                </button>
                                                            </form>
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

                                                    @can('ancillary-costs.show')
                                                        <a href="{{ route('invoices.ancillary-costs.show', [$invoice, $ancillaryCost]) }}"
                                                            class="btn btn-xs btn-info">
                                                            {{ __('Show') }}
                                                        </a>
                                                    @endcan

                                                    @php
                                                        $editDeleteAllowed =
                                                            ($ancillaryEditDeleteStatus['allowed'] ?? false) &&
                                                            !$ancillaryCost->status?->isApproved();
                                                        $editDeleteDisabledReason = $ancillaryCost->status?->isApproved()
                                                            ? null
                                                            : $ancillaryEditDeleteStatus['reason'] ?? null;
                                                        $editTooltip = $ancillaryCost->status?->isApproved()
                                                            ? __('Unapprove the ancillary cost first to edit')
                                                            : $ancillaryEditDeleteStatus['reason'] ?? '';
                                                        $deleteTooltip = $ancillaryCost->status?->isApproved()
                                                            ? __('Unapprove the ancillary cost first to delete')
                                                            : $ancillaryEditDeleteStatus['reason'] ?? '';
                                                    @endphp

                                                    @can('ancillary-costs.edit')
                                                        @if ($editDeleteAllowed)
                                                            <a href="{{ route('invoices.ancillary-costs.edit', [$invoice, $ancillaryCost]) }}"
                                                                class="btn btn-xs btn-info">
                                                                {{ __('Edit') }}
                                                            </a>
                                                        @else
                                                            <span class="tooltip" data-tip="{{ $editTooltip }}">
                                                                <button
                                                                    class="btn btn-xs btn-info btn-disabled cursor-not-allowed">
                                                                    {{ __('Edit') }}
                                                                </button>
                                                            </span>
                                                        @endif
                                                    @endcan

                                                    @can('ancillary-costs.delete')
                                                        @if ($editDeleteAllowed)
                                                            <form class="inline-block"
                                                                action="{{ route('invoices.ancillary-costs.destroy', [$invoice, $ancillaryCost]) }}"
                                                                method="POST">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-xs btn-error">
                                                                    {{ __('Delete') }}
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="tooltip" data-tip="{{ $deleteTooltip }}">
                                                                <button
                                                                    class="btn btn-xs btn-error btn-disabled cursor-not-allowed">
                                                                    {{ __('Delete') }}
                                                                </button>
                                                            </span>
                                                        @endif
                                                    @endcan
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert bg-base-200">
                            <span>{{ __('No ancillary costs are attached to this invoice.') }}</span>
                        </div>
                    @endif
                    @can('ancillary-costs.create')
                        <div class="flex mt-2">
                            @if ($canCreateAncillaryCost)
                                <a href="{{ route('invoices.ancillary-costs.create', $invoice) }}"
                                    class="btn btn-primary">
                                    {{ __('Create Ancillary Cost') }}
                                </a>
                            @else
                                <span class="tooltip"
                                    data-tip="{{ __('Cannot add new ancillary cost for approved invoice because of COGS calculations.') }}">
                                    <button
                                        class="btn btn-primary btn-disabled cursor-not-allowed">{{ __('Create Ancillary Cost') }}</button>
                                </span>
                            @endif
                        </div>
                    @endcan
                </div>
            @endif

            <div>
                <div class="divider text-lg font-semibold">{{ __('Payments') }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div class="card bg-base-200 shadow">
                        <div class="card-body p-4">
                            <h3 class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                {{ __('Invoice Total') }}</h3>
                            <p class="text-lg font-bold">{{ formatNumber((float) $invoice->amount) }}</p>
                        </div>
                    </div>
                    <div class="card bg-base-200 shadow">
                        <div class="card-body p-4">
                            <h3 class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                {{ __('Paid Amount') }}</h3>
                            <p class="text-lg font-bold text-success">{{ formatNumber($paidAmount) }}</p>
                        </div>
                    </div>
                    <div class="card bg-base-200 shadow">
                        <div class="card-body p-4">
                            <h3 class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                {{ __('Remaining Amount') }}</h3>
                            <p class="text-lg font-bold {{ $remainingAmount > 0 ? 'text-error' : 'text-success' }}">
                                {{ formatNumber($remainingAmount) }}</p>
                        </div>
                    </div>
                </div>

                @if ($invoice->payments->isNotEmpty())
                    <div class="mt-4 overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3">{{ __('Date') }}</th>
                                    <th class="px-4 py-3">{{ __('Settlement Account') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Amount') }}</th>
                                    <th class="px-4 py-3">{{ __('Doc Number') }}</th>
                                    <th class="px-4 py-3">{{ __('Description') }}</th>
                                    <th class="px-4 py-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->payments as $payment)
                                    <tr class="hover:bg-base-300">
                                        <td class="px-4 py-3">
                                            {{ $payment->created_at ? formatDate($payment->created_at) : '—' }}</td>
                                        <td class="px-4 py-3">{{ $payment->settlementSubject()?->fullname() ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ formatNumber((float) $payment->amount) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($payment->document)
                                                @can('documents.show')
                                                    <a class="link"
                                                        href="{{ route('documents.show', $payment->document) }}">
                                                        {{ formatDocumentNumber($payment->document->number) }}
                                                    </a>
                                                @else
                                                    {{ formatDocumentNumber($payment->document->number) }}
                                                @endcan
                                            @else
                                                <span
                                                    class="badge badge-ghost">{{ __('No accounting document') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $payment->description ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-2 items-center">
                                                @if ($payment->document)
                                                    @can('invoices.payments.destroy-document')
                                                        <form class="m-0" method="POST"
                                                            action="{{ route('invoices.payments.destroy-document', [$invoice, $payment]) }}"
                                                            onsubmit="return confirm('{{ __('Remove this payment\'s accounting document? The payment will be kept but will no longer count toward the paid amount.') }}')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="btn btn-xs btn-warning">{{ __('Remove Document') }}</button>
                                                        </form>
                                                    @endcan
                                                @elseif ($payment->settlement_subject_id)
                                                    @can('invoices.payments.create-document')
                                                        <form class="m-0" method="POST"
                                                            action="{{ route('invoices.payments.create-document', [$invoice, $payment]) }}">
                                                            @csrf
                                                            <button type="submit"
                                                                class="btn btn-xs btn-success">{{ __('Create Document') }}</button>
                                                        </form>
                                                    @endcan
                                                @endif

                                                @can('invoices.payments.destroy')
                                                    <form class="m-0" method="POST"
                                                        action="{{ route('invoices.payments.destroy', [$invoice, $payment]) }}"
                                                        onsubmit="return confirm('{{ __('Are you sure you want to remove this payment? Its accounting document will be reversed.') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="btn btn-xs btn-error">{{ __('Delete') }}</button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert bg-base-200 shadow-sm">
                        <span>{{ __('No payments have been recorded for this invoice.') }}</span>
                    </div>
                @endif

                @can('invoices.payments.store')
                    <div class="flex mt-4">
                        @if ($paymentDecision->hasErrors())
                            <span class="tooltip"
                                data-tip="{{ $paymentDecision->messages->pluck('text')->implode(' ') }}">
                                <button
                                    class="btn btn-primary btn-disabled cursor-not-allowed">{{ __('Record Payment') }}</button>
                            </span>
                        @else
                            <button class="btn btn-primary gap-2" onclick="payment_modal.showModal()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('Record Payment') }}
                            </button>
                        @endif
                    </div>

                    <dialog id="payment_modal" class="modal">
                        <div class="modal-box">
                            <h3 class="text-lg font-bold mb-4">{{ __('Record Payment') }}</h3>
                            @php
                                $settlementGroups = $settlementSubjects
                                    ->groupBy(fn($subject) => $subject->parent?->name ?? __('Other'))
                                    ->map(
                                        fn($group, $label) => [
                                            'label' => (string) $label,
                                            'options' => $group
                                                ->map(
                                                    fn($subject) => [
                                                        'id' => $subject->id,
                                                        'name' => $subject->name,
                                                        'code' => $subject->formattedCode(),
                                                    ],
                                                )
                                                ->values(),
                                        ],
                                    )
                                    ->values();
                            @endphp
                            <form method="POST" action="{{ route('invoices.payments.store', $invoice) }}"
                                class="space-y-4">
                                @csrf
                                <div>
                                    <label class="label"><span
                                            class="label-text">{{ __('Settlement Account') }}</span></label>
                                    <div x-data="settlementSelect(@js($settlementGroups))" @click.outside="open = false" class="relative">
                                        <input type="hidden" name="subject_id" :value="selectedId">
                                        <button type="button" @click="toggle()"
                                            class="input input-bordered w-full flex items-center justify-between text-left">
                                            <span class="block truncate" :class="{ 'text-gray-400': !selectedLabel }"
                                                x-text="selectedLabel || '{{ __('Select an account') }}'"></span>
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform"
                                                :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 9l-5 5-5-5" />
                                            </svg>
                                        </button>
                                        <div x-show="open" x-cloak x-transition.opacity
                                            class="absolute left-0 right-0 z-[100] mt-1 bg-base-100 border border-base-300 rounded-box shadow-xl max-h-60 flex flex-col overflow-hidden">
                                            <div class="p-2 border-b border-base-200">
                                                <input x-ref="settlementSearch" x-model="search" type="text"
                                                    class="input input-sm w-full" placeholder="{{ __('Search') }}"
                                                    @click.stop>
                                            </div>
                                            <ul class="overflow-y-auto p-1">
                                                <template x-for="group in filteredGroups" :key="group.label">
                                                    <li>
                                                        <div class="px-3 pt-2 pb-1 text-xs font-semibold uppercase text-gray-500"
                                                            x-text="group.label"></div>
                                                        <template x-for="opt in group.options" :key="opt.id">
                                                            <div @click="select(opt)"
                                                                class="px-4 py-2 cursor-pointer hover:bg-base-200 rounded-btn flex justify-between items-center text-sm"
                                                                :class="{ 'bg-primary text-primary-content': selectedId === opt
                                                                        .id }">
                                                                <span class="block truncate" x-text="opt.name"></span>
                                                                <span class="text-xs text-gray-500 ms-2"
                                                                    x-text="opt.code"></span>
                                                            </div>
                                                        </template>
                                                    </li>
                                                </template>
                                                <li x-show="filteredGroups.length === 0"
                                                    class="p-4 text-center text-gray-500 italic text-sm">
                                                    {{ __('No bank or cash subjects are configured.') }}
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div x-data="{ amountInput: '{{ $remainingAmount }}' }">
                                    <label class="label"><span class="label-text">{{ __('Amount') }}</span></label>
                                    <x-text-input input_name="amount" input_class="input-bordered locale-number"
                                        input_value="{{ $remainingAmount }}" required x-model="amountInput"
                                        @input="amountInput = $store.utils.cleanupNumber($event.target.value)"
                                        x-effect="$el.value = $store.utils.convertToFarsi($store.utils.formatNumber(amountInput))" />
                                    <span class="text-xs text-gray-500">{{ __('Remaining Amount') }}:
                                        {{ formatNumber($remainingAmount) }}</span>
                                </div>
                                <div>
                                    <label class="label"><span class="label-text">{{ __('Date') }}</span></label>
                                    <x-text-input data-jdp input_name="date" autocomplete="off" readonly
                                        input_class="input-bordered" input_value="{{ convertToJalali(now(), true) }}" />
                                </div>
                                <div>
                                    <label class="label"><span
                                            class="label-text">{{ __('Reference Number') }}</span></label>
                                    <x-text-input input_name="reference_number" input_class="input-bordered"
                                        maxlength="20" />
                                </div>
                                <div>
                                    <label class="label"><span
                                            class="label-text">{{ __('Description') }}</span></label>
                                    <textarea name="description" class="textarea textarea-bordered w-full" rows="2"></textarea>
                                </div>
                                <div class="modal-action">
                                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                                    <button type="button" class="btn"
                                        onclick="payment_modal.close()">{{ __('Cancel') }}</button>
                                </div>
                            </form>
                        </div>
                        <form method="dialog" class="modal-backdrop">
                            <button>{{ __('close') }}</button>
                        </form>
                    </dialog>
                    @pushOnce('scripts')
                        <script type="module">
                            jalaliDatepicker.startWatch({
                                persianDigits: true,
                                container: '#payment_modal'
                            });
                        </script>
                    @endPushOnce
                @endcan
            </div>

            <div class="card-actions justify-between mt-4">
                <a href="{{ route('invoices.index', ['invoice_type' => $invoice->invoice_type, 'service_buy' => $isServiceBuy]) }}"
                    class="btn btn-ghost gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>

                <div class="flex flex-wrap gap-2">
                    @can('invoices.transfer')
                        @if ($fiscalYears->isNotEmpty())
                            <button type="button" onclick="document.getElementById('inv-transfer-modal').showModal()"
                                class="btn gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                {{ __('Transfer to another Fiscal Year') }}
                            </button>
                        @endif
                    @endcan

                    @php
                        $isSellWorkflow = $invoice->invoice_type === App\Enums\InvoiceType::SELL;
                        $canApprove =
                            ($isSellWorkflow ? false : $invoice->status->isPending()) ||
                            $invoice->status->isReadyToApprove() ||
                            $invoice->status->isUnapproved() ||
                            $invoice->status->isApprovedInactive();
                        $canUnapprove = $invoice->status->isApproved();
                        $isSettled = $invoice->status->isPartiallyPaid() || $invoice->status->isPaid();

                        $hasMoadianSuccess = $invoice->moadianHistories->contains(function ($history) {
                            $data = $history->data;

                            return strtoupper($data['status'] ?? '') === 'SUCCESS';
                        });

                        $canChangeStatus = ($canApprove || $canUnapprove) && !$hasMoadianSuccess;
                    @endphp

                    @can('invoices.send-moadian')
                        @if ($isMoadianSendable)
                            @if ($invoice->status->isApproved() && !$hasMoadianSuccess)
                                <form method="POST" action="{{ route('invoices.send-moadian', $invoice) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success">{{ __('Send Moadian') }}</button>
                                </form>
                            @elseif ($hasMoadianSuccess)
                                <span class="tooltip"
                                    data-tip="{{ __('This invoice has already been sent successfully to Moadian and cannot be sent again.') }}">
                                    <button class="btn btn-success btn-disabled cursor-not-allowed"
                                        title="{{ __('This invoice has already been sent successfully to Moadian and cannot be sent again.') }}">{{ __('Send Moadian') }}</button>
                                </span>
                            @else
                                <span class="tooltip"
                                    data-tip="{{ __('Approve the invoice first to send to moadian') }}">
                                    <button class="btn btn-error btn-disabled cursor-not-allowed"
                                        title="{{ __('Approve the invoice first to send to moadian') }}">{{ __('Send Moadian') }}</button>
                                </span>
                            @endif
                        @endif
                    @endcan

                    @can('invoices.void')
                        @if ($invoice->invoice_type === App\Enums\InvoiceType::SELL)
                            @if (
                                $invoice->status->isApproved() &&
                                    !$invoice->voidInvoice &&
                                    $invoice->getReturnInvoice()->isEmpty() &&
                                    $hasMoadianSuccess)
                                <a href="{{ route('invoices.void-form', $invoice) }}" class="btn btn-warning">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    {{ __('Void') }}
                                </a>
                            @else
                                @php
                                    $voidDisabledTip = $invoice->voidInvoice
                                        ? __('Invoice has voided already.')
                                        : ($invoice->getReturnInvoice()->isNotEmpty()
                                            ? __('Only approved sell invoices without return invoices can be voided.')
                                            : __('Invoice must be approved and sent to Moadian before voiding'));
                                @endphp
                                <span class="tooltip" data-tip="{{ $voidDisabledTip }}">
                                    <button class="btn btn-warning gap-2 btn-disabled cursor-not-allowed">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        {{ __('Void') }}
                                    </button>
                                </span>
                            @endif
                        @endif
                    @endcan

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
                            <form action="{{ route('invoices.change-status', [$invoice, 'ready_to_approve']) }}"
                                method="POST" class="inline-block">
                                @csrf
                                <button type="submit" class="btn btn-success">{{ __('Ready to approve') }}</button>
                            </form>
                        @endif

                        @if ($isSellWorkflow && $invoice->status->isPreInvoice())
                            <form action="{{ route('invoices.change-status', [$invoice, 'rejected']) }}" method="POST"
                                class="inline-block">
                                @csrf
                                <button type="submit" class="btn btn-error gap-2">{{ __('Reject') }}</button>
                            </form>
                        @endif

                        @if ($canChangeStatus)
                            @if ($changeStatusValidation->hasErrors())
                                <a data-tip="{{ $changeStatusValidation->toText() }}"
                                    href="{{ route('invoices.conflicts', $invoice) }}"
                                    class="btn btn-accent inline-flex tooltip">
                                    {{ __('Fix Conflict') }}
                                </a>
                            @else
                                <form
                                    action="{{ route('invoices.change-status', [$invoice, $canUnapprove ? 'unapproved' : 'approved']) }}{{ $changeStatusValidation->hasWarning() ? '?confirm=1' : '' }}"
                                    method="POST"
                                    class="inline-block {{ $changeStatusValidation->hasWarning() ? 'change-status-form' : '' }}">
                                    @csrf
                                    <button type="submit" x-data="{}"
                                        data-tip="{{ $changeStatusValidation->toText() }}"
                                        class="btn inline-flex {{ $canUnapprove ? 'btn-warning' : 'btn-success' }} {{ $canApprove && $changeStatusValidation->hasWarning() ? ' btn-outline ' : '' }}">
                                        {{ $canUnapprove ? __('Unapprove') : __('Approve') }}
                                    </button>
                                </form>
                            @endif
                        @elseif ($isSettled)
                            <span class="tooltip"
                                data-tip="{{ __('A paid or partially paid invoice cannot change its status until its payments (or their payment documents) are removed.') }}">
                                <button
                                    class="btn btn-warning btn-disabled cursor-not-allowed">{{ __('Unapprove') }}</button>
                            </span>
                        @endif
                    @endcan

                    @if (!$invoice->status->isApprovedOrSettled() && !$invoice->invoice_type->isVoid() && !$hasMoadianSuccess)
                        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-primary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Edit invoice') }}
                        </a>
                    @else
                        <span class="tooltip"
                            data-tip="{{ $invoice->invoice_type->isVoid() ? __('Editing is not allowed for void invoices.') : __('Editing is not allowed for approved invoices.') }}">
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

    @can('invoices.transfer')
        @if ($fiscalYears->isNotEmpty())
            <dialog id="inv-transfer-modal" class="modal">
                <div class="modal-box">
                    <h3 class="font-bold text-lg">{{ __('Transfer to another Fiscal Year') }}</h3>
                    <form action="{{ route('invoices.transfer', $invoice) }}" method="POST" class="mt-4">
                        @csrf
                        <div class="form-control w-full">
                            <label class="label">
                                <span class="label-text font-semibold">{{ __('Target Fiscal Year') }}</span>
                            </label>
                            <select name="target_company_id" class="select select-bordered w-full" required>
                                <option value="">{{ __('-- Select fiscal year --') }}</option>
                                @foreach ($fiscalYears as $year)
                                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="modal-action">
                            <button type="button" onclick="document.getElementById('inv-transfer-modal').close()"
                                class="btn">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary gap-2">{{ __('Transfer') }}</button>
                        </div>
                    </form>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button aria-label="{{ __('Close') }}"></button>
                </form>
            </dialog>
        @endif
    @endcan

    <script>
        function settlementSelect(groups) {
            return {
                open: false,
                search: '',
                groups: groups || [],
                selectedId: '',
                selectedLabel: '',
                toggle() {
                    this.open = !this.open;
                    if (this.open) {
                        this.$nextTick(() => this.$refs.settlementSearch?.focus());
                    }
                },
                get filteredGroups() {
                    const q = this.search.trim().toLowerCase();
                    if (!q) return this.groups;

                    return this.groups
                        .map(group => ({
                            label: group.label,
                            options: group.options.filter(opt => (opt.name + ' ' + opt.code).toLowerCase()
                                .includes(q)),
                        }))
                        .filter(group => group.options.length > 0);
                },
                select(opt) {
                    this.selectedId = opt.id;
                    this.selectedLabel = opt.name;
                    this.open = false;
                    this.search = '';
                },
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const changeStatusForms = document.querySelectorAll('.change-status-form');

            changeStatusForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (confirm(
                            '{{ __('This invoice has warnings for change its status, are you sure to change status?') }}'
                        )) {
                        this.submit();
                    }
                });
            });
        });
    </script>

</x-app-layout>
