<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('products.create') }}" class="btn btn-primary">{{ __('Create product') }}</a>
            </div>

            <form action="{{ route('products.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full md:w-2/5">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-2 flex items-center text-gray-400 text-sm">
                            <i class="fa-solid fa-box"></i>
                        </span>
                        <input type="text" name="name" value="{{ request('name') }}"
                            placeholder="{{ __('Product Name') }}"
                            class="w-full pl-8 pr-2 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    </div>

                    <div class="relative">
                        <span class="absolute inset-y-0 left-2 flex items-center text-gray-400 text-sm">
                            <i class="fa-solid fa-layer-group"></i>
                        </span>
                        <input type="text" name="group_name" value="{{ request('group_name') }}"
                            placeholder="{{ __('Product Group Name') }}"
                            class="w-full pl-8 pr-2 py-2 text-sm rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 focus:border-blue-500" />
                    </div>

                    <div class="flex items-center">
                        <button type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 text-sm rounded-lg shadow transition-all">
                            <i class="fa-solid fa-magnifying-glass mr-1"></i>
                            {{ __('Search') }}
                        </button>
                    </div>
                </div>
            </form>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('Product Code') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('Quantity') }}</th>
                        <th class="px-4 py-2">{{ __('Average Cost') }}</th>
                        <th class="px-4 py-2">{{ __('Sell price') }}</th>
                        <th class="px-4 py-2">{{ __('VAT') }}</th>
                        <th class="px-4 py-2">{{ __('Product group') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($products as $product)
                        <tr>
                            <td class="px-4 py-2">{{ formatNumber($product->code) }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('products.show', $product) }}" class="text-primary">
                                    {{ $product->name }}</a>
                            </td>
                            <td class="px-4 py-2">{{ formatNumber($product->quantity) }}</td>
                            <td class="px-4 py-2">{{ formatNumber($product->average_cost) }}</td>
                            <td class="px-4 py-2">{{ formatNumber($product->selling_price) }}</td>
                            <td class="px-4 py-2">{{ formatNumber($product->vat) }}%</td>
                            <td class="px-4 py-2">{{ $product->productGroup ? $product->productGroup->name : '' }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('products.edit', $product) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                @if ($product->invoiceItems()->exists())
                                    <span class="btn btn-sm btn-disabled"
                                        title="{{ __('Cannot delete product that is used in invoice items') }}">{{ __('Delete') }}</span>
                                @else
                                    <form action="{{ route('products.destroy', $product) }}" method="POST"
                                        class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($products->hasPages())
                <div class="join">
                    {{-- Previous Page Link --}}
                    @if ($products->onFirstPage())
                        <input class="join-item btn btn-square hidden " type="radio" disabled>
                    @else
                        <a href="{{ $products->previousPageUrl() }}" class="join-item btn btn-square">&lsaquo;</a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($products->getUrlRange(1, $products->lastPage()) as $page => $url)
                        @if ($page == $products->currentPage())
                            <a href="{{ $url }}"
                                class="join-item btn btn-square bg-blue-500 text-white">{{ $page }}</a>
                        @else
                            <a href="{{ $url }}" class="join-item btn btn-square">{{ $page }}</a>
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}" class="join-item btn btn-square">&rsaquo;</a>
                    @else
                        <input class="join-item btn btn-square hidden" type="radio" disabled>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
