<x-app-layout :title="$warehouse->name">
    <div class="mb-6 flex gap-4 rounded-2xl bg-gradient-to-br from-primary/10 via-base-100 to-secondary/10 p-4 shadow-sm ring-1 ring-base-200 flex-row items-center justify-between">
        <div class="min-w-0">
            <h1 class="break-words text-xl font-black tracking-tight sm:text-2xl">{{ $warehouse->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-base-content/60">
                <span class="badge badge-ghost">{{ $warehouse->code ?: __('No code') }}</span>
                <span>{{ __('Products') }}: {{ localizeNumber($stocks->total()) }}</span>
            </div>
        </div>
        <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap">
            <a class="btn btn-sm btn-primary w-full sm:w-auto" href="{{ route('warehouses.edit', $warehouse) }}">{{ __('Edit') }}</a>
            @php
                $deleteDisabledReason = match (true) {
                    $warehouseCount <= 1 => __('At least one warehouse must remain.'),
                    $warehouse->stocks->where('quantity', '>', 0)->isNotEmpty() => __('A warehouse with stock cannot be deleted.'),
                    ($warehouse->transfersFrom->count() + $warehouse->transfersTo->count()) > 0 => __('A warehouse with transfer history cannot be deleted.'),
                    default => null,
                };
            @endphp
            @if ($deleteDisabledReason)
                <span class="lg:tooltip" data-tip="{{ $deleteDisabledReason }}">
                    <button type="button" class="btn btn-sm btn-error btn-disabled w-full cursor-not-allowed sm:w-auto" title="{{ $deleteDisabledReason }}">{{ __('Delete') }}</button>
                </span>
            @else
                <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-error w-full sm:w-auto">{{ __('Delete') }}</button>
                </form>
            @endif
            <a href="{{ route('warehouses.index') }}" class="btn btn-sm col-span-2 w-full sm:w-auto">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {{ __('Back') }}
            </a>
        </div>
    </div>

    <div class="card overflow-hidden border border-base-200 bg-base-100 shadow-sm">
        <div class="border-b border-base-200 px-5 py-4">
            <h2 class="font-bold">{{ __('Products in warehouse') }}</h2>
            <p class="text-sm text-base-content/50">{{ __('Select a product to open its details.') }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="table min-w-[48rem]">
                <thead>
                    <tr>
                        <th>{{ __('Product') }}</th>
                        <th>{{ __('Product group') }}</th>
                        <th>{{ __('Stock') }}</th>
                        <th>{{ __('Average Cost') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stock)
                        <tr class="hover:bg-base-200/50">
                            <td>
                                <a class="font-bold text-primary hover:underline" href="{{ route('products.show', $stock->product) }}">{{ $stock->product->name }}</a>
                            </td>
                            <td>{{ $stock->product->productGroup?->name ?: '—' }}</td>
                            <td>{{ formatNumber($stock->quantity) }}</td>
                            <td>{{ formatNumber($stock->average_cost) }}</td>
                            <td>
                                <a class="btn btn-sm btn-outline" href="{{ route('warehouses.transfer', ['from_warehouse_id' => $warehouse->id, 'product_id' => $stock->product_id]) }}">{{ __('Transfer') }}</a>
                                <a class="btn btn-sm btn-ghost" href="{{ route('products.show', $stock->product) }}">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-base-content/50">{{ __('No products found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($stocks->hasPages())
            <div class="border-t border-base-200 px-5 py-4">{{ $stocks->links() }}</div>
        @endif
    </div>
</x-app-layout>
