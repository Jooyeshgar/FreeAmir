<?php

namespace Database\Factories;

use App\Models\SalarySlip;
use App\Models\PayrollPattern;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalarySlip>
 */
class SalarySlipFactory extends Factory
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
            'daily_wage' => $this->faker->numberBetween(100, 500),
            'hourly_overtime' => $this->faker->numberBetween(10, 50),
            'holiday_work' => $this->faker->numberBetween(20, 100),
            'friday_work' => $this->faker->numberBetween(20, 100),
            'child_allowance' => $this->faker->numberBetween(50, 200),
            'housing_allowance' => $this->faker->numberBetween(100, 500),
            'food_allowance' => $this->faker->numberBetween(50, 200),
            'marriage_allowance' => $this->faker->numberBetween(200, 1000),
            'payroll_pattern_id' => PayrollPattern::factory(), // Use a factory to create related records
            'description' => $this->faker->sentence,
        ];
    }
}
