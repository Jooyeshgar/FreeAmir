<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::inRandomOrder()->first()->id,
            'parent_id' => Subject::inRandomOrder()->first()->id,
            'code' => $this->faker->unique()->ean8(),
            'name' => $this->faker->name,
            'type' => $this->faker->numberBetween(0, 2),
        ];
    }
}
