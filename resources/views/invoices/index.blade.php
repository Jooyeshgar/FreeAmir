<x-app-layout :title="__('Invoices')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Invoices') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions flex items-center gap-3">
                @if (request('invoice_type') === 'buy')
                    <a href="{{ route('invoices.create', ['invoice_type' => 'buy']) }}" class="btn btn-primary">
                        {{ __('Create buy invoice') }}
                    </a>
                @elseif (request('invoice_type') === 'sell')
                    <a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}" class="btn btn-primary">
                        {{ __('Create sell invoice') }}
                    </a>
                @elseif (request('invoice_type') === 'return_buy')
                    <a href="{{ route('invoices.create', ['invoice_type' => 'return_buy']) }}" class="btn btn-primary">
                        {{ __('Create return buy invoice') }}
                    </a>
                @elseif (request('invoice_type') === 'return_sell')
                    <a href="{{ route('invoices.create', ['invoice_type' => 'return_sell']) }}" class="btn btn-primary">
                        {{ __('Create return sell invoice') }}
                    </a>
                @endif

                <form action="{{ route('invoices.index') }}" method="GET" class="ml-auto">
                    <div class="mt-4 mb-4 grid grid-cols-6 gap-6">
                        <div class="col-span-2 md:col-span-1" hidden>
                            <x-input name="invoice_type" value="{{ request('invoice_type') }}" placeholder="{{ __('Invoice Type') }}" />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <x-input name="number" value="{{ request('number') }}" placeholder="{{ __('Invoice Number') }}" />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <x-input name="date" placeholder="{{ __('date') }}" value="{{ request('date') }}"></x-input>
                        </div>
                        <div class="col-span-6 md:col-span-3">
                            <x-input name="text" value="{{ request('text') }}" placeholder="{{ __('Search by customer name or transaction description') }}" />
                        </div>
                        <div class="col-span-2 md:col-span-1 text-center">
                            <input type="submit" value="{{ __('Search') }}" class="btn-primary btn" />
                        </div>
                    </div>
                </form>
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Invoice Number') }}</th>
                        <th class="px-4 py-2">{{ __('Customer') }}</th>
                        <th class="px-4 py-2">{{ __('Document') }}</th>
                        <th class="px-4 py-2">{{ __('Date') }}</th>
                        <th class="px-4 py-2">{{ __('Price') }}</th>
                        <th class="px-4 py-2">{{ __('Status') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('invoices.show', $invoice) }}" class="link link-hover">
                                    {{ formatDocumentNumber($invoice->number) }}
                                </a>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('customers.show', $invoice->customer) }}">{{ $invoice->customer->name ?? '' }}</a>
                                <br>
                                <span class="text-xs text-gray-500">{{ $invoice->title ?? '' }}</span>
                            </td>
                            <td class="px-4 py-2">
                                @if ($invoice->document_id)
                                    @can('documents.show')
                                        <a href="{{ route('documents.show', $invoice->document_id) }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            {{ formatDocumentNumber($invoice->document->number) ?? '' }}
                                        </a>&nbsp;
                                    @else
                                        <span class="text-gray-500">
                                            <span>{{ formatDocumentNumber($invoice->document->number) ?? '' }}</span>
                                        </span>
                                    @endif
                                @else
                                    <span class="text-gray-500"></span>
                                @endcan
                            </td>
                            <td class="px-4 py-2">{{ isset($invoice->date) ? formatDate($invoice->date) : '' }}</td>
                            <td class="px-4 py-2">
                                {{ isset($invoice->amount) ? formatNumber($invoice->amount - $invoice->subtraction) : '' }}
                            </td>
                            <td class="px-4 py-2">
                                {{ $invoice->status?->label() ?? '' }}
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('invoices.show', $invoice) }}" target="_blank" rel="noopener" class="btn btn-sm btn-info">{{ __('Show') }}</a>
                                <a href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener" class="btn btn-sm btn-info">{{ __('Print') }}</a>

                                @can('invoices.approve')
                                    @php
                                        $isApproved = $invoice->status?->isApproved();
                                        $changeStatusValidation = \App\Services\InvoiceService::getChangeStatusValidation($invoice);
                                        $unapprovedInvoicesData = [];
                                        if (!$isApproved && $invoice->invoice_type === \App\Enums\InvoiceType::BUY) {
                                            $productIds = $invoice->items()->where('itemable_type', \App\Models\Product::class)->pluck('itemable_id')->toArray();

                                            if (!empty($productIds)) {
                                                $unapprovedInvoices = \App\Services\InvoiceService::getUnapprovedSellPriorInvoices(
                                                    $productIds,
                                                    $invoice->date,
                                                    $invoice->number,
                                                    $invoice,
                                                );

                                                if (!empty($unapprovedInvoices)) {
                                                    $unapprovedInvoicesData = collect($unapprovedInvoices)
                                                        ->map(fn($inv) => \App\Enums\InvoiceType::from($inv['invoice_type'])->label() . ' : ' . $inv['number'])
                                                        ->toArray();
                                                }
                                            }
                                        }
                                    @endphp

                                    @if ($changeStatusValidation->hasErrors())
                                        @php
                                            $btnClass = $isApproved ? 'btn-warning' : 'btn-success';
                                            $label = $isApproved ? __('Unapprove') : __('Approve');
                                        @endphp
                                        <span class="tooltip" data-tip="{{ $changeStatusValidation->toText() }}">
                                            <button class="btn btn-sm {{ $btnClass }} btn-disabled cursor-not-allowed" disabled
                                                title="{{ $changeStatusValidation->toText() }}">{{ $label }}</button>
                                        </span>
                                    @else
                                        <a href="{{ route('invoices.change-status', [$invoice, $isApproved ? 'unapproved' : 'approved']) }}"
                                            class="btn btn-sm {{ $isApproved ? 'btn-warning' : 'btn-success' }} invoice-approve-btn"
                                            data-unapproved-invoices="{{ !empty($unapprovedInvoicesData) ? json_encode($unapprovedInvoicesData) : '' }}">
                                            {{ __($isApproved ? 'Unapproved' : 'Approved') }}
                                        </a>
                                    @endif
                                @endcan

                                @php
                                    $editDeleteStatus = \App\Services\InvoiceService::getEditDeleteStatus($invoice);
                                @endphp

                                @if ($editDeleteStatus['allowed'])
                                    @if (!$invoice->status?->isApproved())
                                        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                        <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                        </form>
                                    @else
                                        <span class="tooltip" data-tip="{{ __('Unapprove the invoice first to edit') }}">
                                            <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed" disabled
                                                title="{{ __('Unapprove the invoice first to edit') }}">{{ __('Edit') }}</button>
                                        </span>
                                        <span class="tooltip" data-tip="{{ __('Unapprove the invoice first to delete') }}">
                                            <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed" disabled
                                                title="{{ __('Unapprove the invoice first to delete') }}">{{ __('Delete') }}</button>
                                        </span>
                                    @endif
                                @else
                                    <span class="tooltip" data-tip="{{ $editDeleteStatus['reason'] }}">
                                        <button class="btn btn-sm btn-info btn-disabled cursor-not-allowed" disabled
                                            title="{{ $editDeleteStatus['reason'] }}">{{ __('Edit') }}</button>
                                    </span>
                                    <span class="tooltip" data-tip="{{ $editDeleteStatus['reason'] }}">
                                        <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed" disabled
                                            title="{{ $editDeleteStatus['reason'] }}">{{ __('Delete') }}</button>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if ($invoices->hasPages())
                <div class="join">
                    @if ($invoices->onFirstPage())
                        <input class="join-item btn btn-square hidden " type="radio" disabled>
                    @else
                        <a href="{{ $invoices->previousPageUrl() }}" class="join-item btn btn-square">&lsaquo;</a>
                    @endif

                    @foreach ($invoices->getUrlRange(1, $invoices->lastPage()) as $page => $url)
                        @if ($page == $invoices->currentPage())
                            <a href="{{ $url }}" class="join-item btn btn-square bg-blue-500 text-white">{{ $page }}</a>
                        @else
                            <a href="{{ $url }}" class="join-item btn btn-square">{{ $page }}</a>
                        @endif
                    @endforeach

                    @if ($invoices->hasMorePages())
                        <a href="{{ $invoices->nextPageUrl() }}" class="join-item btn btn-square">&rsaquo;</a>
                    @else
                        <input class="join-item btn btn-square hidden" type="radio" disabled>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @pushOnce('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const approveButtons = document.querySelectorAll('.invoice-approve-btn');

                approveButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        const href = this.getAttribute('href');
                        const unapprovedInvoicesJson = this.getAttribute('data-unapproved-invoices');

                        if (!href.includes('/approve')) {
                            return;
                        }

                        if (unapprovedInvoicesJson && unapprovedInvoicesJson.trim() !== '') {
                            e.preventDefault();
                            try {
                                const unapprovedInvoices = JSON.parse(unapprovedInvoicesJson);

                                if (unapprovedInvoices.length > 0) {
                                    let message =
                                        '{{ __('Note: There are unapproved prior invoices for the selected products that will remain unapproved:') }}\n\n';

                                    unapprovedInvoices.forEach(inv => {
                                        message += '• ' + inv + '\n';
                                    });

                                    message += '\n' +
                                        '{{ __('Do you want to proceed with approval?') }}';

                                    if (confirm(message)) {
                                        window.location.href = href;
                                    }
                                }
                            } catch (error) {
                                console.error('Error parsing unapproved invoices:', error);
                                window.location.href = href;
                            }
                        }
                    });
                });
            });
        </script>
    @endPushOnce
</x-app-layout>
