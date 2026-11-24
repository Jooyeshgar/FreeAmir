<?php

namespace Database\Seeders;

use App\Enums\PersonnelRequestStatus;
use App\Enums\PersonnelRequestType;
use App\Models\Employee;
use App\Models\PersonnelRequest;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PersonnelRequestSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(AttendanceService::class);
        $fiscalYearId = (int) getActiveFiscalYear();
        $employees = Employee::withoutGlobalScopes()->with('workShift')->where('fiscal_year_id', $fiscalYearId)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $approver = User::withoutGlobalScopes()->whereHas('roles', fn ($q) => $q->where('name', 'Super-Admin'))->first();
        $approverId = $approver?->id;

        $template = [
            [
                'days_ago' => 45,
                'type' => PersonnelRequestType::LEAVE_DAILY,
                'status' => PersonnelRequestStatus::APPROVED,
                'daily' => true,
            ],
            [
                'days_ago' => 30,
                'type' => PersonnelRequestType::SICK_LEAVE,
                'status' => PersonnelRequestStatus::APPROVED,
                'daily' => true,
            ],
            [
                'days_ago' => 14,
                'type' => PersonnelRequestType::LEAVE_WITHOUT_PAY,
                'status' => PersonnelRequestStatus::REJECTED,
                'daily' => true,
            ],
            [
                'days_ago' => 7,
                'type' => PersonnelRequestType::LEAVE_HOURLY,
                'status' => PersonnelRequestStatus::PENDING,
                'daily' => false,
                'start_time' => '10:00',
                'end_time' => '12:00',
            ],
            [
                'days_ago' => 2,
                'type' => PersonnelRequestType::MISSION_DAILY,
                'status' => PersonnelRequestStatus::PENDING,
                'daily' => true,
            ],
        ];

        foreach ($employees as $employee) {
            foreach ($template as $i => $row) {
                $date = Carbon::now()->subDays($row['days_ago']);

                // Daily requests are single-day and follow the employee's shift, as when created through the UI.
                if ($row['daily']) {
                    $shift = $service->shiftTimesForDate($employee->workShift, $date);
                    $row['start_time'] = $shift['start'];
                    $row['end_time'] = $shift['end'];
                }

                [$startHour, $startMin] = array_map('intval', explode(':', $row['start_time']));
                [$endHour, $endMin] = array_map('intval', explode(':', $row['end_time']));

                $start = (clone $date)->setTime($startHour, $startMin, 0);
                $end = (clone $date)->setTime($endHour, $endMin, 0);

                $request = PersonnelRequest::withoutGlobalScopes()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'start_date' => $start,
                        'request_type' => $row['type']->value,
                    ],
                    [
                        'fiscal_year_id' => $employee->fiscal_year_id,
                        'end_date' => $end,
                        'reason' => __('Demo request').' #'.($i + 1),
                        'status' => $row['status'],
                        'approved_by' => $row['status']->isPending() ? null : $approverId,
                        'payroll_id' => null,
                    ]
                );

                if ($row['status']->isApproved()) {
                    $request->load('employee.workShift');
                    $service->syncPersonnelRequestLogs($request);
                }
            }
        }
    }
}
