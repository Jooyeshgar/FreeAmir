<?php

namespace Database\Seeders;

use App\Services\ProductService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $productService = app(ProductService::class);
        $productCount = 50;

        $faker = Faker::create();
        session(['active-company-id' => 1]);

        for ($i = 0; $i < $productCount; $i++) {
            $productService->create(
                [
                    'code' => $faker->unique()->numerify('###'),
                    'company_id' => 1,
                    'name' => $faker->name,
                    'group' => 1,
                    'location' => $faker->streetAddress,
                    'quantity' => $faker->numberBetween(50, 1000),
                    'quantity_warning' => $faker->randomDigitNotNull,
                    'oversell' => $faker->boolean,
                    'purchace_price' => $faker->randomFloat(2, 0, 1000),
                    'selling_price' => $faker->randomFloat(2, 0, 1000),
                    'description' => $faker->sentence,
                ]);
        }
    }
}
