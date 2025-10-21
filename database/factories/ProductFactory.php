<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'code' => $this->faker->unique()->numerify('#####'),
            'company_id' => session('active-company-id'),
            'name' => $this->faker->name,
            'group' => 1,
            'location' => $this->faker->streetAddress,
            'quantity' => $this->faker->randomDigitNotNull,
            'quantity_warning' => $this->faker->randomDigitNotNull,
            'oversell' => $this->faker->boolean,
            'purchace_price' => $this->faker->randomFloat(2, 0, 1000),
            'selling_price' => $this->faker->randomFloat(2, 0, 1000),
            'discount_formula' => $this->faker->word,
            'description' => $this->faker->persianSentence(),
        ];
    }
}
