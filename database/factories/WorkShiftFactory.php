<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkShiftFactory extends Factory
{
    protected $model = WorkShift::class;

    public function definition(): array
    {
        $start = $this->faker->time('H:i');
        $end = $this->faker->time('H:i');

        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->words(2, true),
            'start_time' => $start,
            'end_time' => $end,
            'crosses_midnight' => false,
            'float_before' => $this->faker->numberBetween(0, 30),
            'float_after' => $this->faker->numberBetween(0, 30),
            'break' => $this->faker->numberBetween(0, 60),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function crossesMidnight(): static
    {
        return $this->state(['crosses_midnight' => true]);
    }
}
