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
            'worked' => 0,
            'delay' => 0,
            'early_leave' => 0,
            'overtime' => 0,
            'mission' => 0,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
            'is_friday' => false,
            'is_holiday' => false,
            'is_manual' => false,
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
