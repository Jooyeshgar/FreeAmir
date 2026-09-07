<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\WarehouseService;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $groups = ProductGroup::withoutGlobalScopes()->where('company_id', getActiveCompany())->get();
        $warehouses = Warehouse::withoutGlobalScopes()->where('company_id', getActiveCompany())->where('code', '!=', 'DAMAGED')->get();
        $damagedWarehouse = Warehouse::withoutGlobalScopes()->where('company_id', getActiveCompany())->where('code', 'DAMAGED')->first();
        $transferredToDamaged = false;

        foreach ($groups as $group) {
            Product::factory()->count(10)->withGroup($group)->withSubjects()->create()->each(
                function (Product $product) use ($warehouses, $damagedWarehouse, &$transferredToDamaged): void {
                    $totalQuantity = 0;
                    foreach ($warehouses as $warehouse) {
                        $quantity = 1_000;
                        WarehouseProductStock::updateOrCreate(
                            ['warehouse_id' => $warehouse->id, 'product_id' => $product->id],
                            [
                                'quantity' => $quantity,
                                'average_cost' => $product->average_cost ?? 0,
                            ],
                        );
                        $totalQuantity += $quantity;
                    }

                    $product->updateQuietly(['quantity' => $totalQuantity]);

                    if ($damagedWarehouse && (! $transferredToDamaged || fake()->boolean(35))) {
                        $sourceWarehouse = $warehouses->random();
                        app(WarehouseService::class)->transfer($product, $sourceWarehouse, $damagedWarehouse, fake()->numberBetween(1, 20), 'خراب شده');
                        $transferredToDamaged = true;
                    }
                },
            );
        }
    }
}
