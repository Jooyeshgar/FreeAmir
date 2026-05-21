<?php

namespace Database\Seeders;

use App\Enums\PersonnelRequestType;
use App\Models\Employee;
use App\Models\PersonnelRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PersonnelRequestDemoSeeder extends Seeder
{
    /**
     * Seed a handful of personnel requests (mostly leave-related) per employee,
     * spread across the last two months with a mix of pending / approved /
     * rejected statuses so the demo shows the approval workflow end-to-end.
     */
    public function run(): void
    {
        $employees = Employee::withoutGlobalScopes()->where('company_id', 1)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $approver = User::withoutGlobalScopes()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Super-Admin'))
            ->first();
        $approverId = $approver?->id;

        // Each row defines: how far in the past, duration, type, status.
        $template = [
            ['days_ago' => 45, 'duration_days' => 2, 'type' => PersonnelRequestType::LEAVE_DAILY, 'status' => 'approved'],
            ['days_ago' => 30, 'duration_days' => 1, 'type' => PersonnelRequestType::SICK_LEAVE, 'status' => 'approved'],
            ['days_ago' => 14, 'duration_days' => 3, 'type' => PersonnelRequestType::LEAVE_WITHOUT_PAY, 'status' => 'rejected'],
            ['days_ago' => 7,  'duration_days' => 1, 'type' => PersonnelRequestType::LEAVE_HOURLY, 'status' => 'pending'],
            ['days_ago' => 2,  'duration_days' => 2, 'type' => PersonnelRequestType::MISSION_DAILY, 'status' => 'pending'],
        ];

        foreach ($employees as $employee) {
            foreach ($template as $i => $row) {
                $start = Carbon::now()->subDays($row['days_ago'])->setTime(8, 0);
                $end = (clone $start)->addDays($row['duration_days'])->setTime(16, 0);

                PersonnelRequest::withoutGlobalScopes()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'start_date' => $start,
                        'request_type' => $row['type']->value,
                    ],
                    [
                        'company_id' => 1,
                        'end_date' => $end,
                        'reason' => __('Demo request').' #'.($i + 1),
                        'status' => $row['status'],
                        'approved_by' => $row['status'] !== 'pending' ? $approverId : null,
                        'payroll_id' => null,
                    ]
                );
            }
        }
    }
}
