<x-app-layout>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div hidden>
                    <div class="text-gray-500">{{ __('Code') }}</div>
                    <div class="font-semibold">{{ $product->code }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Name') }}</div>
                    <div class="font-semibold">{{ $product->name }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Product Group') }}</div>
                    <div class="font-semibold">{{ $product->productgroup->name ?? '' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Location') }}</div>
                    <div class="font-semibold">{{ $product->location ?? '' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Quantity') }}</div>
                    <div class="font-semibold">{{ isset($product->quantity) ? formatNumber($product->quantity) : '' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Quantity warning') }}</div>
                    <div class="font-semibold">
                        {{ isset($product->quantity_warning) ? formatNumber($product->quantity_warning) : '' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Oversell') }}</div>
                    <div class="font-semibold">{{ isset($product->oversell) ? formatNumber($product->oversell) : '' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Purchace Price') }}</div>
                    <div class="font-semibold">
                        {{ isset($product->purchace_price) ? formatNumber($product->purchace_price) : 0 }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Selling Price') }}</div>
                    <div class="font-semibold">
                        {{ isset($product->selling_price) ? formatNumber($product->selling_price) : 0 }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Discount Formula') }}</div>
                    <div class="font-semibold">{{ $product->discount_formula ?? '' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('VAT') }}</div>
                    <div class="font-semibold">{{ isset($product->vat) ? formatNumber($product->vat) : '' }}</div>
                </div>
                @if (isset($product->description))
                    <div>
                        <div class="text-gray-500">{{ __('Description') }}</div>
                        <div class="font-semibold">{{ $product->description ?? '' }}</div>
                    </div>
                @endif
            </div>
            <table class="table table-fixed w-3/4 mt-3 overflow-auto">
                <thead>
                    <tr>
                        <th class="px-4 py-2 w-1/6">{{ __('Date') }}</th>
                        <th class="px-4 py-2 w-1/6">{{ __('Buy') }}</th>
                        <th class="px-4 py-2 w-1/6">{{ __('Sell') }}</th>
                        <th class="px-4 py-2 w-1/6">{{ __('Unit Price') }}</th>
                        <th class="px-4 py-2 w-1/6">{{ __('OFF') }}</th>
                        <th class="px-4 py-2 w-1/6">{{ __('Remaining') }}</th>
                    </tr>
                </thead>


                <tbody>
                    @php
                        $remaining = 100 - $product->quantity;

                        $totalSell = 0;
                        $totalBuy = 0;
                    @endphp

                    @foreach ($invoice_items as $item)
                        @php
                            if ($item->is_sell) {
                                $remaining -= $item->quantity;
                                $totalSell += $item->quantity;
                            } else {
                                $remaining += $item->quantity;
                                $totalBuy += $item->quantity;
                            }
                        @endphp

                        <tr>
                            <td class="px-4 py-2">
                                {{ gregorian_to_jalali_date($item->updated_at) }}
                            </td>
                            <td class="px-4 py-2">{{ $item->is_sell ? (int) $item->quantity : 0 }}</td>
                            <td class="px-4 py-2">{{ !$item->is_sell ? (int) $item->quantity : 0 }}</td>

                            <td class="px-4 py-2">{{ $item->unit_price }}</td>
                            <td class="px-4 py-2">{{ $item->unit_discount }}</td>

                            <td class="px-4 py-2 {{ $remaining < 0 ? 'text-red-600 font-bold bg-red-100 rounded' : '' }}">
                                {{ $remaining }}
                            </td>
                        </tr>
                    @endforeach

                    <tr class="font-bold bg-gray-100">
                        <td class="px-4 py-2">{{ __('Total') }}</td>
                        <td class="px-4 py-2">{{ $totalSell }}</td>
                        <td class="px-4 py-2">{{ $totalBuy }}</td>
                        <td class="px-4 py-2" colspan="2"></td>
                        <td class="px-4 py-2 {{ $remaining < 0 ? 'rounded bg-red-500 text-white' : '' }}">
                            مانده انبار: {{ $remaining }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="card-actions justify-end mt-6">
                <a href="{{ route('products.edit', $product) }}" class="btn btn-info">{{ __('Edit') }}</a>
                <a href="{{ route('products.index') }}" class="btn">{{ __('Back') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>