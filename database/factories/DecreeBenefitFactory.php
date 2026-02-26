<?php

namespace Database\Factories;

use App\Models\DecreeBenefit;
use App\Models\PayrollElement;
use App\Models\SalaryDecree;
use Illuminate\Database\Eloquent\Factories\Factory;

class DecreeBenefitFactory extends Factory
{
    protected $model = DecreeBenefit::class;

    public function definition(): array
    {
        return [
            'decree_id' => SalaryDecree::factory(),
            'element_id' => PayrollElement::factory(),
            'element_value' => $this->faker->randomFloat(2, 100_000, 5_000_000),
        ];
    }
}
