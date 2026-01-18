<?php

namespace Database\Factories;

use App\Enums\AncillaryCostType;
use App\Models\AncillaryCost;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class AncillaryCostItemFactory extends Factory
{
    public function definition(): array
    {
        $ancillaryCost = AncillaryCost::withoutGlobalScopes()->inRandomOrder()->first();
        $product = Product::withoutGlobalScopes()->inRandomOrder()->first();

        return [
            'ancillary_cost_id' => $ancillaryCost->id,
            'product_id' => $product->id,
            'type' => $this->faker->randomElement(AncillaryCostType::cases()),
            'amount' => $this->faker->randomFloat(0, 100000, 1000000),
        ];
    }
}
