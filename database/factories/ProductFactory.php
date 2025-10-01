<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'code' => $this->faker->unique()->bothify('PRD-###'),
            'name' => $this->faker->name,
            'sstid' => $this->faker->numberBetween(1000, 9999),
            'group' => ProductGroup::factory(),
            'location' => $this->faker->city,
            'quantity' => $this->faker->numberBetween(1, 100),
            'quantity_warning' => $this->faker->numberBetween(1, 10),
            'oversell' => $this->faker->boolean,
            'purchace_price' => $this->faker->randomFloat(2, 10, 100),
            'selling_price' => $this->faker->randomFloat(2, 20, 200),
            'discount_formula' => null,
            'description' => $this->faker->sentence,
            'company_id' => 1,
            'subject_id' => Subject::factory()->create()->id,
            'vat' => $this->faker->randomFloat(2, 0, 20),
        ];
    }
}
