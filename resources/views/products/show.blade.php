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
                        {{ isset($product->quantity_warning) ? formatNumber($product->quantity_warning) : '' }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Oversell') }}</div>
                    <div class="font-semibold">{{ isset($product->oversell) ? formatNumber($product->oversell) : '' }}
                    </div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Purchace Price') }}</div>
                    <div class="font-semibold">
                        {{ isset($product->purchace_price) ? formatNumber($product->purchace_price) : 0 }}</div>
                </div>
                <div>
                    <div class="text-gray-500">{{ __('Selling Price') }}</div>
                    <div class="font-semibold">
                        {{ isset($product->selling_price) ? formatNumber($product->selling_price) : 0 }}</div>
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

            @if ($invoice_items->where('is_sell', true)->isNotEmpty())
                <h5 class="mt-6 font-semibold text-gray-700">{{ __('Selling List') }}</h5>
                <table class="table w-full mt-2 overflow-auto">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">{{ __('Quantity') }}</th>
                            <th class="px-4 py-2">{{ __('Unit Price') }}</th>
                            <th class="px-4 py-2">{{ __('Unit Discount') }}</th>
                            <th class="px-4 py-2">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice_items->where('is_sell', true) as $item)
                            <tr>
                                <td class="px-4 py-2">{{ $item->quantity }}</td>
                                <td class="px-4 py-2">{{ $item->unit_price }}</td>
                                <td class="px-4 py-2">{{ $item->unit_discount }}</td>
                                <td class="px-4 py-2">{{ $item->amount }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if ($invoice_items->where('is_sell', false)->isNotEmpty())
                <h5 class="mt-6 font-semibold text-gray-700">{{ __('Buying List') }}</h5>
                <table class="table w-full mt-2 overflow-auto">
                    <thead>
                        <tr>
                            <th class="px-4 py-2">{{ __('Quantity') }}</th>
                            <th class="px-4 py-2">{{ __('Unit Price') }}</th>
                            <th class="px-4 py-2">{{ __('Unit Discount') }}</th>
                            <th class="px-4 py-2">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoice_items->where('is_sell', false) as $item)
                            <tr>
                                <td class="px-4 py-2">{{ $item->quantity }}</td>
                                <td class="px-4 py-2">{{ $item->unit_price }}</td>
                                <td class="px-4 py-2">{{ $item->unit_discount }}</td>
                                <td class="px-4 py-2">{{ $item->amount }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="card-actions justify-end mt-6">
                <a href="{{ route('products.edit', $product) }}" class="btn btn-info">{{ __('Edit') }}</a>
                <a href="{{ route('products.index') }}" class="btn">{{ __('Back') }}</a>
            </div>
        </div>
    </div>
</x-app-layout>