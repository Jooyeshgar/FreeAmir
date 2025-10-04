<x-app-layout>
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
                @else
                    <a href="{{ route('invoices.create', ['invoice_type' => 'sell']) }}" class="btn btn-primary">
                        {{ __('Create sell invoice') }}
                    </a>
                @endif

                <form action="{{ route('invoices.index') }}" method="GET" class="ml-auto">
                    <div class="mt-4 mb-4 grid grid-cols-6 gap-6">
                        <div class="col-span-2 md:col-span-1">
                            <x-input name="number" value="{{ request('number') }}"
                                placeholder="{{ __('Invoice Number') }}" />
                        </div>
                        <div class="col-span-2 md:col-span-1">
                            <x-input name="date" placeholder="{{ __('date') }}" value="{{ request('date') }}"></x-input>
                        </div>
                        <div class="col-span-6 md:col-span-3">
                            <x-input name="text" value="{{ request('text') }}"
                                placeholder="{{ __('Search by customer name or transaction description') }}" />
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
                        <th class="px-4 py-2">{{ __('Code') }}</th>
                        <th class="px-4 py-2">{{ __('Customer') }}</th>
                        <th class="px-4 py-2">{{ __('Document') }}</th>
                        <th class="px-4 py-2">{{ __('Date') }}</th>
                        <th class="px-4 py-2">{{ __('Amount') }}</th>
                        <th class="px-4 py-2">{{ __('Status') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-2">
                                {{ convertToFarsi($invoice->number) }}
                            </td>
                            <td class="px-4 py-2">{{ $invoice->customer->name ?? '' }}</td>
                            <td class="px-4 py-2">
                                @if ($invoice->document)
                                    <a class="link"
                                        href="{{ route('documents.show', $invoice->document_id) }}">{{ formatDocumentNumber($invoice->document->number) }}</a>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ isset($invoice->date) ? formatDate($invoice->date) : '' }}</td>
                            <td class="px-4 py-2">{{ isset($invoice->amount) ? formatNumber($invoice->amount) : '' }}
                            </td>
                            <td class="px-4 py-2">
                                {{ $invoice->permanent ?? false ? __('Permanent') : __('Draft') }}
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('invoices.show', $invoice) }}"
                                    class="btn btn-sm btn-info">{{ __('View') }}</a>
                                {{-- <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-info">{{
                                    __('Edit') }}</a> --}}
                                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
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
</x-app-layout>