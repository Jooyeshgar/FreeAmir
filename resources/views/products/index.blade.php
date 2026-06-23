<x-app-layout :title="__('Products')">
    <x-show-message-bags />

    {{-- Page Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 px-1 pb-5">
        <div class="min-w-48">
            <h1 class="text-xl font-bold text-base-content">{{ __('Products') }}</h1>
            <p class="text-sm text-base-content/50 mt-0.5">{{ __('Manage your products and inventory') }}</p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-2">
            <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Create product') }}
            </a>
            @can('products.report')
                <button type="button" class="btn btn-secondary btn-sm gap-1.5" onclick="document.getElementById('report-pdf-modal').showModal()">
                    {{ __('Warehouse Report') }}
                </button>
            @endcan
            <a href="{{ route('products.export') }}" class="btn btn-primary btn-sm gap-1.5">{{ __('Export CSV') }}</a>
            <a href="{{ route('products.import') }}" class="btn btn-primary btn-sm gap-1.5">{{ __('Import CSV') }}</a>
        </div>
    </div>

    @can('products.report')
        @php
            $optionalColumns = [
                'category' => __('Category'),
                'code' => __('Product code'),
                'selling_price' => __('Sale price'),
                'cost_of_goods' => __('Cost of goods'),
                'last_item_cost' => __('Last item cost'),
                'sales_profit' => __('Sales profit'),
                'revenue_account' => __('Revenue account amount'),
                'cogs_account' => __('COGS account amount'),
                'inventory_account' => __('Inventory account amount'),
                'sales_return_account' => __('Sales return account amount'),
            ];
        @endphp
        <dialog id="report-pdf-modal" class="modal">
            <div class="modal-box max-w-2xl">
                <h3 class="text-lg font-bold">{{ __('Warehouse Report') }}</h3>

                <form action="{{ route('products.report') }}" method="GET" target="_blank">
                    <x-input name="name" value="{{ request('name') }}" hidden />
                    <x-input name="group_name" value="{{ request('group_name') }}" hidden />
                    <x-input name="min_quantity" value="{{ request('min_quantity') }}" hidden />
                    <x-input name="cols_submitted" value="1" hidden />

                    <div class="text-info mt-2">
                        {{ __('The column product name is always reported.') }}
                    </div>

                    <div class="mt-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-semibold">{{ __('Optional columns') }}</span>
                            <label class="flex cursor-pointer items-center gap-2 text-xs">
                                <input type="checkbox" id="report-cols-toggle" class="checkbox checkbox-sm" checked
                                    onchange="document.querySelectorAll('#report-pdf-modal input[name=&quot;columns[]&quot;]').forEach(cb => cb.checked = this.checked)">
                                <span>{{ __('Select All') }}</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                            @foreach ($optionalColumns as $key => $label)
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="checkbox" name="columns[]" value="{{ $key }}" class="checkbox checkbox-sm" checked
                                        onchange="document.getElementById('report-cols-toggle').checked = document.querySelectorAll('#report-pdf-modal input[name=&quot;columns[]&quot;]:checked').length === document.querySelectorAll('#report-pdf-modal input[name=&quot;columns[]&quot;]').length">
                                    <span class="label-text">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('report-pdf-modal').close()">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Print PDF') }}</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button aria-label="close"></button>
            </form>
        </dialog>
    @endcan

    {{-- Product List --}}
    <div class="card bg-base-100 shadow-sm border border-base-200 mx-1 mb-6">
        <div class="card-body p-0">
            {{-- Card Header: title + filters --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-base-200">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-base font-bold text-base-content">{{ __('Product List') }}</h2>
                    <span class="badge badge-ghost">
                        {{ localizeNumber($products->total()) }} {{ __('records') }}
                    </span>
                </div>

                <form action="{{ route('products.index') }}" method="GET" class="flex flex-wrap items-center gap-2" dir="ltr">
                    <div class="relative w-40 max-w-full [&_.input]:input-sm" dir="rtl">
                        <x-input type="text" name="name" value="{{ request('name') }}" placeholder="{{ __('Product Name') }}" />
                    </div>

                    <div class="relative w-40 max-w-full [&_.input]:input-sm" dir="rtl">
                        <x-input type="text" name="code" value="{{ request('code') }}" placeholder="{{ __('Product code') }}" />
                    </div>

                    <div class="relative w-40 max-w-full [&_.input]:input-sm" dir="rtl">
                        <x-input type="text" name="group_name" value="{{ request('group_name') }}" placeholder="{{ __('Product Group Name') }}" />
                    </div>

                    <div class="relative w-40 max-w-full [&_.input]:input-sm" dir="rtl">
                        <x-input type="number" name="min_quantity" value="{{ request('min_quantity') }}" placeholder="{{ __('Min quantity') }}" />
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary gap-1.5" dir="rtl">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z" />
                        </svg>
                        {{ __('Search') }}
                    </button>
                </form>
            </div>

            <div class="p-4 sm:p-5">
                <table class="table w-full overflow-auto">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">{{ __('Product Code') }}</th>
                            <th class="px-4 py-2">{{ __('Name') }}</th>
                            <th class="px-4 py-2">{{ __('Quantity') }}</th>
                            <th class="px-4 py-2">{{ __('Total Sell') }}</th>
                            <th class="px-4 py-2">{{ __('Average Cost') }}</th>
                            <th class="px-4 py-2">{{ __('Sell price') }}</th>
                            @can('reports.journal')
                                <th class="px-4 py-2">{{ __('Sales profit') }}</th>
                                <th class="px-4 py-2"> </th>
                                <th class="px-4 py-2">{{ __('Total Sell') }}</th>
                            @endcan
                            <th class="px-4 py-2">{{ __('Product group') }}</th>
                            <th class="px-4 py-2">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach ($products as $product)
                            <tr>
                                <td class="px-4 py-2">{{ localizeNumber($product->code) }}</td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('products.show', $product) }}" class="text-primary">
                                        {{ $product->name }}</a>
                                </td>
                                <td class="px-4 py-2">
                                    {{ formatNumber($product->quantity) }}
                                    @if ($product->unapprovedQuantity != 0)
                                        <span class="text-red-400"> / {{ formatNumber($product->unapprovedQuantity) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ formatNumber($product->totalSellCount) }}</td>
                                <td class="px-4 py-2">{{ formatNumber($product->average_cost) }}</td>
                                <td class="px-4 py-2">{{ formatNumber($product->selling_price) }}</td>
                                @can('reports.journal')
                                    <td class="px-4 py-2">{{ formatNumber($product->salesProfit) }}</td>
                                    <td class="px-4 py-2">
                                        {{ $product->totalSell != 0 ? formatNumber(round(($product->salesProfit / $product->totalSell) * 100, 2)) : 0 }}%</td>
                                    <td class="px-4 py-2">{{ formatNumber($product->totalSell) }}</td>
                                @endcan
                                <td class="px-4 py-2">
                                    <a href="{{ route('product-groups.show', $product->productGroup) }}">{{ $product->productGroup->name }}</a>
                                </td>
                                <td class="px-4 py-2">
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-info">{{ __('Edit') }}</a>
                                    @if ($product->invoiceItems()->exists())
                                        <span class="tooltip" data-tip="{{ __('Cannot delete product that is used in invoice items') }}">
                                            <button class="btn btn-sm btn-info btn-disabled cursor-not-allowed" disabled
                                                title="{{ __('Cannot delete product that is used in invoice items') }}">{{ __('Delete') }}</button>
                                        </span>
                                    @else
                                        <form action="{{ route('products.destroy', $product) }}" method="POST" class="inline-block">
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
            @if ($products->hasPages())
                <div class="px-5 py-4 border-t border-base-200">
                    {!! $products->withQueryString()->links() !!}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
