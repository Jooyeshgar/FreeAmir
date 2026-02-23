<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OrgChart;
use App\Models\SalaryDecree;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalaryDecreeFactory extends Factory
{
    protected $model = SalaryDecree::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'org_chart_id' => OrgChart::factory(),
            'name' => $this->faker->optional()->bothify('Decree-####'),
            'start_date' => $startDate,
            'end_date' => $this->faker->optional(0.4)->dateTimeBetween($startDate, '+1 year'),
            'contract_type' => $this->faker->randomElement(['full_time', 'part_time', 'hourly', 'shift']),
            'daily_wage' => $this->faker->optional()->randomFloat(2, 100_000, 2_000_000),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
