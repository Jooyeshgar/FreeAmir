<x-app-layout :title="__('Transfer Product')">
    <div class="card bg-base-100 shadow">
        <form method="POST" action="{{ route('warehouses.transfer.store') }}">@csrf<div class="card-body">
                <h1 class="card-title">{{ __('Transfer Product') }}</h1>
                <x-show-message-bags />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label">{{ __('Product') }}</label>
                        <select class="select select-bordered w-full" name="product_id">
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input name="quantity" title="{{ __('Quantity') }}" />
                    </div>
                    <div>
                        <label class="label">{{ __('From warehouse') }}</label>
                        <select class="select select-bordered w-full" name="from_warehouse_id">
                            <option value="">{{ __('Source Warehouse') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
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
