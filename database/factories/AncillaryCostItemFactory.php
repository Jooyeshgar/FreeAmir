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
        return [
            'ancillary_cost_id' => AncillaryCost::inRandomOrder()->first()->id,
            'product_id' => Product::inRandomOrder()->first()->id,
            'type' => $this->faker->randomElement(AncillaryCostType::cases()),
            'amount' => $this->faker->randomFloat(2, 100, 1000),
        ];
    }
}
