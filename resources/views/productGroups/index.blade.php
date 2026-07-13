<x-app-layout :title="__('Products Group')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Products Group') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your product groups and their accounts') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2">
            <a href="{{ route('product-groups.create') }}" class="btn btn-sm btn-primary">{{ __('Create product group') }}</a>
        </div>
    </div>

    {{-- Product Group List --}}
    <div class="card bg-base-100 shadow-sm border border-base-200 mx-1 mb-6">
        <div class="card-body p-0">
            {{-- Card Header --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-base-200">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-bold text-base-content">{{ __('Products Group') }}</h2>
                    <span class="badge badge-ghost">
                        {{ localizeNumber($productGroups->total()) }} {{ __('records') }}
                    </span>
                </div>
            </div>

            <div class="p-4 sm:p-5">
            <table class="table w-full overflow-auto">
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
                            <td class="px-4 py-2">{{ formatNumber($productGroup->products_count ?? $productGroup->products()->count()) }}</td>
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
                                <a href="{{ route('product-groups.show', $productGroup) }}"
                                    class="btn btn-sm btn-info">{{ __('View') }}</a>
                                <a href="{{ route('product-groups.edit', $productGroup) }}"
                                    class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                @if ($productGroup->delete_blocking_reason)
                                    <span class="tooltip" data-tip="{{ $productGroup->delete_blocking_reason }}">
                                        <button class="btn btn-sm btn-error btn-disabled cursor-not-allowed" disabled title="{{ $productGroup->delete_blocking_reason }}">{{ __('Delete') }}</button>
                                    </span>
                                @else
                                    <form action="{{ route('product-groups.destroy', $productGroup) }}" method="POST" class="inline-block">
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
            </div>

            {{-- Pagination --}}
            @if ($productGroups->hasPages())
                <div class="px-5 py-4 border-t border-base-200">
                    {!! $productGroups->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
