<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'code' => 'P-'.$this->faker->unique()->numerify('#####'),
            'name' => $this->faker->words(3, true),
            'sstid' => $this->faker->optional()->word,
            'group' => null,
            'location' => $this->faker->optional()->word,
            'quantity' => $this->faker->numberBetween(0, 1000),
            'quantity_warning' => $this->faker->numberBetween(0, 20),
            'oversell' => 0,
            'selling_price' => $this->faker->randomFloat(2, 0, 10000),
            'discount_formula' => null,
            'description' => $this->faker->sentence,
            'company_id' => Company::factory(),
            'vat' => 0,
            'average_cost' => 0,
        ];
    }

    /**
     * Attach one or more websites after creating the product.
     * Usage: Product::factory()->withWebsites(2)->create();
     */
    public function withWebsites(int $count = 1)
    {
        return $this->afterCreating(function (Product $product) use ($count) {
            \App\Models\ProductWebsite::factory()->count($count)->create(['product_id' => $product->id]);
        });
    }
}
