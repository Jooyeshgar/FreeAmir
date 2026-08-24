<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Models\WarehouseTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseService
{
    public function create(array $data): Warehouse
    {
        return Warehouse::create($data);
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $warehouse->update($data);

        return $warehouse;
    }

    public function transfer(Product $product, Warehouse $from, Warehouse $to, float $quantity, ?string $description = null): WarehouseTransfer
    {
        if ((int) $product->company_id !== (int) $from->company_id || (int) $from->company_id !== (int) $to->company_id) {
            throw ValidationException::withMessages(['from_warehouse_id' => __('The product and warehouses must belong to the same company.')]);
        }

        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_warehouse_id' => __('The source and destination warehouses must be different.')]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => __('The quantity must be greater than zero.')]);
        }

        return DB::transaction(function () use ($product, $from, $to, $quantity, $description) {
            $stocks = $this->lockedStocks($product, $from, $to);
            $source = $stocks->get($from->id);
            $destination = $stocks->get($to->id);

            if ((float) $source->quantity < $quantity) {
                throw ValidationException::withMessages(['quantity' => __('The source warehouse does not have enough stock.')]);
            }

            $unitCost = (float) $source->average_cost;
            $source->decrement('quantity', $quantity);
            $destination->quantity = (float) $destination->quantity + $quantity;
            $destination->average_cost = $destination->quantity > 0
                ? (((float) $destination->quantity - $quantity) * (float) $destination->average_cost + $quantity * $unitCost) / (float) $destination->quantity
                : $unitCost;
            $destination->save();

            return WarehouseTransfer::create([
                'product_id' => $product->id,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'description' => $description,
                'transferred_by' => auth()->id(),
                'transferred_at' => now()->toDateString(),
            ]);
        });
    }

    private function lockedStocks(Product $product, Warehouse ...$warehouses)
    {
        $warehouseIds = collect($warehouses)->pluck('id')->sort()->values();

        foreach ($warehouseIds as $warehouseId) {
            WarehouseProductStock::firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                ['quantity' => 0, 'average_cost' => $product->average_cost ?? 0]
            );
        }

        return WarehouseProductStock::query()
            ->where('product_id', $product->id)
            ->whereIn('warehouse_id', $warehouseIds)
            ->orderBy('warehouse_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('warehouse_id');
    }
}
