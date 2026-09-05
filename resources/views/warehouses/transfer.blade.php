<x-app-layout :title="__('Transfer Product')">
    <div class="card bg-base-100 shadow" x-data="{ selectedValue: '', productId: '{{ old('product_id', $selectedProduct?->id) }}' }">
        <form method="POST" action="{{ route('warehouses.transfer.store') }}">@csrf<div class="card-body">
                <h1 class="card-title">{{ __('Transfer Product') }}</h1>
                <x-show-message-bags />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">{{ __('Product') }}</label>
                        @php
                            $productValue = $selectedProduct ? 'product-' . $selectedProduct->id : '';
                            $productLocked = $selectedProduct !== null;
                        @endphp
                        <x-select-box url="{{ route('warehouses.search-products') }}"
                            :options="[['headerGroup' => 'product', 'options' => $products]]"
                            x-model="selectedValue" x-init="selectedValue = '{{ $productValue }}'"
                            x-on:selected="productId = $event.detail.id" placeholder="{{ __('Select Product') }}" :disabled="$productLocked" />
                        <input type="hidden" name="product_id" x-bind:value="productId">
                    </div>
                    <div>
                        <x-input name="quantity" title="{{ __('Quantity') }}" />
                    </div>
                    <div>
                        <label class="label">{{ __('From warehouse') }}</label>
                        <select class="select select-bordered w-full" name="from_warehouse_id" @disabled($selectedWarehouse)>
                            <option value="">{{ __('Source Warehouse') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) old('from_warehouse_id', $selectedWarehouse?->id) === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @if ($selectedWarehouse)
                            <input type="hidden" name="from_warehouse_id" value="{{ $selectedWarehouse->id }}">
                        @endif
                    </div>
                    <div>
                        <label class="label">{{ __('To warehouse') }}</label>
                        <select class="select select-bordered w-full" name="to_warehouse_id">
                            <option value="">{{ __('Target Warehouse') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <x-textarea name="description" title="{{ __('Description') }}" />
                    </div>
                </div>
                <div class="card-actions justify-end">
                    <a href="{{ route('warehouses.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
                    <button class="btn btn-primary">{{ __('Transfer') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
