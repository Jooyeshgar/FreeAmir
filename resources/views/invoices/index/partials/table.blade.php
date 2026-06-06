<table class="table w-full mt-4 overflow-auto">
    <thead>
        <tr>
            <th class="px-4 py-2">{{ __('Invoice Number') }}</th>
            <th class="px-4 py-2">{{ __('Customer') }}</th>
            <th class="px-4 py-2">{{ __('Document') }}</th>
            <th class="px-4 py-2">{{ __('Date') }}</th>
            <th class="px-4 py-2">{{ __('Price') }} ({{ config('amir.currency') ?? __('Rial') }})</th>
            <th class="px-4 py-2">{{ __('Status') }}</th>
            @if ($showMoadianColumn)
                <th class="px-4 py-2">{{ __('Moadian Status') }}</th>
            @endif
            <th class="px-4 py-2">{{ __('Action') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoices as $invoice)
            @php
                $isVoided = $isSellWorkflow && (bool) $invoice->voidInvoice;
                $canApprove = ($isSellWorkflow ? false : $invoice->status->isPending())
                    || $invoice->status->isReadyToApprove()
                    || $invoice->status->isUnapproved()
                    || $invoice->status->isApprovedInactive();
                $canUnapprove = $invoice->status->isApproved();
                $canChangeStatus = $canApprove || $canUnapprove;
                $editDisabled = $isVoidWorkflow || $invoice->status->isApproved() || $isVoided;
                $deleteDisabled = $invoice->status->isApproved() || $isVoided;
                $editTooltip = $isVoidWorkflow
                    ? __('Editing is not allowed for void invoices.')
                    : ($isVoided ? __('Voided invoices cannot be edited') : __('Unapprove the invoice first to edit'));
                $deleteTooltip = $isVoided ? __('Voided invoices cannot be deleted') : __('Unapprove the invoice first to delete');
                $moadianStatus = $showMoadianColumn ? \App\Enums\MoadianStatus::fromData($invoice->latestMoadianHistory?->data['status'] ?? null) : null;
            @endphp
            <tr class="{{ $isVoided ? 'text-gray-500' : '' }}">
                <td class="px-4 py-2">
                    <a href="{{ route('invoices.show', $invoice) }}" class="link link-hover">{{ formatDocumentNumber($invoice->number) }}</a>
                </td>
                <td class="px-4 py-2">
                    <a href="{{ route('customers.show', $invoice->customer) }}">{{ $invoice->customer->name ?? '' }}</a>
                    <br>
                    <span class="text-xs {{ $isVoided ? 'text-gray-400' : 'text-gray-500' }}">{{ $invoice->title ?? '' }}</span>
                </td>
                <td class="px-4 py-2">
                    @if ($invoice->document_id)
                        @can('documents.show')
                            <a href="{{ route('documents.show', $invoice->document_id) }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ formatDocumentNumber($invoice->document->number) ?? '' }}
                            </a>
                        @else
                            <span class="text-gray-500">{{ formatDocumentNumber($invoice->document->number) ?? '' }}</span>
                        @endcan
                    @endif
                </td>
                <td class="px-4 py-2">{{ isset($invoice->date) ? formatDate($invoice->date) : '' }}</td>
                <td class="px-4 py-2">{{ isset($invoice->amount) ? formatNumber($invoice->amount - $invoice->subtraction) : '' }}</td>
                <td class="px-4 py-2">{{ $invoice->status?->label() ?? '' }}</td>
                @if ($showMoadianColumn)
                    <td class="px-4 py-2">
                        @if ($invoice->latestMoadianHistory === null)
                            <span class="text-gray-400">{{ __('Not sent') }}</span>
                        @elseif ($moadianStatus !== null)
                            <span class="badge {{ $moadianStatus->color() }}">{{ $moadianStatus->label() }}</span>
                        @else
                            <span class="badge badge-warning">{{ __('UNKNOWN') }}</span>
                        @endif
                    </td>
                @endif
                <td class="px-4 py-2">
                    <a href="{{ route('invoices.show', $invoice) }}" target="_blank" rel="noopener" class="btn btn-sm btn-info">{{ __('Show') }}</a>

                    @can('invoices.approve')
                        @if ($isSellWorkflow && ($invoice->status->isPreInvoice() || $invoice->status->isRejected()))
                            <form action="{{ route('invoices.change-status', [$invoice, 'ready_to_approve']) }}" method="POST" class="inline-block">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">{{ __('Issue') }}</button>
                            </form>
                        @endif
                        @if ($isSellWorkflow && $invoice->status->isPreInvoice())
                            <form action="{{ route('invoices.change-status', [$invoice, 'rejected']) }}" method="POST" class="inline-block">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-error">{{ __('Reject') }}</button>
                            </form>
                        @endif
                        @if ($canChangeStatus)
                            @if ($invoice->changeStatusValidation->hasErrors())
                                <a data-tip="{{ $invoice->changeStatusValidation->toText() }}" href="{{ route('invoices.conflicts', $invoice) }}"
                                    class="btn btn-sm btn-accent inline-flex tooltip">{{ __('Fix Conflict') }}</a>
                            @elseif ($isVoided)
                                <span class="tooltip" data-tip="{{ __('Unapprove the void invoice first to change status.') }}">
                                    <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed">{{ __('Unapprove') }}</button>
                                </span>
                            @else
                                <form action="{{ route('invoices.change-status', [$invoice, $canUnapprove ? 'unapproved' : 'approved']) }}{{ $invoice->changeStatusValidation->hasWarning() ? '?confirm=1' : '' }}"
                                    method="POST" class="inline-block {{ $invoice->changeStatusValidation->hasWarning() ? 'change-status-form' : '' }}">
                                    @csrf
                                    <button type="submit" x-data="{}" data-tip="{{ $invoice->changeStatusValidation->toText() }}"
                                        class="btn btn-sm inline-flex tooltip {{ $canUnapprove ? 'btn-warning' : 'btn-success' }} {{ $canApprove && $invoice->changeStatusValidation->hasWarning() ? 'btn-outline' : '' }}">
                                        {{ $canUnapprove ? __('Unapprove') : __('Approve') }}
                                    </button>
                                </form>
                            @endif
                        @endif
                    @endcan

                    @if (!$editDisabled)
                        <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                    @else
                        <span class="tooltip" data-tip="{{ $editTooltip }}">
                            <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed">{{ __('Edit') }}</button>
                        </span>
                    @endif

                    @if (!$deleteDisabled)
                        <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                        </form>
                    @else
                        <span class="tooltip" data-tip="{{ $deleteTooltip }}">
                            <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed">{{ __('Delete') }}</button>
                        </span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@if (request('status') !== null)
    @php
        $backParams = ['invoice_type' => $invoiceType];
        if (in_array($invoiceType, ['buy', 'return_buy'])) {
            $backParams['service_buy'] = request('service_buy');
        }
    @endphp
    <div class="px-4 py-2 text-left">
        <a class="btn btn-primary" href="{{ route('invoices.index', $backParams) }}">{{ __('Back') }}</a>
    </div>
@endif

{{ $invoices->withQueryString()->links() }}
