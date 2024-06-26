<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products') }}
        </h2>
    </x-slot>
    <x-show-message-bags/>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('products.create') }}" class="btn btn-primary">Create product</a>
            </div>
            <table class="table w-full mt-4 overflow-auto">
                <thead>
                <tr>
                    <th class="px-4 py-2">کد</th>
                    <th class="px-4 py-2">نام</th>
                    <th class="px-4 py-2">مقدار</th>
                    <th class="px-4 py-2">قیمت خرید</th>
                    <th class="px-4 py-2">قیمت فروش</th>
                    <th class="px-4 py-2">گروه کالا</th>
                    <th class="px-4 py-2">Action</th>
                </tr>
                </thead>
                <tbody>

                @foreach ($products as $product)
                    <tr>
                        <td class="px-4 py-2">{{ $product->code }}</td>
                        <td class="px-4 py-2">{{ $product->name }}</td>
                        <td class="px-4 py-2">{{ $product->quantity }}</td>
                        <td class="px-4 py-2">{{ $product->purchace_price }}</td>
                        <td class="px-4 py-2">{{ $product->selling_price }}</td>
                        <td class="px-4 py-2">{{ $product->productGroup ? $product->productGroup->name : '' }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ route('products.edit', $product) }}"
                                class="btn btn-sm btn-info">Edit</a>
                            <form action="{{ route('products.destroy', $product) }}" method="POST"
                                    class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-error">Delete</button>
                            </form>
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
                            <a href="{{ $url }}" class="join-item btn btn-square bg-blue-500 text-white">{{ $page }}</a>
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
