<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceLogSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::withoutGlobalScopes()->where('company_id', 1)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $start = Carbon::now()->subMonthsNoOverflow(3)->startOfMonth();
        $end = Carbon::now()->endOfDay();

        foreach ($employees as $employee) {
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $this->createLog($employee, $cursor->copy());
                $cursor->addDay();
            }
        }
    }

    private function createLog(Employee $employee, Carbon $date): void
    {
        $isFriday = (int) $date->format('N') === 5;

        $worked = $isFriday ? 0 : 480;
        $entry = $isFriday ? null : '08:00:00';
        $exit = $isFriday ? null : '16:00:00';

        $delay = (! $isFriday && random_int(1, 5) === 1) ? random_int(5, 30) : 0;
        $overtime = (! $isFriday && random_int(1, 4) === 1) ? random_int(30, 120) : 0;
        $paidLeave = (! $isFriday && random_int(1, 30) === 1) ? 480 : 0;

        if ($paidLeave > 0) {
            $worked = 0;
            $entry = null;
            $exit = null;
            $delay = 0;
            $overtime = 0;
        }

        AttendanceLog::withoutGlobalScopes()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'log_date' => $date->toDateString(),
            ],
            [
                'company_id' => 1,
                'monthly_attendance_id' => null,
                'entry_time' => $entry,
                'exit_time' => $exit,
                'worked' => $worked,
                'remote_work' => 0,
                'delay' => $delay,
                'early_leave' => 0,
                'overtime' => $overtime,
                'auto_overtime' => 0,
                'mission' => 0,
                'paid_leave' => $paidLeave,
                'unpaid_leave' => 0,
                'is_friday' => $isFriday,
                'is_holiday' => false,
                'is_manual' => false,
                'description' => null,
            ]
        );
    }
}
