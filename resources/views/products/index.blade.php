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
                {{ __('Create product') }}
            </a>
            @can('products.report')
                <button type="button" class="btn btn-secondary btn-sm gap-1.5" onclick="document.getElementById('report-pdf-modal').showModal()">
                    {{ __('Warehouse Report') }}
                </button>
            @endcan
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('product-csv-modal').showModal()">{{ __('Receive Report') }}</button>
            <a href="{{ route('products.import') }}" class="btn btn-outline btn-sm">{{ __('Import CSV') }}</a>
        </div>
    </div>

    <dialog id="product-csv-modal" class="modal">
        <div class="modal-box max-w-3xl">
            <h3 class="text-lg font-bold">{{ __('Receive Report') }}</h3>

            <form id="product-csv-export-form" action="{{ route('products.export') }}" method="GET">
                @csrf
                <input type="hidden" name="export" value="products_csv">
                <x-input name="cols_submitted" value="1" hidden />

                <div class="mt-2 text-info">{{ __('The name column is always exported.') }}</div>

                <div class="mt-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-semibold">{{ __('Optional columns') }}</span>
                        <div class="text-xs [&_.fieldset]:m-0 [&_.label]:p-0 [&_.checkbox]:checkbox-sm">
                            <x-checkbox name="" id="product-csv-cols-toggle" :title="__('Select All')" :checked="true"
                                onchange="document.querySelectorAll('#product-csv-modal input[name=&quot;columns[]&quot;]').forEach(cb => cb.checked = this.checked)" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($csvColumns as $key => $label)
                            @continue($key === 'name')
                            <div class="[&_.fieldset]:m-0 [&_.label]:p-0 [&_.checkbox]:checkbox-sm">
                                <x-checkbox name="columns[]" :id="'product-csv-column-'.$key" :value="$key" :title="$label" :checked="true"
                                    onchange="document.getElementById('product-csv-cols-toggle').checked = document.querySelectorAll('#product-csv-modal input[name=&quot;columns[]&quot;]:checked').length === document.querySelectorAll('#product-csv-modal input[name=&quot;columns[]&quot;]').length" />
                            </div>
                        @endforeach
                    </div>
                </div>

                <label class="fieldset w-full mt-4">
                    <span class="label">{{ __('Recipient email') }}</span>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required maxlength="255" class="input input-bordered w-full" dir="ltr" autocomplete="email">
                </label>
                <p class="mt-2 text-sm text-base-content/70">{{ __('You can replace your email address to send this report to someone else. The email will identify you as the requester.') }}</p>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('product-csv-modal').close()">{{ __('Cancel') }}</button>
                    <button type="submit" name="delivery" value="download" formaction="{{ route('report') }}" formmethod="POST" formnovalidate class="btn btn-primary">{{ __('Download') }}</button>
                    <button type="submit" name="delivery" value="email" formaction="{{ route('report') }}" formmethod="POST" class="btn btn-secondary">{{ __('Send to email') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button aria-label="close"></button>
        </form>
    </dialog>

    @can('products.report')
        <dialog id="report-pdf-modal" class="modal">
            <div class="modal-box max-w-2xl">
                <h3 class="text-lg font-bold">{{ __('Warehouse Report') }}</h3>

                <form id="warehouse-pdf-export-form" action="{{ route('products.report') }}" method="GET" target="_blank">
                    @csrf
                    <input type="hidden" name="export" value="warehouse_pdf">
                    <x-input name="name" value="{{ request('name') }}" hidden />
                    <x-input name="group_name" value="{{ request('group_name') }}" hidden />
                    <x-input name="min_quantity" value="{{ request('min_quantity') }}" hidden />
                    <x-input name="need_order" value="1" hidden :disabled="! request()->boolean('need_order')" />
                    <x-input name="cols_submitted" value="1" hidden />

                    <div class="text-info mt-2">
                        {{ __('The column product name is always reported.') }}
                    </div>

                    <div class="mt-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-sm font-semibold">{{ __('Optional columns') }}</span>
                            <div class="text-xs [&_.fieldset]:m-0 [&_.label]:p-0 [&_.checkbox]:checkbox-sm">
                                <x-checkbox name="" id="report-cols-toggle" :title="__('Select All')" :checked="true"
                                    onchange="document.querySelectorAll('#report-pdf-modal input[name=&quot;columns[]&quot;]').forEach(cb => cb.checked = this.checked)" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-1 sm:grid-cols-2">
                            @foreach ($reportColumns as $key => $label)
                                <div class="[&_.fieldset]:m-0 [&_.label]:p-0 [&_.checkbox]:checkbox-sm">
                                    <x-checkbox name="columns[]" :id="'report-column-'.$key" :value="$key" :title="$label" :checked="true"
                                        onchange="document.getElementById('report-cols-toggle').checked = document.querySelectorAll('#report-pdf-modal input[name=&quot;columns[]&quot;]:checked').length === document.querySelectorAll('#report-pdf-modal input[name=&quot;columns[]&quot;]').length" />
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="modal-action">
                        <button type="button" class="btn btn-ghost" onclick="document.getElementById('report-pdf-modal').close()">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('warehouse-pdf-delivery-modal').showModal()">{{ __('Receive Report') }}</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button aria-label="close"></button>
            </form>
        </dialog>
        <dialog id="warehouse-pdf-delivery-modal" class="modal">
            <div class="modal-box max-w-md">
                <h3 class="text-lg font-bold">{{ __('Receive Report') }}</h3>
                <label class="fieldset w-full mt-4">
                    <span class="label">{{ __('Recipient email') }}</span>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required maxlength="255" class="input input-bordered w-full" dir="ltr" autocomplete="email" form="warehouse-pdf-export-form">
                </label>
                <p class="mt-2 text-sm text-base-content/70">{{ __('You can replace your email address to send this report to someone else. The email will identify you as the requester.') }}</p>
                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('warehouse-pdf-delivery-modal').close()">{{ __('Cancel') }}</button>
                    <button type="submit" form="warehouse-pdf-export-form" name="delivery" value="download" formaction="{{ route('report') }}" formmethod="POST" formtarget="_self" formnovalidate class="btn btn-primary">{{ __('Download') }}</button>
                    <button type="submit" form="warehouse-pdf-export-form" name="delivery" value="email" formaction="{{ route('report') }}" formmethod="POST" formtarget="_self" class="btn btn-secondary">{{ __('Send to email') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button aria-label="{{ __('Close') }}"></button></form>
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

                <form action="{{ route('products.index') }}" method="GET" class="flex flex-wrap items-center gap-2">
                    <div class="relative w-50 max-w-full [&_.input]:input-sm">
                        <x-input type="text" name="name" value="{{ request('name') }}" placeholder="{{ __('Product Name') }}" />
                    </div>

                    <div class="relative w-20 max-w-full [&_.input]:input-sm">
                        <x-input type="text" name="code" value="{{ request('code') }}" placeholder="{{ __('Product code') }}" />
                    </div>

                    <div class="relative w-30 max-w-full [&_.input]:input-sm">
                        <x-input type="text" name="group_name" value="{{ request('group_name') }}" placeholder="{{ __('Product Group Name') }}" />
                    </div>

                    <div class="relative w-30 max-w-full [&_.input]:input-sm">
                        <x-input type="number" name="min_quantity" value="{{ request('min_quantity') }}" placeholder="{{ __('Min quantity') }}" />
                    </div>

                    <div class="[&_.fieldset]:m-0 [&_.label]:text-sm">
                        <x-checkbox name="need_order" id="need_order" :title="__('Need Order')" value="1" :checked="request()->boolean('need_order')" />
                    </div>

                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Search') }}</button>   
                </form>
            </div>

            <div class="p-4 sm:p-5">
                <table class="table w-full overflow-auto">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">{{ __('Product Code') }}</th>
                            <th class="px-4 py-2">{{ __('Name') }}</th>
                            <th class="px-4 py-2">{{ __('Stock') }}</th>
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
                            @php($needOrderTextClass = $product->needs_order ? 'text-red-500' : '')
                            <tr @class([$needOrderTextClass => $needOrderTextClass !== ''])>
                                <td class="px-4 py-2">{{ localizeNumber($product->code) }}</td>
                                <td class="px-4 py-2">
                                    @if ($product->needs_order)
                                        <span class="tooltip" data-tip="{{ __('Need Order') }}">
                                            <a href="{{ route('products.show', $product) }}" class="{{ $needOrderTextClass }}">{{ $product->name }}</a>
                                        </span>
                                    @else
                                        <a href="{{ route('products.show', $product) }}" class="text-primary">{{ $product->name }}</a>
                                    @endif
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
