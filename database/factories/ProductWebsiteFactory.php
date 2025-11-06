<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductWebsite;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductWebsiteFactory extends Factory
{
    protected $model = ProductWebsite::class;

    public function definition(): array
    {
        return [
            'link' => 'https://'.$this->faker->domainWord.'.'.$this->faker->randomElement(['com', 'org', 'net', 'co.uk']),
            'product_id' => $this->faker->randomElement(Product::all()->toArray()),
        ];
    }
}
