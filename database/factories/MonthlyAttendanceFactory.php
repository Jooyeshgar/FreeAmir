<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonthlyAttendanceFactory extends Factory
{
    protected $model = MonthlyAttendance::class;

    public function definition(): array
    {
        $workDays = $this->faker->numberBetween(20, 26);
        $presentDays = $this->faker->numberBetween(0, $workDays);
        $absentDays = $workDays - $presentDays;

        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'year' => $this->faker->numberBetween(1401, 1403),
            'month' => $this->faker->numberBetween(1, 12),
            'work_days' => $workDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'overtime' => $this->faker->numberBetween(0, 600),
            'mission_days' => $this->faker->numberBetween(0, 3),
            'paid_leave_days' => $this->faker->numberBetween(0, 3),
            'unpaid_leave_days' => $this->faker->numberBetween(0, 2),
            'friday' => $this->faker->numberBetween(0, 480),
            'holiday' => $this->faker->numberBetween(0, 480),
        ];
    }
}
