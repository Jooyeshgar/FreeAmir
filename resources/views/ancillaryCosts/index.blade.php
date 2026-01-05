<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ancillary Costs') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('ancillary-costs.create') }}" class="btn btn-primary">{{ __('Create Ancillary Cost') }}</a>
            </div>

            <dl class="grid grid-cols-4 gap-3">
                @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                    <div class="bg-base-100 p-3 rounded-md border">
                        <dd class="text-sm font-semibold">
                            @if ($ancillaryCosts->where('status', $status)->count() == 0 || request('status') == $status->value)
                                <span class="text-gray-500">{{ $status->label() }} :
                                    {{ convertToFarsi($ancillaryCosts->where('status', $status)->count()) }}</span>
                            @else
                                <a class="link link-hover" href="{{ route('ancillary-costs.index', ['status' => $status]) }}">
                                    {{ $status->label() }} :
                                    {{ convertToFarsi($ancillaryCosts->where('status', $status)->count()) }}
                                </a>
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="p-2">{{ __('Number') }}</th>
                        <th class="p-2">{{ __('Doc Number') }}</th>
                        <th class="p-2">{{ __('Invoice Number') }}</th>
                        <th class="p-2">{{ __('Cost Type') }}</th>
                        <th class="p-2">{{ __('Date') }}</th>
                        <th class="p-2">{{ __('Amount') }} ({{ config('amir.currency') ?? __('Rial') }})</th>
                        <th class="p-2">{{ __('Status') }}</th>
                        <th class="p-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($ancillaryCosts as $ancillaryCost)
                        <tr>
                            <td class="p-2">{{ $ancillaryCost->number }}</td>
                            <td class="p-2">
                                @can('documents.show')
                                    @if ($ancillaryCost->document_id)
                                        <a href="{{ route('documents.show', $ancillaryCost->document_id) }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </a>&nbsp;
                                        <a class="link" href="{{ route('documents.edit', $ancillaryCost->document_id) }}">
                                            {{ formatDocumentNumber($ancillaryCost->document->number) ?? '' }}</a>
                                    @endif
                                @else
                                    <span class="text-gray-500">{{ formatDocumentNumber($ancillaryCost->document->number) ?? '' }}</span>
                                @endcan
                            </td>
                            <td class="p-2">
                                <a class="link"
                                    href="{{ route('invoices.show', $ancillaryCost->invoice_id) }}">{{ formatDocumentNumber($ancillaryCost->invoice->number) ?? '' }}</a>
                            </td>
                            <td class="p-2">{{ $ancillaryCost->type->label() }}</td>
                            <td class="p-2">{{ formatDate($ancillaryCost->date) }}</td>
                            <td class="p-2">{{ formatNumber($ancillaryCost->amount) }}</td>
                            <td class="p-2">
                                {{ $ancillaryCost->status?->label() ?? '' }}
                            </td>
                            <td class="p-2">
                                <a href="{{ route('ancillary-costs.show', $ancillaryCost) }}" class="btn btn-sm btn-info">{{ __('Show') }}</a>

                                @can('ancillary-costs.approve')
                                    @if ($ancillaryCost->changeStatusValidation['allowed'])
                                        <a href="{{ route('ancillary-costs.change-status', [$ancillaryCost, $ancillaryCost->status?->isApproved() ? 'unapprove' : 'approve']) }}"
                                            class="btn btn-sm {{ $ancillaryCost->status?->isApproved() ? 'btn-warning' : 'btn-success' }}">
                                            {{ __($ancillaryCost->status?->isApproved() ? 'Unapprove' : 'Approve') }}
                                        </a>
                                    @else
                                        <span class="tooltip" data-tip="{{ $ancillaryCost->changeStatusValidation['reason'] }}">
                                            <button class="btn btn-sm {{ $ancillaryCost->status?->isApproved() ? 'btn-warning' : 'btn-success' }} btn-disabled cursor-not-allowed" disabled
                                                title="{{ $ancillaryCost->changeStatusValidation['reason'] }}">{{ $ancillaryCost->status?->isApproved() ? __('Unapprove') : __('Approve') }}</button>
                                        </span>
                                    @endif
                                @endcan
                                
                                @if ($ancillaryCost->editDeleteStatus['allowed'])
                                    @if (!$ancillaryCost->status->isApproved())
                                        <a href="{{ route('ancillary-costs.edit', $ancillaryCost) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                        <form action="{{ route('ancillary-costs.destroy', $ancillaryCost) }}" method="POST" class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                        </form>
                                    @else
                                        <span class="tooltip" data-tip="{{ __('Unapprove the ancillary cost first to edit') }}">
                                            <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed" disabled
                                                title="{{ __('Unapprove the ancillary cost first to edit') }}">{{ __('Edit') }}</button>
                                        </span>
                                        <span class="tooltip" data-tip="{{ __('Unapprove the ancillary cost first to delete') }}">
                                            <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed" disabled
                                                title="{{ __('Unapprove the ancillary cost first to delete') }}">{{ __('Delete') }}</button>
                                        </span>
                                    @endif
                                @else
                                    <span class="tooltip" data-tip="{{ $ancillaryCost->editDeleteStatus['reason'] }}">
                                        <button class="btn btn-sm btn-info btn-disabled cursor-not-allowed" disabled
                                            title="{{ $ancillaryCost->editDeleteStatus['reason'] }}">{{ __('Edit') }}</button>
                                    </span>
                                    <span class="tooltip" data-tip="{{ $ancillaryCost->editDeleteStatus['reason'] }}">
                                        <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed" disabled
                                            title="{{ $ancillaryCost->editDeleteStatus['reason'] }}">{{ __('Delete') }}</button>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if (request('status') !== null)
                <div class="px-4 py-2 text-left">
                    <a class="btn btn-primary" href="{{ route('ancillary-costs.index') }}">{{ __('Back') }}</a>
                </div>
            @endif

            {{ $ancillaryCosts->withQueryString()->links() }}

        </div>
    </div>
</x-app-layout>
