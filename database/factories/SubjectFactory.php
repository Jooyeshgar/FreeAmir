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
            'company_id' => $this->faker->randomElement(Company::all()->toArray()),
            'parent_id' => $this->faker->randomElement(Subject::all()->toArray()),
            'code' => $this->faker->unique()->ean8(),
            'name' => $this->faker->name,
            'type' => $this->faker->numberBetween(0, 2),
        ];
    }
}
