<?php

namespace Database\Factories;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceLogFactory extends Factory
{
    protected $model = AttendanceLog::class;

    public function definition(): array
    {
        $entry = $this->faker->time('H:i');

        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'monthly_attendance_id' => null,
            'log_date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'entry_time' => $entry,
            'exit_time' => $this->faker->optional(0.8)->time('H:i'),
            'is_manual' => false,
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
