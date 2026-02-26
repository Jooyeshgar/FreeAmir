<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PublicHoliday;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublicHolidayFactory extends Factory
{
    protected $model = PublicHoliday::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'date' => $this->faker->unique()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'),
            'name' => $this->faker->words(3, true),
        ];
    }
}
