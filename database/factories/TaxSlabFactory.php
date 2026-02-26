<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\TaxSlab;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxSlabFactory extends Factory
{
    protected $model = TaxSlab::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'year' => $this->faker->numberBetween(1400, 1404),
            'slab_order' => $this->faker->unique()->numberBetween(1, 10),
            'income_from' => 0,
            'income_to' => $this->faker->numberBetween(100_000_000, 500_000_000),
            'tax_rate' => $this->faker->randomElement([10, 15, 20, 25]),
            'annual_exemption' => $this->faker->optional()->numberBetween(10_000_000, 50_000_000),
        ];
    }
}
