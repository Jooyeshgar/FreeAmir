<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use App\Models\TaxSlab;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxSlabFactory extends Factory
{
    protected $model = TaxSlab::class;

    public function definition(): array
    {
        return [
            'fiscal_year_id' => FiscalYear::factory(),
            'income_to' => $this->faker->numberBetween(100_000_000, 500_000_000),
            'tax_rate' => $this->faker->randomElement([10, 15, 20, 25]),
        ];
    }
}
