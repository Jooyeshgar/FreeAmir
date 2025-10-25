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
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="p-2 w-20">{{ __('Invoice') }}</th>
                        <th class="p-2 w-40">{{ __('Cost Type') }}</th>
                        <th class="p-2 w-20">{{ __('Date') }}</th>
                        <th class="p-2 w-20">{{ __('Amount') }}</th>
                        <th class="p-2 w-40">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($ancillaryCosts as $ancillaryCost)
                        <tr>
                            <td class="p-2">
                                <a class="link"
                                    href="{{ route('invoices.show', $ancillaryCost->invoice_id) }}">{{ formatDocumentNumber($ancillaryCost->invoice->number) ?? '' }}</a>
                            </td>
                            <td class="p-2">{{ $ancillaryCost->description }}</td>
                            <td class="p-2">{{ formatDate($ancillaryCost->date) }}</td>
                            <td class="p-2">{{ formatNumber($ancillaryCost->amount) }}</td>
                            <td class="p-2">
                                <a href="{{ route('ancillary-costs.edit', $ancillaryCost) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('ancillary-costs.destroy', $ancillaryCost) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($ancillaryCosts->hasPages())
                <div class="join">
                    {{-- Previous Page Link --}}
                    @if ($ancillaryCosts->onFirstPage())
                        <input class="join-item btn btn-square hidden " type="radio" disabled>
                    @else
                        <a href="{{ $ancillaryCosts->previousPageUrl() }}" class="join-item btn btn-square">&lsaquo;</a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($ancillaryCosts->getUrlRange(1, $ancillaryCosts->lastPage()) as $page => $url)
                        @if ($page == $ancillaryCosts->currentPage())
                            <a href="{{ $url }}" class="join-item btn btn-square bg-blue-500 text-white">{{ $page }}</a>
                        @else
                            <a href="{{ $url }}" class="join-item btn btn-square">{{ $page }}</a>
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($ancillaryCosts->hasMorePages())
                        <a href="{{ $ancillaryCosts->nextPageUrl() }}" class="join-item btn btn-square">&rsaquo;</a>
                    @else
                        <input class="join-item btn btn-square hidden" type="radio" disabled>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>