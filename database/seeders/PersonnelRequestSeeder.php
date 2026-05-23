<?php

namespace Database\Seeders;

use App\Enums\PersonnelRequestType;
use App\Models\Employee;
use App\Models\PersonnelRequest;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PersonnelRequestSeeder extends Seeder
{
    public function __construct(private readonly AttendanceService $service) {}

    public function run(): void
    {
        $employees = Employee::withoutGlobalScopes()->where('company_id', 1)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $approver = User::withoutGlobalScopes()->whereHas('roles', fn ($q) => $q->where('name', 'Super-Admin'))->first();
        $approverId = $approver?->id;

        $template = [
            [
                'days_ago' => 45,
                'type' => PersonnelRequestType::LEAVE_DAILY,
                'status' => 'approved',
                'days' => 2,
                'start_time' => '08:00',
                'end_time' => '16:00',
            ],
            [
                'days_ago' => 30,
                'type' => PersonnelRequestType::SICK_LEAVE,
                'status' => 'approved',
                'days' => 1,
                'start_time' => '08:00',
                'end_time' => '16:00',
            ],
            [
                'days_ago' => 14,
                'type' => PersonnelRequestType::LEAVE_WITHOUT_PAY,
                'status' => 'rejected',
                'days' => 3,
                'start_time' => '08:00',
                'end_time' => '16:00',
            ],
            [
                'days_ago' => 7,
                'type' => PersonnelRequestType::LEAVE_HOURLY,
                'status' => 'pending',
                'days' => null,
                'start_time' => '10:00',
                'end_time' => '12:00',
            ],
            [
                'days_ago' => 2,
                'type' => PersonnelRequestType::MISSION_DAILY,
                'status' => 'pending',
                'days' => 2,
                'start_time' => '08:00',
                'end_time' => '16:00',
            ],
        ];

        foreach ($employees as $employee) {
            foreach ($template as $i => $row) {
                [$startHour, $startMin] = array_map('intval', explode(':', $row['start_time']));
                [$endHour,   $endMin] = array_map('intval', explode(':', $row['end_time']));

                $start = Carbon::now()->subDays($row['days_ago'])->setTime($startHour, $startMin, 0);

                if ($row['days'] === null) {
                    $end = Carbon::now()->subDays($row['days_ago'])->setTime($endHour, $endMin, 0);
                } else {
                    $end = (clone $start)->addDays($row['days'] - 1)->setTime($endHour, $endMin, 0);
                }

                $request = PersonnelRequest::withoutGlobalScopes()->updateOrCreate(
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

                if ($row['status'] === 'approved') {
                    $request->load('employee.workShift');
                    $this->service->syncPersonnelRequestLogs($request);
                }
            }
        }
    }
}
