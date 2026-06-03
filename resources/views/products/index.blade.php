<x-app-layout :title="__('Products')">
    <x-show-message-bags />
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="card-actions">
                <a href="{{ route('products.create') }}" class="btn btn-primary">{{ __('Create product') }}</a>
                @can('products.report')
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('report-pdf-modal').showModal()">
                        {{ __('Warehouse Report') }}
                    </button>
                @endcan
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
                                {{ __('The columns :columns are always reported.', ['columns' => __('Product name') . '، ' . __('Inbound quantity') . '، ' . __('Outbound quantity') . '، ' . __('Current stock')]) }}
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
                                <button type="submit" class="btn btn-primary">{{ __('Download PDF') }}</button>
                            </div>
                        </form>
                    </div>
                    <form method="dialog" class="modal-backdrop">
                        <button aria-label="close"></button>
                    </form>
                </dialog>
            @endcan

            <form action="{{ route('products.index') }}" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 w-full md:w-3/5">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-2 flex items-center text-gray-400 text-sm">
                            <i class="fa-solid fa-box"></i>
                        </span>
                        <x-input type="text" name="name" value="{{ request('name') }}" placeholder="{{ __('Product Name') }}" />
                    </div>

                    <div class="relative">
                        <span class="absolute inset-y-0 left-2 flex items-center text-gray-400 text-sm">
                            <i class="fa-solid fa-layer-group"></i>
                        </span>
                        <x-input type="text" name="group_name" value="{{ request('group_name') }}" placeholder="{{ __('Product Group Name') }}" />
                    </div>

                    <div class="relative">
                        <span class="absolute inset-y-0 left-2 flex items-center text-gray-400 text-sm">
                            <i class="fa-solid fa-warehouse"></i>
                        </span>
                        <x-input type="number" name="min_quantity" value="{{ request('min_quantity') }}" placeholder="{{ __('Min quantity') }}" />
                    </div>

                    <div class="flex items-center">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 text-sm rounded-lg shadow transition-all">
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
                            <td class="px-4 py-2">{{ convertToFarsi($product->code) }}</td>
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
            {!! $products->withQueryString()->links() !!}
        </div>
    </div>
</x-app-layout>
