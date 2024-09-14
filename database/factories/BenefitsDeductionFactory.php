<?php

namespace Database\Factories;

use App\Models\BenefitsDeduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BenefitsDeduction>
 */
class BenefitsDeductionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word,
            'type' => $this->faker->randomElement(['benefit', 'deduction']),
            'calculation' => $this->faker->randomElement(['fixed', 'hourly', 'manual']),
            'insurance_included' => $this->faker->boolean,
            'tax_included' => $this->faker->boolean,
            'show_on_payslip' => $this->faker->boolean,
            'amount' => $this->faker->numberBetween(100, 1000),
        ];
    }
}
