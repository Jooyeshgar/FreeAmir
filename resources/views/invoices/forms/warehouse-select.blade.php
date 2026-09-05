@php
    $selectedWarehouseId = old('warehouse_id', $invoice->warehouse_id ?? $warehouses->first()?->id);
@endphp

<div class="flex w-1/6 flex-wrap">
    <label class="text-gray-500 w-full" for="warehouse_id">{{ __('Warehouse') }}</label>
    <select name="warehouse_id" id="warehouse_id" class="select select-bordered w-full" required>
        <option value="">{{ __('Select Warehouse') }}</option>
        @foreach ($warehouses as $warehouse)
            <option value="{{ $warehouse->id }}" @selected((int) $selectedWarehouseId === (int) $warehouse->id)>{{ $warehouse->name }}</option>
        @endforeach
    </select>
</div>
