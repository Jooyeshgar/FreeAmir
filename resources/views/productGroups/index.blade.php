<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Products Group') }}
        </h2>
    </x-slot>
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('product-groups.create') }}"
                    class="btn btn-primary">{{ __('Create product group') }}</a>
            </div>

            <table class="table w-full mt-4 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2">{{ __('SSTID') }}</th>
                        <th class="px-4 py-2">{{ __('Name') }}</th>
                        <th class="px-4 py-2">{{ __('VAT') }}</th>
                        <th class="px-4 py-2">{{ __('Products') }}</th>
                        <th class="px-4 py-2">{{ __('Inventory Subject') }}</th>
                        <th class="px-4 py-2">{{ __('Income Subject') }}</th>
                        <th class="px-4 py-2">{{ __('Return Sales Subject') }}</th>
                        <th class="px-4 py-2">{{ __('COGS Subject') }}</th>
                        <th class="px-4 py-2">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach ($productGroups as $productGroup)
                        <tr>
                            <td class="px-4 py-2">{{ $productGroup->sstid }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('products.index', ['group_name' => $productGroup->name]) }}"
                                    class="text-blue-600 hover:underline">
                                    {{ $productGroup->name }}
                                </a>
                            </td>
                            <td class="px-4 py-2">{{ formatNumber($productGroup->vat) }}%</td>
                            <td class="px-4 py-2">{{ formatNumber($productGroup->products->count()) }}</td>
                            <td class="px-4 py-2"><a
                                    href="{{ route('transactions.index', ['subject_id' => $productGroup->inventorySubject]) }}">{{ $productGroup->inventorySubject?->name }}</a>
                            </td>
                            <td class="px-4 py-2"><a
                                    href="{{ route('transactions.index', ['subject_id' => $productGroup->incomeSubject]) }}">{{ $productGroup->incomeSubject?->name }}</a>
                            </td>
                            <td class="px-4 py-2"><a
                                    href="{{ route('transactions.index', ['subject_id' => $productGroup->salesReturnsSubject]) }}">{{ $productGroup->salesReturnsSubject?->name }}</a>
                            </td>
                            <td class="px-4 py-2"><a
                                    href="{{ route('transactions.index', ['subject_id' => $productGroup->cogsSubject]) }}">{{ $productGroup->cogsSubject?->name }}</a>
                            </td>
                            <td class="px-4 py-2">
                                <a href="{{ route('product-groups.edit', $productGroup) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                <form action="{{ route('product-groups.destroy', $productGroup) }}" method="POST"
                                    class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-error">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {!! $productGroups->links() !!}
        </div>
    </div>
</x-app-layout>
