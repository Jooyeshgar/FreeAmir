<x-app-layout :title="__('Invoice') .
    ' ' .
    $invoice->invoice_type->label() .
    ' #' .
    formatDocumentNumber($invoice->number ?? $invoice->id)">
    <div class="invoice-show card w-full bg-base-100 text-sm lg:text-base shadow-xl">
        <div
            class="card-header bg-gradient-to-r from-blue-50 to-indigo-50 dark:text-white dark:from-gray-800 dark:to-gray-700 px-4 py-3 sm:px-5 lg:px-6 lg:py-4 rounded-t-2xl border-b-2 border-primary/20">
            <div>
                <h2 class="text-lg sm:text-xl lg:text-2xl break-words font-bold text-gray-800 dark:text-white">
                    {{ __('Invoice') }} {{ $invoice->invoice_type->label() }}
                    #{{ formatDocumentNumber($invoice->number ?? $invoice->id) }} - {{ $invoice->title }}
                </h2>
                <p class="mt-1 text-xs sm:text-sm lg:text-base sm:text-end">
                    {{ __('Issued on :date', ['date' => $invoice->date ? formatDate($invoice->date) : __('Unknown')]) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <x-invoice-bookmark :invoice="$invoice" />
                <span class="badge badge-sm md:badge-md lg:badge-lg h-auto max-w-full whitespace-normal break-words py-1 badge-primary gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h10a2 2 0 012 2v4a2 2 0 01-.586 1.414l-7 7a2 2 0 01-2.828 0l-4.586-4.586A2 2 0 014 12V5a2 2 0 012-2z" />
                    </svg>
                    {{ $invoice->invoice_type->label() }}
                </span>
                <span
                    class="badge badge-sm md:badge-md lg:badge-lg h-auto max-w-full whitespace-normal break-words py-1 {{ $invoice->status->isApproved() ? 'badge-primary' : ($invoice->status->isPaid() ? 'badge-success' : ($invoice->status->isPartiallyPaid() ? 'badge-info' : ($invoice->status->isRejected() ? 'badge-error' : 'badge-warning'))) }} gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $invoice->status->label() }}
                </span>

                @if ($invoice->warehouse)
                    <a href="{{ route('warehouses.show', $invoice->warehouse) }}"
                        class="badge badge-sm md:badge-md lg:badge-lg h-auto max-w-full whitespace-normal break-words py-1 badge-secondary gap-2 link">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 9h.01M15 9h.01M9 13h.01M15 13h.01" />
                        </svg>
                        {{ $invoice->warehouse->name }}
                    </a>
                @endif

                @if ($isMoadianSendable && $invoice->status->isApprovedOrSettled())
                    <a class="badge badge-sm md:badge-md lg:badge-lg h-auto max-w-full whitespace-normal break-words py-1 link"
                        href="{{ route('invoices.moadian-histories.show', $invoice) }}">{{ __('Moadian Histories') }}</a>
                @endif

                @php
                    $voidInvoice = $invoice->voidInvoice;
                    $voidedInvoice = $invoice->voidedInvoice;
                @endphp

                @if ($voidInvoice || $voidedInvoice)
                    <span class="badge badge-sm md:badge-md lg:badge-lg h-auto max-w-full whitespace-normal break-words py-1 badge-error gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
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

        <div class="card-body min-w-0 gap-3 p-4 sm:p-5 lg:p-8 space-y-5 lg:space-y-8">
            <x-show-message-bags />

            @php
                $fiscalYearClosed = $invoice->fiscalYear?->isClosed() ?? false;
                $fiscalYearClosedMessage = __('This fiscal year is already closed.');
            @endphp

            @if ($fiscalYearClosed)
                <div class="alert alert-warning shadow-sm">
                    <span>{{ $fiscalYearClosedMessage }} {{ __('Invoice and settlement operations are read-only.') }}</span>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                <div
                    class="stats shadow bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200/60 dark:from-slate-800 dark:to-sky-950/40 dark:border-sky-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                    <div class="stat min-w-0 px-4 py-3 lg:px-6 lg:py-4">
                        <div class="stat-title whitespace-normal text-xs md:text-sm text-blue-500 dark:text-sky-300">{{ __('Subtotal') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value whitespace-normal text-blue-600 dark:text-sky-200 text-xl md:text-2xl lg:text-3xl break-all tabular-nums">
                            {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0)) }}
                        </div>
                        <div class="stat-desc whitespace-normal text-blue-400 dark:text-sky-400/80">{{ __('Before discounts and tax') }}
                        </div>
                    </div>
                </div>

                <div
                    class="stats shadow bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60 dark:from-slate-800 dark:to-amber-950/40 dark:border-amber-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                    <div class="stat min-w-0 px-4 py-3 lg:px-6 lg:py-4">
                        <div class="stat-title whitespace-normal text-xs md:text-sm text-amber-500 dark:text-amber-300">{{ __('Discounts') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value whitespace-normal text-amber-600 dark:text-amber-200 text-xl md:text-2xl lg:text-3xl break-all tabular-nums">
                            {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->unit_discount ?? 0), 0)) }}
                        </div>
                        <div class="stat-desc whitespace-normal text-amber-400 dark:text-amber-400/80">{{ __('Total deductions') }}</div>
                    </div>
                </div>

                <div
                    class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60 dark:from-slate-800 dark:to-emerald-950/40 dark:border-emerald-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                    <div class="stat min-w-0 px-4 py-3 lg:px-6 lg:py-4">
                        <div class="stat-title whitespace-normal text-xs md:text-sm text-emerald-500 dark:text-emerald-300">{{ __('VAT') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value whitespace-normal text-emerald-600 dark:text-emerald-200 text-xl md:text-2xl lg:text-3xl break-all tabular-nums">
                            {{ formatNumber($invoice->items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0)) }}
                        </div>
                        <div class="stat-desc whitespace-normal text-emerald-400 dark:text-emerald-400/80">{{ __('Collected tax') }}
                        </div>
                    </div>
                </div>

                <div
                    class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60 dark:from-slate-800 dark:to-indigo-950/40 dark:border-indigo-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                    <div class="stat min-w-0 px-4 py-3 lg:px-6 lg:py-4">
                        <div class="stat-title whitespace-normal text-xs md:text-sm text-indigo-500 dark:text-indigo-300">{{ __('Grand total') }}
                            ({{ config('amir.currency') ?? __('Rial') }})</div>
                        <div class="stat-value whitespace-normal text-indigo-600 dark:text-indigo-200 text-xl md:text-2xl lg:text-3xl break-all tabular-nums">
                            {{ formatNumber(($invoice->amount ?? 0) - ($invoice->subtraction ?? 0)) }}</div>
                        <div class="stat-desc whitespace-normal text-indigo-400 dark:text-indigo-400/80">{{ __('Payable amount') }}</div>
                    </div>
                </div>

                @if ($changeStatusValidation->hasErrors() || $changeStatusValidation->hasWarning())
                    <div class="stats bg-base-100 gap-2 shadow col-span-full">
                        <x-show-messages :message="$changeStatusValidation->toDetailText()" type="alert" />
                    </div>
                @endif

            </div>
            @if ($invoice->description)
                <div>
                    <div class="divider text-sm md:text-base lg:text-lg whitespace-nowrap font-semibold">{{ __('Notes') }}</div>
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
                <div class="divider text-sm md:text-base lg:text-lg whitespace-nowrap font-semibold">{{ __('Customer Details') }}</div>
                @if ($invoice->customer)
                    <div class="grid [&>*]:min-w-0 grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Customer') }}</h3>
                                <p class="text-sm md:text-base lg:text-lg break-words font-semibold text-gray-800 dark:text-slate-100">
                                    {{ $invoice->customer->name }}</p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Phone') }}</h3>
                                <p class="text-sm md:text-base lg:text-lg break-words font-semibold text-gray-800 dark:text-slate-100">
                                    {{ $invoice->customer->phone ? localizeNumber($invoice->customer->phone) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Mobile') }}</h3>
                                <p class="text-sm md:text-base lg:text-lg break-words font-semibold text-gray-800 dark:text-slate-100">
                                    {{ $invoice->customer->mobile ? localizeNumber($invoice->customer->mobile) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Economic code') }}</h3>
                                <p class="text-sm md:text-base lg:text-lg break-words font-semibold text-gray-800 dark:text-slate-100">
                                    {{ $invoice->customer->ecnmcs_code ? localizeNumber($invoice->customer->ecnmcs_code) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow">
                            <div class="card-body p-4">
                                <h3
                                    class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                    {{ __('Postal code') }}</h3>
                                <p class="text-sm md:text-base lg:text-lg break-words font-semibold text-gray-800 dark:text-slate-100">
                                    {{ $invoice->customer->postal_code ? localizeNumber($invoice->customer->postal_code) : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="card bg-base-200 shadow md:col-span-2 lg:col-span-5">
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
                <div class="divider text-sm md:text-base lg:text-lg whitespace-nowrap font-semibold">{{ __('Items') }}</div>
                <div class="min-w-0 overflow-x-auto">
                    <table class="table table-xs md:table-sm lg:table-md w-full">
                        <thead>
                            <tr>
                                <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">#</th>
                                <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">
                                    {{ $invoice->invoice_type == App\Enums\InvoiceType::BUY ? __('Product') : __('Product/Service') }}
                                </th>
                                <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Description') }}</th>
                                <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Quantity') }}</th>
                                <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Unit price') }}</th>
                                <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Discount') }}</th>
                                <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('VAT') }}</th>
                                <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($invoice->items as $index => $item)
                                <tr class="hover:bg-base-300">
                                    <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ localizeNumber($index + 1) }}</td>
                                    <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
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
                                    <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ $item->description }}</td>
                                    <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatNumber($item->quantity ?? 0) }}</td>
                                    <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatNumber($item->unit_price ?? 0) }}</td>
                                    <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatNumber($item->unit_discount ?? 0) }}
                                    </td>
                                    <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatNumber($item->vat ?? 0) }}</td>
                                    <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">
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
                                    class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right text-sm text-gray-600 dark:text-slate-300">
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
                        <div class="divider text-sm md:text-base lg:text-lg whitespace-nowrap font-semibold">{{ __('Invoice') }}
                            {{ __('Return from') }}{{ $invoice->invoice_type->label() }}</div>
                        <div class="min-w-0 overflow-x-auto">
                            <table class="table table-xs md:table-sm lg:table-md w-full">
                                <thead>
                                    <tr>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">#</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Invoice Number') }}</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Date') }}</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($invoice->getReturnInvoice() as $index => $returnedInvoice)
                                        <tr class="hover:bg-base-300">
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ localizeNumber($index + 1) }}</td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
                                                <a href="{{ route('invoices.show', $returnedInvoice) }}"
                                                    class="link link-hover link-primary">
                                                    {{ formatDocumentNumber($returnedInvoice->number) }}
                                                </a>
                                            </td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatDate($returnedInvoice->date) }}
                                            </td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">
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
                            <div class="mt-2 text-right {{ $invoice->status->isApprovedOrSettled() ? '' : 'tooltip' }}"
                                data-tip="{{ $invoice->status->isApprovedOrSettled() ? '' : __('Only approved invoices can be returned.') }}">
                                @if ($invoice->invoice_type === App\Enums\InvoiceType::BUY)
                                    <a href="{{ route('invoices.create', ['invoice_type' => 'return_buy', 'returned_invoice_id' => $invoice->id, 'service_buy' => $isServiceBuy ? '1' : null]) }}"
                                        class="btn btn-sm lg:btn-md btn-primary {{ $invoice->status->isApprovedOrSettled() && !$fiscalYearClosed ? '' : 'btn-disabled' }}"
                                        @disabled($fiscalYearClosed) title="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : '' }}">
                                        {{ __('Create return buy invoice') }}
                                    </a>
                                @elseif ($invoice->invoice_type === App\Enums\InvoiceType::SELL)
                                    <a href="{{ route('invoices.create', ['invoice_type' => 'return_sell', 'returned_invoice_id' => $invoice->id]) }}"
                                        class="btn btn-sm lg:btn-md btn-primary {{ $invoice->status->isApprovedOrSettled() && !$fiscalYearClosed ? '' : 'btn-disabled' }}"
                                        @disabled($fiscalYearClosed) title="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : '' }}">
                                        {{ __('Create return sell invoice') }}
                                    </a>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            @endif

            @if (in_array($invoice->invoice_type, [App\Enums\InvoiceType::RETURN_BUY, App\Enums\InvoiceType::RETURN_SELL]))
                <div class="divider text-sm md:text-base lg:text-lg whitespace-nowrap font-semibold">{{ __('Invoice') }}
                    {{ $invoice->getReturnedInvoice()?->invoice_type->label() }}</div>
                @if ($invoice->getReturnedInvoice())
                    <div class="grid [&>*]:min-w-0 grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
                        <div class="card bg-base-200">
                            <div class="card-body p-4">
                                <h3 class="card-title text-sm font-medium text-gray-500 dark:text-slate-300">
                                    {{ __('Title') }}
                                    {{ __('Invoice') }}</h3>
                                <p class="text-sm md:text-base lg:text-lg break-words font-semibold text-gray-800 dark:text-slate-100">
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
                            <div class="stat min-w-0 px-4 py-3 lg:px-6 lg:py-4">
                                <div class="stat-title whitespace-normal text-xs md:text-sm text-blue-500 dark:text-sky-300">{{ __('Subtotal') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value whitespace-normal text-blue-600 dark:text-sky-200 text-xl md:text-2xl lg:text-3xl break-all tabular-nums">
                                    {{ formatNumber($invoice->getReturnedInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->quantity ?? 0) * ($item->unit_price ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc whitespace-normal text-blue-400 dark:text-sky-400/80">
                                    {{ __('Before discounts and tax') }}</div>
                            </div>
                        </div>

                        <div
                            class="stats shadow bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200/60 dark:from-slate-800 dark:to-amber-950/40 dark:border-amber-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                            <div class="stat min-w-0 px-4 py-3 lg:px-6 lg:py-4">
                                <div class="stat-title whitespace-normal text-xs md:text-sm text-amber-500 dark:text-amber-300">{{ __('Discounts') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value whitespace-normal text-amber-600 dark:text-amber-200 text-xl md:text-2xl lg:text-3xl break-all tabular-nums">
                                    {{ formatNumber($invoice->getReturnedInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->unit_discount ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc whitespace-normal text-amber-400 dark:text-amber-400/80">
                                    {{ __('Total deductions') }}</div>
                            </div>
                        </div>

                        <div
                            class="stats shadow bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200/60 dark:from-slate-800 dark:to-emerald-950/40 dark:border-emerald-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                            <div class="stat min-w-0 px-4 py-3 lg:px-6 lg:py-4">
                                <div class="stat-title whitespace-normal text-xs md:text-sm text-emerald-500 dark:text-emerald-300">{{ __('VAT') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value whitespace-normal text-emerald-600 dark:text-emerald-200 text-xl md:text-2xl lg:text-3xl break-all tabular-nums">
                                    {{ formatNumber($invoice->getReturnedInvoice()?->items->reduce(fn($carry, $item) => $carry + ($item->vat ?? 0), 0)) }}
                                </div>
                                <div class="stat-desc whitespace-normal text-emerald-400 dark:text-emerald-400/80">
                                    {{ __('Collected tax') }}</div>
                            </div>
                        </div>

                        <div
                            class="stats shadow bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200/60 dark:from-slate-800 dark:to-indigo-950/40 dark:border-indigo-500/20 dark:shadow-none dark:ring-1 dark:ring-white/5">
                            <div class="stat min-w-0 px-4 py-3 lg:px-6 lg:py-4">
                                <div class="stat-title whitespace-normal text-xs md:text-sm text-indigo-500 dark:text-indigo-300">{{ __('Grand total') }}
                                    ({{ config('amir.currency') ?? __('Rial') }})</div>
                                <div class="stat-value whitespace-normal text-indigo-600 dark:text-indigo-200 text-xl md:text-2xl lg:text-3xl break-all tabular-nums">
                                    {{ formatNumber(($invoice->getReturnedInvoice()?->amount ?? 0) - ($invoice->getReturnedInvoice()?->subtraction ?? 0)) }}
                                </div>
                                <div class="stat-desc whitespace-normal text-indigo-400 dark:text-indigo-400/80">
                                    {{ __('Payable amount') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="min-w-0 overflow-x-auto">
                        <table class="table table-xs md:table-sm lg:table-md w-full">
                            <thead>
                                <tr>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">#</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">
                                        {{ $invoice->invoice_type == App\Enums\InvoiceType::BUY ? __('Product') : __('Product/Service') }}
                                    </th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Description') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Quantity') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Unit price') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Discount') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('VAT') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($invoice->getReturnedInvoice()?->items as $index => $item)
                                    <tr class="hover:bg-base-300">
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ localizeNumber($index + 1) }}</td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
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
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ $item->description }}</td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatNumber($item->quantity ?? 0) }}
                                        </td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatNumber($item->unit_price ?? 0) }}
                                        </td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">
                                            {{ formatNumber($item->unit_discount ?? 0) }}</td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatNumber($item->vat ?? 0) }}</td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">
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
                                        class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right text-sm text-gray-600 dark:text-slate-300">
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
                    <div class="divider text-sm md:text-base lg:text-lg whitespace-nowrap font-semibold">{{ __('Ancillary Costs') }}</div>
                    @if ($invoice->ancillaryCosts->isNotEmpty())
                        <div class="mt-4 min-w-0 overflow-x-auto">
                            <table class="table table-xs md:table-sm lg:table-md w-full">
                                <thead>
                                    <tr>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Ancillary Cost Number') }}</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Doc Number') }}</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Cost Type') }}</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Status') }}</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Amount') }}</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('VAT') }}</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Total') }}</th>
                                        <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoice->ancillaryCosts as $ancillaryCost)
                                        <tr class="hover:bg-base-300">
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
                                                <a class="link" href="{{ route('invoices.ancillary-costs.show', [$invoice, $ancillaryCost]) }}">
                                                    {{ formatDocumentNumber($ancillaryCost->number) }}
                                                </a>
                                            </td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
                                                @if ($ancillaryCost->document_id)
                                                    @can('documents.show')
                                                        <a href="{{ route('documents.show', $ancillaryCost->document_id) }}">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                        </a>&nbsp;
                                                        <a class="link" href="{{ route('documents.edit', $ancillaryCost->document_id) }}">
                                                            {{ formatDocumentNumber($ancillaryCost->document?->number ?? $ancillaryCost->document_id) }}</a>
                                                    @else
                                                        <span class="text-gray-500">{{ formatDocumentNumber($ancillaryCost->document?->number ?? $ancillaryCost->document_id) }}</span>
                                                    @endcan
                                                @endif
                                            </td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ $ancillaryCost->type?->label() ?? '—' }}</td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ $ancillaryCost->status?->label() ?? '—' }}</td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">
                                                {{ formatNumber((float) ($ancillaryCost->amount ?? 0) - (float) ($ancillaryCost->vat ?? 0)) }}
                                            </td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">
                                                {{ formatNumber((float) ($ancillaryCost->vat ?? 0)) }}</td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">
                                                {{ formatNumber((float) ($ancillaryCost->amount ?? 0)) }}</td>
                                            <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
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
                                                        @if (($ancillaryChangeStatusValidation['allowed'] ?? false) && !$fiscalYearClosed)
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
                                                                    class="btn btn-xs {{ $ancillaryCost->status?->isApproved() ? 'btn-warning' : 'btn-success' }} btn-disabled cursor-not-allowed" disabled title="{{ $ancillaryChangeStatusValidation['reason'] ?? '' }}">
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
                                                            !$ancillaryCost->status?->isApproved() &&
                                                            !$fiscalYearClosed;
                                                        $editDeleteDisabledReason = $ancillaryCost->status?->isApproved()
                                                            ? null
                                                            : $ancillaryEditDeleteStatus['reason'] ?? null;
                                                        $editTooltip = $fiscalYearClosed
                                                            ? $fiscalYearClosedMessage
                                                            : ($ancillaryCost->status?->isApproved()
                                                            ? __('Unapprove the ancillary cost first to edit')
                                                            : $ancillaryEditDeleteStatus['reason'] ?? '');
                                                        $deleteTooltip = $fiscalYearClosed
                                                            ? $fiscalYearClosedMessage
                                                            : ($ancillaryCost->status?->isApproved()
                                                            ? __('Unapprove the ancillary cost first to delete')
                                                            : $ancillaryEditDeleteStatus['reason'] ?? '');
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
                                                                    class="btn btn-xs btn-info btn-disabled cursor-not-allowed" disabled title="{{ $editTooltip }}">
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
                                                                    class="btn btn-xs btn-error btn-disabled cursor-not-allowed" disabled title="{{ $deleteTooltip }}">
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
                            @if ($canCreateAncillaryCost && !$fiscalYearClosed)
                                <a href="{{ route('invoices.ancillary-costs.create', $invoice) }}"
                                    class="btn btn-sm lg:btn-md btn-primary">
                                    {{ __('Create Ancillary Cost') }}
                                </a>
                            @else
                                <span class="tooltip"
                                    data-tip="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : __('Cannot add new ancillary cost for approved invoice because of COGS calculations.') }}">
                                    <button
                                        class="btn btn-sm lg:btn-md btn-primary btn-disabled cursor-not-allowed">{{ __('Create Ancillary Cost') }}</button>
                                </span>
                            @endif
                        </div>
                    @endcan
                </div>
            @endif

            <div>
                <div class="divider text-sm md:text-base lg:text-lg whitespace-nowrap font-semibold">{{ __('Payments') }}</div>
                <div class="grid [&>*]:min-w-0 grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                    <div class="card bg-base-200 shadow">
                        <div class="card-body p-4">
                            <h3 class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                {{ __('Invoice Total') }}</h3>
                            <p class="text-sm md:text-base lg:text-lg break-words font-bold">{{ formatNumber((float) $invoice->amount) }}</p>
                        </div>
                    </div>
                    <div class="card bg-base-200 shadow">
                        <div class="card-body p-4">
                            <h3 class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                {{ __('Paid Amount') }}</h3>
                            <p class="text-sm md:text-base lg:text-lg break-words font-bold text-success">{{ formatNumber($paidAmount) }}</p>
                        </div>
                    </div>
                    <div class="card bg-base-200 shadow">
                        <div class="card-body p-4">
                            <h3 class="card-title text-xs uppercase tracking-wide text-gray-500 dark:text-slate-300">
                                {{ __('Remaining Amount') }}</h3>
                            <p class="text-sm md:text-base lg:text-lg break-words font-bold {{ $remainingAmount > 0 ? 'text-error' : 'text-success' }}">
                                {{ formatNumber($remainingAmount) }}</p>
                        </div>
                    </div>
                </div>

                @if ($invoice->payments->isNotEmpty())
                    <div class="mt-4 overflow-x-auto">
                        <table class="table table-xs md:table-sm lg:table-md w-full">
                            <thead>
                                <tr>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Date') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Settlement Account') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Amount') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ __('Bank service fee') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Doc Number') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Description') }}</th>
                                    <th class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->payments as $payment)
                                    <tr class="hover:bg-base-300">
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
                                            {{ $payment->date ? formatDate($payment->date) : '—' }}</td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
                                            @if($payment->cheque)
                                                @can('cheques.show')
                                                    <a class="link link-primary" href="{{ route('cheques.show', $payment->cheque) }}">
                                                        {{ __('Cheque number') }}: {{ localizeNumber($payment->cheque->cheque_number) ?: '—' }}
                                                    </a>
                                                @else
                                                    {{ __('Cheque number') }}: {{ localizeNumber($payment->cheque->cheque_number) ?: '—' }}
                                                @endcan
                                                <div class="text-xs opacity-60">
                                                    {{ __('Cheque serial') }}: {{ localizeNumber($payment->cheque->serial) ?: '—' }}
                                                </div>
                                                <span class="badge badge-outline badge-sm ms-1">{{ $payment->cheque->status->label() }}</span>
                                            @else
                                                {{ $payment->settlementSubject()?->fullname() ?? '—' }}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatNumber((float) $payment->amount) }}
                                        </td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3 text-right">{{ formatNumber((float) $payment->bank_service_fee) }}</td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
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
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">{{ $payment->description ?? '—' }}</td>
                                        <td class="px-2 py-2 md:px-3 lg:px-4 lg:py-3">
                                            <div class="flex flex-wrap gap-2 items-center">
                                                @if ($payment->document && !$payment->cheque_id)
                                                    @can('invoices.payments.destroy-document')
                                                        <form class="m-0" method="POST"
                                                            action="{{ route('invoices.payments.destroy-document', [$invoice, $payment]) }}"
                                                            onsubmit="return confirm('{{ __('Remove this payment\'s accounting document? The payment will be kept but will no longer count toward the paid amount.') }}')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" @disabled($fiscalYearClosed)
                                                                class="btn btn-xs btn-warning {{ $fiscalYearClosed ? 'btn-disabled cursor-not-allowed' : '' }}"
                                                                title="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : '' }}">{{ __('Remove Document') }}</button>
                                                        </form>
                                                    @endcan
                                                @elseif (!$payment->cheque_id && $payment->settlement_subject_id)
                                                    @can('invoices.payments.create-document')
                                                        <form class="m-0" method="POST"
                                                            action="{{ route('invoices.payments.create-document', [$invoice, $payment]) }}">
                                                            @csrf
                                                            <button type="submit" @disabled($fiscalYearClosed)
                                                                class="btn btn-xs btn-success {{ $fiscalYearClosed ? 'btn-disabled cursor-not-allowed' : '' }}"
                                                                title="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : '' }}">{{ __('Create Document') }}</button>
                                                        </form>
                                                    @endcan
                                                @endif

                                                @if (!$payment->cheque_id)
                                                    @can('invoices.payments.update')
                                                        <button type="button" x-data="{}" class="btn btn-xs btn-outline" data-payment-id="{{ $payment->id }}"
                                                            @disabled($fiscalYearClosed) title="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : '' }}"
                                                            @click="$dispatch('invoice-payment-edit', {
                                                                id: @js($payment->id),
                                                                amount: @js((string) $payment->amount),
                                                                bankServiceFee: @js((string) $payment->bank_service_fee),
                                                                subjectId: @js($payment->settlement_subject_id ?? $payment->settlementSubject()?->id),
                                                                subjectName: @js($payment->settlementSubject()?->name),
                                                                availableAmount: @js((string) ($remainingAmount + ($payment->document_id ? (float) $payment->amount : 0))),
                                                                date: @js($payment->date ? convertToJalali($payment->date, true) : ''),
                                                                referenceNumber: @js($payment->reference_number ?? ''),
                                                                description: @js($payment->description ?? '')
                                                            })">{{ __('Edit') }}</button>
                                                    @endcan
                                                @endif
                                                @can('invoices.payments.destroy')
                                                    <form class="m-0" method="POST" action="{{ route('invoices.payments.destroy', [$invoice, $payment]) }}"
                                                        onsubmit="return confirm('{{ __('Are you sure you want to remove this payment? Its accounting document will be reversed.') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" @disabled($fiscalYearClosed)
                                                            class="btn btn-xs btn-error {{ $fiscalYearClosed ? 'btn-disabled cursor-not-allowed' : '' }}"
                                                            title="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : '' }}">{{ __('Delete') }}</button>
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

                @if (auth()->user()->can('invoices.payments.store') || auth()->user()->can('invoices.payments.update'))
                    @can('invoices.payments.store')
                        <div class="flex flex-wrap gap-2 mt-4">
                        @if ($fiscalYearClosed || $paymentDecision->hasErrors())
                            <span class="tooltip"
                                data-tip="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : $paymentDecision->messages->pluck('text')->implode(' ') }}">
                                <button
                                    class="btn btn-sm lg:btn-md btn-primary btn-disabled cursor-not-allowed">{{ __('Record Payment') }}</button>
                            </span>
                        @else
                            <button type="button" class="btn btn-sm lg:btn-md btn-primary gap-2"
                                onclick="window.dispatchEvent(new CustomEvent('invoice-payment-create')); payment_modal.showModal()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('Record Payment') }}
                            </button>
                            @if($chequeDirection)
                                @can('invoices.payments.store-cheque')
                                    <button type="button" @disabled($fiscalYearClosed)
                                        class="btn btn-sm lg:btn-md btn-secondary gap-2 {{ $fiscalYearClosed ? 'btn-disabled cursor-not-allowed' : '' }}"
                                        title="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : '' }}" onclick="cheque_payment_modal.showModal()">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 7h18M5 11h14M7 15h4m-6 4h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2Z" />
                                        </svg>
                                        {{ __('Pay by cheque') }}
                                    </button>
                                @endcan
                            @endif
                        @endif
                        </div>
                    @endcan

                    @php
                        $editingPayment = $invoice->payments->firstWhere('id', old('payment_id'));
                        $paymentModalState = [
                            'amount' => old('amount', $editingPayment?->amount ?? $remainingAmount),
                            'bankServiceFee' => old('bank_service_fee', $editingPayment?->bank_service_fee ?? '0'),
                            'subjectId' => old('subject_id', $editingPayment?->settlement_subject_id ?? $editingPayment?->settlementSubject()?->id ?? ''),
                            'subjectName' => $paymentModalSubject?->name ?? $editingPayment?->settlementSubject()?->name ?? '',
                            'date' => old('date', $editingPayment?->date ? convertToJalali($editingPayment->date, true) : convertToJalali(now(), true)),
                            'referenceNumber' => old('reference_number', $editingPayment?->reference_number ?? ''),
                            'description' => old('description', $editingPayment?->description ?? ''),
                            'paymentId' => old('payment_id', ''),
                            'availableAmount' => $editingPayment
                                ? $remainingAmount + ($editingPayment->document_id ? (float) $editingPayment->amount : 0)
                                : $remainingAmount,
                            'editing' => old('payment_mode') === 'edit',
                            'restore' => old('payment_mode') !== null,
                            'closed' => $fiscalYearClosed,
                        ];
                    @endphp
                    <dialog id="payment_modal" class="modal" x-data="invoicePaymentForm(@js($paymentModalState), @js(route('invoices.payments.store', $invoice)), @js(route('invoices.payments.update', [$invoice, '__PAYMENT__'])))" x-on:invoice-payment-edit.window="edit($event.detail)" x-on:invoice-payment-create.window="create()" x-init="restore()">
                        <div class="modal-box">
                            <h3 class="text-sm md:text-base lg:text-lg break-words font-bold mb-4" x-text="editing ? '{{ __('Edit Payment') }}' : '{{ __('Record Payment') }}'"></h3>
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
                            <form method="POST" :action="action" class="space-y-4" @submit="if (@js($fiscalYearClosed)) $event.preventDefault()">
                                @csrf
                                <input type="hidden" name="_method" value="PUT" :disabled="!editing">
                                <input type="hidden" name="payment_id" :value="paymentId">
                                <input type="hidden" name="payment_mode" :value="editing ? 'edit' : 'create'">
                                <div>
                                    <label class="label"><span
                                            class="label-text">{{ __('Settlement Account') }}</span></label>
                                    <div x-data="settlementSelect(@js($settlementGroups), @js(route('invoices.payments.search-subjects', $invoice)), @js($paymentModalState['subjectId']), @js($paymentModalState['subjectName']))" @click.outside="open = false" class="relative">
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
                                                    @input.debounce.300ms="searchRemote()"
                                                    class="input input-sm w-full" placeholder="{{ __('Search') }}"
                                                    @click.stop>
                                            </div>
                                            <ul class="overflow-y-auto p-1">
                                                <li x-show="loading" class="p-4 text-center text-sm">{{ __('Loading') }}</li>
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
                                                <li x-show="!loading && filteredGroups.length === 0"
                                                    class="p-4 text-center text-gray-500 italic text-sm">
                                                    {{ __('No results found') }}
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="label"><span class="label-text">{{ __('Amount') }}</span></label>
                                    <x-text-input input_name="amount" input_class="input-bordered locale-number"
                                        input_value="{{ $paymentModalState['amount'] }}" required x-model="amount"
                                        @input="amount = $store.utils.cleanupNumber($event.target.value)"
                                        x-effect="$el.value = $store.utils.convertToFarsi($store.utils.formatNumber(amount))" />
                                    <span class="text-xs text-gray-500">{{ __('Remaining Amount') }}:
                                        <span x-text="$store.utils.convertToFarsi($store.utils.formatNumber(availableAmount))"></span></span>
                                </div>
                                <div>
                                    <label class="label" for="invoice_payment_fee"><span class="label-text">{{ __('Bank service fee') }}</span></label>
                                    <x-text-input input_name="bank_service_fee" id_input="invoice_payment_fee"
                                        input_class="input-bordered locale-number" input_value="{{ $paymentModalState['bankServiceFee'] }}"
                                        x-model="bankServiceFee" @input="bankServiceFee = $store.utils.cleanupNumber($event.target.value)"
                                        x-effect="$el.value = $store.utils.convertToFarsi($store.utils.formatNumber(bankServiceFee))" />
                                </div>
                                <div>
                                    <label class="label"><span class="label-text">{{ __('Date') }}</span></label>
                                    <x-text-input data-jdp input_name="date" id_input="invoice_payment_date"
                                        autocomplete="off" readonly x-model="date"
                                        input_class="input-bordered" input_value="{{ $paymentModalState['date'] }}" />
                                </div>
                                <div>
                                    <label class="label"><span
                                            class="label-text">{{ __('Reference Number') }}</span></label>
                                    <x-text-input input_name="reference_number" input_class="input-bordered"
                                        maxlength="20" x-model="referenceNumber" input_value="{{ $paymentModalState['referenceNumber'] }}" />
                                </div>
                                <div>
                                    <label class="label"><span
                                            class="label-text">{{ __('Description') }}</span></label>
                                    <textarea name="description" class="textarea textarea-bordered w-full" rows="2" x-model="description">{{ $paymentModalState['description'] }}</textarea>
                                </div>
                                <div class="modal-action">
                                    <button type="button" class="btn btn-sm md:btn-sm lg:btn-md"
                                        onclick="payment_modal.close()">{{ __('Cancel') }}</button>
                                    <button type="submit" @disabled($fiscalYearClosed) title="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : '' }}" class="btn btn-sm md:btn-sm lg:btn-md btn-primary" x-text="editing ? '{{ __('Edit') }}' : '{{ __('Save') }}'"></button>
                                </div>
                            </form>
                        </div>
                        <form method="dialog" class="modal-backdrop">
                            <button>{{ __('close') }}</button>
                        </form>
                    </dialog>
                    @can('invoices.payments.store')
                    @if($chequeDirection)
                        @can('invoices.payments.store-cheque')
                            <dialog id="cheque_payment_modal" class="modal">
                                <div class="modal-box max-w-3xl">
                                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                                        <div>
                                            <h3 class="text-sm md:text-base lg:text-lg break-words font-bold">{{ __('Pay invoice by cheque') }}</h3>
                                            <p class="text-sm opacity-70">{{ $chequeDirection->label() }} - {{ $invoice->customer?->name }}</p>
                                        </div>
                                        <span class="badge badge-sm md:badge-md lg:badge-lg h-auto max-w-full whitespace-normal py-1 badge-secondary">{{ __('Remaining Amount') }}: {{ formatNumber($remainingAmount) }}</span>
                                    </div>
                                    <form method="POST" action="{{ route('invoices.payments.store-cheque', $invoice) }}">
                                        @csrf
                                        <div class="alert alert-info p-1 mb-2 text-sm">
                                            {{ __('If you select a chequebook, it must belong to the selected bank account.') }}
                                        </div>
                                        <div class="grid [&>*]:min-w-0 gap-4 md:grid-cols-2">
                                            @if ($chequeDirection === \App\Enums\ChequeType::RECEIVABLE)
                                                <div class="md:col-span-2"><x-cheque-destination /></div>
                                            @endif
                                            <div x-data="{ amountInput: '{{ $remainingAmount }}' }">
                                                <label class="label"><span class="label-text">{{ __('Amount') }}</span></label>
                                                <x-text-input input_name="amount" input_class="input-bordered locale-number"
                                                    input_value="{{ $remainingAmount }}" required x-model="amountInput"
                                                    @input="amountInput = $store.utils.cleanupNumber($event.target.value)"
                                                    x-effect="$el.value = $store.utils.convertToFarsi($store.utils.formatNumber(amountInput))" />
                                            </div>
                                            <div class="md:col-span-1" x-data="{ chequeFee: @js((string) old('bank_service_fee', 0)) }">
                                                <x-text-input title="{{ __('Bank service fee') }}" label_text_class="text-sm text-gray-500" input_name="bank_service_fee" id_input="cheque_payment_fee" input_class="locale-number" :input_value="old('bank_service_fee', 0)"
                                                    inputmode="decimal" x-model="chequeFee" @input="chequeFee = $store.utils.cleanupNumber($event.target.value)"
                                                    x-effect="$el.value = $store.utils.convertToFarsi($store.utils.formatNumber(chequeFee))" />
                                            </div>
                                            <div>
                                                <label class="label"><span class="label-text">{{ __('16-digit Sayad number') }}</span></label>
                                                <x-text-input input_name="sayad_number" input_class="input-bordered font-mono"
                                                    inputmode="numeric" minlength="16" maxlength="16" required />
                                            </div>
                                            <div>
                                                <label class="label"><span class="label-text">{{ __('Cheque number') }}</span></label>
                                                <x-text-input input_name="cheque_number" input_class="input-bordered" maxlength="50" />
                                            </div>
                                            <div>
                                                <label class="label"><span class="label-text">{{ __('Issue date') }}</span></label>
                                                <x-text-input data-jdp input_name="issue_date" id_input="cheque_issue_date"
                                                    autocomplete="off" readonly
                                                    input_class="input-bordered" input_value="{{ convertToJalali(now(), true) }}" required />
                                            </div>
                                            <div>
                                                <label class="label"><span class="label-text">{{ __('Due date') }}</span></label>
                                                <x-text-input data-jdp input_name="due_date" id_input="cheque_due_date"
                                                    autocomplete="off" readonly
                                                    input_class="input-bordered" required />
                                            </div>
                                            @if($chequeDirection !== \App\Enums\ChequeType::PAYABLE)
                                                <div>
                                                    <label class="label"><span class="label-text">{{ __('Bank') }}</span></label>
                                                    <select name="bank_id" class="select select-bordered w-full" required>
                                                        <option value="">{{ __('Select bank') }}</option>
                                                        @foreach($chequeBanks as $bank)
                                                            <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif
                                            @if($chequeDirection === \App\Enums\ChequeType::PAYABLE)
                                                <div>
                                                    <label class="label"><span class="label-text">{{ __('Bank account') }}</span></label>
                                                    <select name="bank_account_id" class="select select-bordered w-full" required>
                                                        <option value="">{{ __('Select bank account') }}</option>
                                                        @foreach($chequeBankAccounts as $account)
                                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="label"><span class="label-text">{{ __('Chequebook') }}</span></label>
                                                    <select name="chequebook_id" class="select select-bordered w-full">
                                                        <option value="">{{ __('No chequebook') }}</option>
                                                        @foreach($chequebooks as $chequebook)
                                                            <option value="{{ $chequebook->id }}">{{ $chequebook->bankAccount?->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            @endif
                                            <div>
                                                <label class="label"><span class="label-text">{{ __('Cheque serial') }}</span></label>
                                                <x-text-input input_name="serial" input_class="input-bordered" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="label"><span class="label-text">{{ __('Description') }}</span></label>
                                            <textarea name="description" class="textarea textarea-bordered w-full" rows="2"></textarea>
                                        </div>
                                        <div class="modal-action">
                                            <button type="button" class="btn btn-sm md:btn-sm lg:btn-md" onclick="cheque_payment_modal.close()">{{ __('Cancel') }}</button>
                                            <button type="submit" class="btn btn-sm md:btn-sm lg:btn-md btn-secondary">{{ __('Register cheque and pay') }}</button>
                                        </div>
                                    </form>
                                </div>
                                <form method="dialog" class="modal-backdrop"><button>{{ __('close') }}</button></form>
                            </dialog>
                        @endcan
                    @endif
                    @endcan
                    @pushOnce('scripts')
                        <script type="module">
                            const invoiceModalDateSelector = [
                                '#payment_modal input[data-jdp]',
                                '#cheque_payment_modal input[data-jdp]'
                            ].join(', ');

                            const prepareInvoiceModalDatepicker = (input) => {
                                const modal = input.closest('dialog');
                                if (!modal) {
                                    return;
                                }

                                jalaliDatepicker.updateOptions({
                                    persianDigits: true,
                                    selector: invoiceModalDateSelector,
                                    container: `#${modal.id}`
                                });

                                for (const elementName of ['jdp-container', 'jdp-overlay']) {
                                    const element = document.querySelector(elementName);
                                    if (elementName === 'jdp-overlay' && element) {
                                        element.style.position = 'fixed';
                                    }

                                    if (element && element.parentElement !== modal) {
                                        modal.appendChild(element);
                                    }
                                }
                            };

                            jalaliDatepicker.startWatch({
                                persianDigits: true,
                                selector: invoiceModalDateSelector,
                                container: '#payment_modal'
                            });

                            document.querySelectorAll(invoiceModalDateSelector).forEach((input) => {
                                input.addEventListener('pointerdown', (event) => {
                                    event.preventDefault();
                                    prepareInvoiceModalDatepicker(input);
                                    input.focus({ preventScroll: true });
                                    jalaliDatepicker.show(input);
                                });

                                input.addEventListener('focusin', () => {
                                    prepareInvoiceModalDatepicker(input);
                                });
                            });
                        </script>
                    @endPushOnce
                @endif
            </div>

            <div class="card-actions grid grid-cols-[repeat(2,minmax(0,1fr))] items-stretch gap-2 md:flex md:flex-nowrap md:items-center md:justify-between md:[direction:rtl] mt-4">
                <a href="{{ route('invoices.index', ['invoice_type' => $invoice->invoice_type, 'service_buy' => $isServiceBuy]) }}"
                    class="btn btn-sm lg:btn-md btn-ghost h-auto min-h-8 min-w-0 whitespace-normal gap-1 px-2 py-2 md:shrink-0 md:gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ __('Back') }}
                </a>

                <div class="contents md:flex md:min-w-0 md:flex-nowrap md:gap-2 [&>.btn]:h-auto [&>.btn]:min-h-8 [&>.btn]:min-w-0 [&>.btn]:whitespace-normal [&>.btn]:px-2 [&>.btn]:py-2 [&>form]:min-w-0 [&>form>.btn]:h-auto [&>form>.btn]:min-h-8 [&>form>.btn]:w-full [&>form>.btn]:whitespace-normal [&>form>.btn]:px-2 [&>form>.btn]:py-2 [&>.tooltip]:min-w-0 [&>.tooltip>.btn]:h-auto [&>.tooltip>.btn]:min-h-8 [&>.tooltip>.btn]:w-full [&>.tooltip>.btn]:whitespace-normal [&>.tooltip>.btn]:px-2 [&>.tooltip>.btn]:py-2 md:[&>.btn]:shrink-0 md:[&>.btn]:whitespace-nowrap md:[&>form]:shrink-0 md:[&>form>.btn]:whitespace-nowrap md:[&>.tooltip]:shrink-0 md:[&>.tooltip>.btn]:whitespace-nowrap">
                    @can('invoices.transfer')
                        @if ($fiscalYears->isNotEmpty())
                            <button type="button" @disabled($fiscalYearClosed)
                                onclick="document.getElementById('inv-transfer-modal').showModal()"
                                class="btn btn-sm lg:btn-md gap-2 {{ $fiscalYearClosed ? 'btn-disabled cursor-not-allowed' : '' }}"
                                title="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : '' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5" fill="none"
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
                        $isVoided = $isSellWorkflow && (bool) $invoice->voidInvoice;
                        $canApprove =
                            ($isSellWorkflow ? false : $invoice->status->isPending()) ||
                            $invoice->status->isReadyToApprove() ||
                            $invoice->status->isUnapproved() ||
                            $invoice->status->isApprovedInactive();
                        // Settled invoices are shown as disabled in the list and cannot
                        // change status until their payments are removed.
                        $canUnapprove = $invoice->status->isApproved();
                        $isSettled = $invoice->status->isPartiallyPaid() || $invoice->status->isPaid();

                        $hasMoadianSuccess = $invoice->moadianHistories->contains(function ($history) {
                            $data = $history->data;

                            return strtoupper($data['status'] ?? '') === 'SUCCESS';
                        });

                        $originalHasMoadianSuccess = !$invoice->invoice_type->isVoid() ||
                            ($invoice->voidedInvoice?->moadianHistories->contains(function ($history) {
                                $data = $history->data;

                                return strtoupper($data['status'] ?? '') === 'SUCCESS';
                            }) ?? false);

                        $canChangeStatus = ($canApprove || $canUnapprove) && !$hasMoadianSuccess;
                    @endphp

                    @can('invoices.send-moadian')
                        @if ($isMoadianSendable)
                            @if ($hasMoadianSuccess)
                                <span class="tooltip"
                                    data-tip="{{ __('This invoice has already been sent successfully to Moadian and cannot be sent again.') }}">
                                    <button class="btn btn-sm lg:btn-md btn-success btn-disabled cursor-not-allowed"
                                        title="{{ __('This invoice has already been sent successfully to Moadian and cannot be sent again.') }}">{{ __('Send Moadian') }}</button>
                                </span>
                            @elseif (!$invoice->status->isApprovedOrSettled())
                                <span class="tooltip"
                                    data-tip="{{ __('Approve the invoice first to send to moadian') }}">
                                    <button class="btn btn-sm lg:btn-md btn-error btn-disabled cursor-not-allowed"
                                        title="{{ __('Approve the invoice first to send to moadian') }}">{{ __('Send Moadian') }}</button>
                                </span>
                            @elseif (!$originalHasMoadianSuccess)
                                <span class="tooltip"
                                    data-tip="{{ __('The original invoice must be sent successfully to Moadian before sending its void invoice.') }}">
                                    <button class="btn btn-sm lg:btn-md btn-error btn-disabled cursor-not-allowed"
                                        title="{{ __('The original invoice must be sent successfully to Moadian before sending its void invoice.') }}">{{ __('Send Moadian') }}</button>
                                </span>
                            @elseif ($fiscalYearClosed)
                                <span class="tooltip" data-tip="{{ $fiscalYearClosedMessage }}">
                                    <button class="btn btn-success btn-disabled cursor-not-allowed" disabled>{{ __('Send Moadian') }}</button>
                                </span>
                            @else
                                <form method="POST" action="{{ route('invoices.send-moadian', $invoice) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm lg:btn-md btn-success">{{ __('Send Moadian') }}</button>
                                </form>
                            @endif
                        @endif
                    @endcan

                    @can('invoices.void')
                        @if ($invoice->invoice_type === App\Enums\InvoiceType::SELL)
                            @if (
                                $invoice->status->isApprovedOrSettled() &&
                                    !$invoice->voidInvoice &&
                                    !$fiscalYearClosed &&
                                    $invoice->getReturnInvoice()->isEmpty())
                                <a href="{{ route('invoices.void-form', $invoice) }}" class="btn btn-sm lg:btn-md btn-warning">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    {{ __('Void') }}
                                </a>
                            @else
                                @php
                                    $voidDisabledTip = $fiscalYearClosed
                                        ? $fiscalYearClosedMessage
                                        : ($invoice->voidInvoice
                                        ? __('Invoice has voided already.')
                                        : ($invoice->getReturnInvoice()->isNotEmpty()
                                            ? __('Only approved sell invoices without return invoices can be voided.')
                                            : __('Invoice must be approved before voiding.')));
                                @endphp
                                <span class="tooltip" data-tip="{{ $voidDisabledTip }}">
                                    <button class="btn btn-sm lg:btn-md btn-warning gap-2 btn-disabled cursor-not-allowed">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5" fill="none"
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

                    @if ($invoice->status->isApprovedOrSettled())
                        <x-export-delivery-choice :id="'invoice-pdf-delivery-'.$invoice->id" export="invoice_pdf" :filters="['invoice_id' => $invoice->id]" :label="__('Print')" :title="__('Receive PDF')" class="btn btn-sm lg:btn-md btn-outline gap-2"
                            :desc="__('You can replace your email address to send this pdf to someone else. The email will identify you as the requester.')"/>
                    @else
                        <a href="{{ route('invoices.print', $invoice) }}" class="btn btn-sm lg:btn-md btn-outline gap-2" target="_blank" rel="noopener">
                            {{ __('Print') }}
                        </a>
                    @endif

                    @can('invoices.approve')
                        @if (!$fiscalYearClosed && $isSellWorkflow && ($invoice->status->isPreInvoice() || $invoice->status->isRejected()))
                            <form action="{{ route('invoices.change-status', [$invoice, 'ready_to_approve']) }}"
                                method="POST" class="inline-block">
                                @csrf
                                <button type="submit" class="btn btn-sm lg:btn-md btn-success">{{ __('Ready to approve') }}</button>
                            </form>
                        @endif

                        @if (!$fiscalYearClosed && $isSellWorkflow && $invoice->status->isPreInvoice())
                            <form action="{{ route('invoices.change-status', [$invoice, 'rejected']) }}" method="POST"
                                class="inline-block">
                                @csrf
                                <button type="submit" class="btn btn-sm lg:btn-md btn-error gap-2">{{ __('Reject') }}</button>
                            </form>
                        @endif

                        @if ($canChangeStatus && !$fiscalYearClosed)
                            @if ($changeStatusValidation->hasErrors())
                                <a data-tip="{{ $changeStatusValidation->toText() }}"
                                    href="{{ route('invoices.conflicts', $invoice) }}"
                                    class="btn btn-sm lg:btn-md btn-accent inline-flex tooltip">
                                    {{ __('Fix Conflict') }}
                                </a>
                            @elseif ($isVoided)
                                <span class="tooltip" data-tip="{{ __('To change the status, first unapprove the voiding invoice.') }}">
                                    <button class="btn btn-sm lg:btn-md btn-error btn-disabled cursor-not-allowed">{{ __('Unapprove') }}</button>
                                </span>
                            @else
                                <form
                                    action="{{ route('invoices.change-status', [$invoice, $canUnapprove ? 'unapproved' : 'approved']) }}{{ ($changeStatusValidation->hasWarning() || $isSettled) ? '?confirm=1' : '' }}"
                                    method="POST"
                                    class="inline-block {{ ($changeStatusValidation->hasWarning() || $isSettled) ? 'change-status-form' : '' }}"
                                    data-confirm-message="{{ $isSettled ? __('Deactivating this paid or partially paid invoice will remove its payment accounting documents while keeping the payment records. Are you sure?') : __('This invoice has warnings for change its status, are you sure to change status?') }}">
                                    @csrf
                                    <button type="submit" x-data="{}"
                                        data-tip="{{ $changeStatusValidation->toText() }}"
                                        class="btn btn-sm lg:btn-md inline-flex {{ $canUnapprove ? 'btn-warning' : 'btn-success' }} {{ $canApprove && $changeStatusValidation->hasWarning() ? ' btn-outline ' : '' }}">
                                        {{ $canUnapprove ? __('Unapprove') : __('Approve') }}
                                    </button>
                                </form>
                            @endif
                        @elseif ($fiscalYearClosed)
                            <span class="tooltip" data-tip="{{ $fiscalYearClosedMessage }}">
                                <button class="btn btn-warning btn-disabled cursor-not-allowed" disabled>{{ __('Change status') }}</button>
                            </span>
                        @elseif ($isSettled)
                            <span class="tooltip"
                                data-tip="{{ __('A paid or partially paid invoice cannot change its status until its payments (or their payment documents) are removed.') }}">
                                <button
                                    class="btn btn-warning btn-disabled cursor-not-allowed">{{ __('Unapprove') }}</button>
                            </span>
                        @endif
                    @endcan

                    @if (!$fiscalYearClosed && !$invoice->status->isApprovedOrSettled() && !$invoice->invoice_type->isVoid() && !$hasMoadianSuccess)
                        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm lg:btn-md btn-primary gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Edit invoice') }}
                        </a>
                    @else
                        <span class="tooltip"
                            data-tip="{{ $fiscalYearClosed ? $fiscalYearClosedMessage : ($invoice->invoice_type->isVoid() ? __('Editing is not allowed for void invoices.') : __('Editing is not allowed for approved invoices.')) }}">
                            <button class="btn btn-sm lg:btn-md btn-primary gap-2 btn-disabled cursor-not-allowed">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5" fill="none"
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
                            <a href="{{ route('documents.show', $invoice->document) }}" class="btn btn-sm lg:btn-md btn-secondary gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 lg:h-5 lg:w-5" fill="none"
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
                    <h3 class="font-bold text-sm md:text-base lg:text-lg">{{ __('Transfer to another Fiscal Year') }}</h3>
                    <form action="{{ route('invoices.transfer', $invoice) }}" method="POST" class="mt-4">
                        @csrf
                        <div class="form-control w-full">
                            <label class="label">
                                <span class="label-text font-semibold">{{ __('Target Fiscal Year') }}</span>
                            </label>
                            <select name="target_fiscal_year_id" class="select select-bordered w-full" required>
                                <option value="">{{ __('-- Select fiscal year --') }}</option>
                                @foreach ($fiscalYears as $year)
                                    <option value="{{ $year->id }}">{{ $year->company?->name }} - {{ $year->year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-control mt-4 [&_.fieldset]:m-0 [&_.label]:p-0 [&_.label]:text-sm [&_.checkbox]:checkbox-sm [&_.checkbox]:checkbox-primary">
                            <x-checkbox name="transfer_dependencies" id="transfer_dependencies" value="1"
                                :title="__('Create missing customer, products, services, and their accounting subjects in the target fiscal year')" />
                        </div>
                        <div class="modal-action">
                            <button type="button" onclick="document.getElementById('inv-transfer-modal').close()"
                                class="btn btn-sm md:btn-sm lg:btn-md">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-sm md:btn-sm lg:btn-md btn-primary gap-2">{{ __('Transfer') }}</button>
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
        function invoicePaymentForm(initial, createUrl, updateUrl) {
            return {
                amount: initial.amount,
                availableAmount: initial.availableAmount,
                bankServiceFee: initial.bankServiceFee,
                date: initial.date,
                referenceNumber: initial.referenceNumber,
                description: initial.description,
                paymentId: initial.paymentId,
                editing: initial.editing,
                action: initial.editing ? updateUrl.replace('__PAYMENT__', initial.paymentId) : createUrl,
                restore() {
                    if (initial.closed || !initial.restore) return;
                    this.action = this.editing ? updateUrl.replace('__PAYMENT__', this.paymentId) : createUrl;
                    this.$nextTick(() => {
                        this.setSubject(initial.subjectId, initial.subjectName);
                        document.getElementById('payment_modal').showModal();
                    });
                },
                setSubject(id, name) {
                    const selector = document.querySelector('#payment_modal [x-ref="settlementSearch"]')?.closest('[x-data]');
                    if (selector && window.Alpine) Alpine.$data(selector).setSelection(id, name);
                },
                create() {
                    if (initial.closed) return;
                    this.editing = false;
                    this.paymentId = '';
                    this.action = createUrl;
                    this.amount = @js((string) $remainingAmount);
                    this.availableAmount = @js((string) $remainingAmount);
                    this.bankServiceFee = '0';
                    this.date = @js(convertToJalali(now(), true));
                    this.referenceNumber = '';
                    this.description = '';
                    this.setSubject('', '');
                },
                edit(payment) {
                    if (initial.closed) return;
                    this.editing = true;
                    this.paymentId = payment.id;
                    this.action = updateUrl.replace('__PAYMENT__', payment.id);
                    this.amount = payment.amount;
                    this.availableAmount = payment.availableAmount;
                    this.bankServiceFee = payment.bankServiceFee;
                    this.date = payment.date;
                    this.referenceNumber = payment.referenceNumber;
                    this.description = payment.description;
                    this.setSubject(payment.subjectId, payment.subjectName);
                    document.getElementById('payment_modal').showModal();
                },
            };
        }

        function settlementSelect(groups, url, selectedId = '', selectedLabel = '') {
            return {
                open: false,
                search: '',
                groups: groups || [],
                initialGroups: groups || [],
                loading: false,
                requestId: 0,
                selectedId: selectedId ? Number(selectedId) : '',
                selectedLabel,
                setSelection(id, name) {
                    this.selectedId = id ? Number(id) : '';
                    this.selectedLabel = name || '';
                },
                toggle() {
                    this.open = !this.open;
                    if (this.open) {
                        this.$nextTick(() => this.$refs.settlementSearch?.focus());
                    }
                },
                get filteredGroups() {
                    return this.groups;
                },
                async searchRemote() {
                    const requestId = ++this.requestId;
                    const q = this.search.trim();
                    if (!q) {
                        this.groups = this.initialGroups;
                        this.loading = false;
                        return;
                    }
                    this.loading = true;
                    this.groups = [];
                    try {
                        const response = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (!response.ok) throw new Error('Settlement search failed');
                        const results = await response.json();
                        if (requestId === this.requestId) this.groups = results;
                    } catch {
                        if (requestId === this.requestId) this.groups = [];
                    } finally {
                        if (requestId === this.requestId) this.loading = false;
                    }
                },
                select(opt) {
                    this.selectedId = opt.id;
                    this.selectedLabel = opt.name;
                    this.open = false;
                    this.search = '';
                    this.requestId++;
                    this.loading = false;
                    this.groups = this.initialGroups;
                },
            };
        }

        document.addEventListener('DOMContentLoaded', function() {
            const changeStatusForms = document.querySelectorAll('.change-status-form');

            changeStatusForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (confirm(this.dataset.confirmMessage)) {
                        this.submit();
                    }
                });
            });
        });
    </script>

</x-app-layout>
