<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollPattern>
 */
class PayrollPatternFactory extends Factory
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
            'overtime_hourly' => $this->faker->numberBetween(10, 50),
            'holiday_work' => $this->faker->numberBetween(20, 100),
            'friday_work' => $this->faker->numberBetween(20, 100),
            'child_allowance' => $this->faker->numberBetween(50, 200),
            'housing_allowance' => $this->faker->numberBetween(100, 500),
            'grocery_allowance' => $this->faker->numberBetween(50, 200),
            'marriage_allowance' => $this->faker->numberBetween(200, 1000),
            'insurance_percentage' => $this->faker->randomFloat(2, 0, 100),
            'unemployment_insurance' => $this->faker->numberBetween(20, 100),
            'employer_share' => $this->faker->numberBetween(50, 200),
        ];
    }
}
