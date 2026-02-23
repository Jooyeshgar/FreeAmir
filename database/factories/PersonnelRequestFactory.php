<?php

namespace Database\Factories;

use App\Enums\PersonnelRequestType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PersonnelRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonnelRequestFactory extends Factory
{
    protected $model = PersonnelRequest::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', 'now');
        $endDate = $this->faker->dateTimeBetween($startDate, '+7 days');

        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'request_type' => $this->faker->randomElement(PersonnelRequestType::cases())->value,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration_minutes' => $this->faker->numberBetween(0, 480),
            'reason' => $this->faker->optional()->sentence(),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'approved_by' => null,
            'payroll_id' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(['status' => 'rejected']);
    }
}
