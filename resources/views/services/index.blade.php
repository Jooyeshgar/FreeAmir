<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Services') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('services.create') }}" class="btn btn-primary">{{ __('Create service') }}</a>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Sell price') }}</th>
                        <th class="px-4 py-2">{{ __('VAT') }}</th>
                        <th class="px-4 py-2">{{ __('Service group') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($services as $service)
                        <tr>
                            <td class="px-4 py-2">{{ formatNumber($service->code) }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('services.show', $service) }}" class="text-primary">
                                    {{ $service->name }}</a>
                            </td>
                            <td class="px-4 py-2">{{ formatNumber($service->selling_price) }}</td>
                            <td class="px-4 py-2">{{ formatNumber($service->vat) }}%</td>
                            <td class="px-4 py-2">{{ $service->serviceGroup ? $service->serviceGroup->name : '' }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('services.edit', $service) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                    @if ($service->invoiceItems()->exists())
                                        <span class="btn btn-sm btn-disabled" title="{{ __('Cannot delete service that is used in invoice items') }}">{{ __('Delete') }}</span>
                                    @else
                                        <form action="{{ route('services.destroy', $service) }}" method="POST" class="inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                        </form>    
                                    @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($services->hasPages())
                <div class="join">
                    {{-- Previous Page Link --}}
                    @if ($services->onFirstPage())
                        <input class="join-item btn btn-square hidden " type="radio" disabled>
                    @else
                        <a href="{{ $services->previousPageUrl() }}" class="join-item btn btn-square">&lsaquo;</a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($services->getUrlRange(1, $services->lastPage()) as $page => $url)
                        @if ($page == $services->currentPage())
                            <a href="{{ $url }}" class="join-item btn btn-square bg-blue-500 text-white">{{ $page }}</a>
                        @else
                            <a href="{{ $url }}" class="join-item btn btn-square">{{ $page }}</a>
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($services->hasMorePages())
                        <a href="{{ $services->nextPageUrl() }}" class="join-item btn btn-square">&rsaquo;</a>
                    @else
                        <input class="join-item btn btn-square hidden" type="radio" disabled>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
