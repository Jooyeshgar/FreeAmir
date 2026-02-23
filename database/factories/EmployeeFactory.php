<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('EMP-####')),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'father_name' => $this->faker->optional()->firstName('male'),
            'nationality' => 'iranian',
            'gender' => $this->faker->randomElement(['male', 'female']),
            'work_site_id' => WorkSite::factory(),
            'is_active' => true,
        ];
    }
}
