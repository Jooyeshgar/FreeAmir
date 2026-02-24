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
                    @if ($service_buy)
                        <a href="{{ route('invoices.create', ['invoice_type' => 'buy', 'service_buy' => '1']) }}"
                            class="btn btn-primary">
                            {{ __('Service Buy Invoice') }}
                        </a>
                    @else
                        <a href="{{ route('invoices.create', ['invoice_type' => 'buy']) }}" class="btn btn-primary">
                            {{ __('Create buy invoice') }}
                        </a>
                    @endif
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

                <a href="{{ route('invoices.inactive') }}" class="btn btn-primary">{{ __('Approve Inactive') }}</a>

                <form action="{{ route('invoices.index') }}" method="GET" class="ml-auto">
                    <div class="mt-4 mb-4 grid grid-cols-8 gap-6">

                        <div class="col-span-2 md:col-span-1" hidden>
                            <x-input name="invoice_type" value="{{ request('invoice_type') }}"
                                placeholder="{{ __('Invoice Type') }}" />
                            <x-input name="service_buy" value="{{ request('service_buy') }}"
                                placeholder="{{ __('Service Buy') }}" />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <x-input name="number" value="{{ request('number') }}"
                                placeholder="{{ __('Invoice Number') }}" />
                        </div>
                        <div class="col-span-6 md:col-span-3">
                            <x-input name="text" value="{{ request('text') }}"
                                placeholder="{{ __('Search by customer name or transaction description') }}" />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <x-date-picker name="start_date" class="w-40" placeholder="{{ __('Start date') }}"
                                value="{{ request('start_date') }}"></x-date-picker>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <x-date-picker name="end_date" class="w-40" placeholder="{{ __('End date') }}"
                                value="{{ request('end_date') }}"></x-date-picker>
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            @php
                                $invoiceType = request('invoice_type');
                                $isSellWorkflow = $invoiceType === 'sell';
                                $skipIfSell = fn($status) => $status->isPending();
                                $skipIfNotSell = fn($status) => $status->isReadyToApprove() ||
                                    $status->isPreInvoice() ||
                                    $status->isRejected();
                                $shouldSkip = $isSellWorkflow ? $skipIfSell : $skipIfNotSell;
                            @endphp
                            <select name="status" id="status"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900 px-3 py-2">
                                <option value="all">{{ __('All Invoices') }}</option>
                                    @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                                        @if ($shouldSkip($status))
                                            @continue
                                        @endif
                                        <option value="{{ $status->value }}" @selected($status != 'all' && $status->value == request('status'))>
                                            {{ $status->label() }}</option>
                                    @endforeach
                            </select>
                        </div>
                        <div class="col-span-2 md:col-span-1 text-center">
                            <input type="submit" value="{{ __('Search') }}" class="btn-primary btn" />
                        </div>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                @php
                    $invoiceType = request('invoice_type');
                    $statusFilter = request('status');
                    $baseQuery = request()->except('page');
                    $isSellWorkflow = $invoiceType === 'sell';

                    $skipIfSell = fn($status) => $status->isPending();
                    $skipIfNotSell = fn($status) => $status->isReadyToApprove() ||
                        $status->isPreInvoice() ||
                        $status->isRejected();
                    $shouldSkip = $isSellWorkflow ? $skipIfSell : $skipIfNotSell;

                    $statusTypes = [
                        \App\Enums\InvoiceStatus::PENDING->value => 'info',
                        \App\Enums\InvoiceStatus::APPROVED->value => 'success',
                        \App\Enums\InvoiceStatus::UNAPPROVED->value => 'warning',
                        \App\Enums\InvoiceStatus::PRE_INVOICE->value => 'info',
                        \App\Enums\InvoiceStatus::APPROVED_INACTIVE->value => 'error',
                        \App\Enums\InvoiceStatus::REJECTED->value => 'error',
                        \App\Enums\InvoiceStatus::READY_TO_APPROVE->value => 'info',
                    ];
                @endphp
                @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                    @if ($shouldSkip($status))
                        @continue
                    @endif
                    @php
                        $value = $status->value;

                        $count = $statusCounts->get($value, 0);
                        $isActive = $statusFilter == $value;
                        $url = route('invoices.index', array_merge($baseQuery, ['status' => $value]));
                        $type = $statusTypes[$value] ?? 'info';

                        if ($invoiceType === 'buy') {
                            $quantityTitle = request('service_buy') == '1' ? __('Bought Services Quantity') : __('Bought Products Quantity');
                        } elseif ($invoiceType === 'sell') {
                            $quantityTitle = __('Sold Products Quantity');
                        }elseif ($invoiceType === 'return_buy' && ! request('service_buy') == '1') {
                            $quantityTitle = __('Returned Bought Products Quantity');
                        }elseif ($invoiceType === 'return_sell') {
                            $quantityTitle = __('Returned Sold Products Quantity');
                        }elseif ($invoiceType === 'return_buy' && request('service_buy') == '1') {
                            $quantityTitle = __('Returned Sold Services Quantity');
                        }
                    @endphp
                    <a href="{{ $url }}"
                        class="block transition-transform hover:scale-105 {{ $isActive ? 'ring-2 ring-primary rounded-xl' : '' }}">
                        <x-stat-card :title="$status->label()" :value="convertToFarsi($count)" :type="$type" />
                    </a>
                @endforeach
                @if(request('service_buy') == '1')
                    <x-stat-card :title="$quantityTitle" :value="formatNumber($invoices->totalServicesQuantity)" />
                @else
                    <x-stat-card :title="$quantityTitle" :value="formatNumber($invoices->totalProductsQuantity)" />
                @endif
                <x-stat-card :title="__('Invoices Amount')" :value="formatNumber($invoices->totalAmount)" />
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Invoice Number') }}</th>
                        <th class="px-4 py-2">{{ __('Customer') }}</th>
                        <th class="px-4 py-2">{{ __('Document') }}</th>
                        <th class="px-4 py-2">{{ __('Date') }}</th>
                        <th class="px-4 py-2">{{ __('Price') }} ({{ config('amir.currency') ?? __('Rial') }})</th>
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
                                <a
                                    href="{{ route('customers.show', $invoice->customer) }}">{{ $invoice->customer->name ?? '' }}</a>
                                <br>
                                <span class="text-xs text-gray-500">{{ $invoice->title ?? '' }}</span>
                            </td>
                            <td class="px-4 py-2">
                                @if ($invoice->document_id)
                                    @can('documents.show')
                                        <a href="{{ route('documents.show', $invoice->document_id) }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
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
                                @php
                                    $isSellWorkflow = $invoice->invoice_type === \App\Enums\InvoiceType::SELL;
                                    $canApprove = ($isSellWorkflow ? false : $invoice->status->isPending()) || $invoice->status->isReadyToApprove() || $invoice->status->isUnapproved() || $invoice->status->isApprovedInactive();
                                    $canUnapprove = $invoice->status->isApproved();
                                    $canChangeStatus = $canApprove || $canUnapprove;
                                @endphp
                                <a href="{{ route('invoices.show', $invoice) }}" target="_blank"
                                    rel="noopener" class="btn btn-sm btn-info">{{ __('Show') }}</a>

                                @can('invoices.approve')
                                    @if ($isSellWorkflow && ($invoice->status->isPreInvoice() || $invoice->status->isRejected()))
                                        <a href="{{ route('invoices.change-status', [$invoice, 'ready_to_approve']) }}"
                                            class="btn btn-sm btn-success">{{ __('Issue') }}
                                        </a>
                                    @endif

                                    @if ($isSellWorkflow && $invoice->status->isPreInvoice())
                                        <a href="{{ route('invoices.change-status', [$invoice, 'rejected']) }}"
                                            class="btn btn-sm btn-error">{{ __('Reject') }}
                                        </a>
                                    @endif

                                    @if ($canChangeStatus)
                                        @if ($canApprove && $invoice->changeStatusValidation->hasErrors())
                                            <a data-tip="{{ $invoice->changeStatusValidation->toText() }}"
                                                href="{{ route('invoices.conflicts', $invoice) }}"
                                                class="btn btn-sm btn-accent inline-flex tooltip">{{ __('Fix Conflict') }}
                                            </a>
                                        @else
                                            <a x-data="{}"
                                                @if ($invoice->changeStatusValidation->hasWarning()) @click.prevent="if (confirm(@js($invoice->changeStatusValidation->toText()))) { window.location.href = '{{ route('invoices.change-status', [$invoice, $canUnapprove ? 'unapproved' : 'approved']) }}?confirm=1' }" @endif
                                                data-tip="{{ $invoice->changeStatusValidation->toText() }}"
                                                href="{{ route('invoices.change-status', [$invoice, $canUnapprove ? 'unapproved' : 'approved']) }}"
                                                class="btn btn-sm inline-flex tooltip {{ $canUnapprove ? 'btn-warning' : 'btn-success' }} {{ $canApprove && $invoice->changeStatusValidation->hasWarning() ? ' btn-outline ' : '' }}">
                                                {{ $canUnapprove ? __('Unapprove') : __('Approve') }}
                                            </a>
                                        @endif
                                    @endif
                                @endcan

                                @if (!$invoice->status->isApproved())
                                    <a href="{{ route('invoices.edit', $invoice) }}"
                                        class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                    <form action="{{ route('invoices.destroy', $invoice) }}" method="POST"
                                        class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                    </form>
                                @else
                                    <span class="tooltip"
                                        data-tip="{{ __('Unapprove the invoice first to edit') }}">
                                        <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed"
                                            title="{{ __('Unapprove the invoice first to edit') }}">{{ __('Edit') }}</button>
                                    </span>
                                    <span class="tooltip"
                                        data-tip="{{ __('Unapprove the invoice first to delete') }}">
                                        <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed"
                                            title="{{ __('Unapprove the invoice first to delete') }}">{{ __('Delete') }}</button>
                                    </span>
                                @endif

                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (request('status') !== null)
                <div class="px-4 py-2 text-left">
                    <a class="btn btn-primary"
                        href="{{ route('invoices.index', parameters: ['invoice_type' => request('invoice_type'), 'service_buy' => request('service_buy')]) }}">{{ __('Back') }}</a>
                </div>
            @endif

            {{ $invoices->withQueryString()->links() }}

        </div>
    </div>

</x-app-layout>
