<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MonthlyAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::withoutGlobalScopes()->where('company_id', 1)->get();
        if ($employees->isEmpty()) {
            return;
        }

        foreach ($employees as $employee) {
            $logs = AttendanceLog::withoutGlobalScopes()->where('company_id', 1)->where('employee_id', $employee->id)->orderBy('log_date')->get();
            if ($logs->isEmpty()) {
                continue;
            }

            $grouped = $logs->groupBy(function (AttendanceLog $log) {
                $date = $log->log_date instanceof Carbon ? $log->log_date : Carbon::parse($log->log_date);
                [$jy, $jm] = gregorian_to_jalali($date->year, $date->month, $date->day);

                return $jy.'-'.str_pad((string) $jm, 2, '0', STR_PAD_LEFT);
            });

            foreach ($grouped as $key => $monthLogs) {
                [$jYear, $jMonth] = array_map('intval', explode('-', $key));

                [$gy, $gm, $gd] = jalali_to_gregorian($jYear, $jMonth, 1);
                $startDate = Carbon::createFromDate($gy, $gm, $gd)->startOfDay();

                $nextJMonth = $jMonth === 12 ? 1 : $jMonth + 1;
                $nextJYear = $jMonth === 12 ? $jYear + 1 : $jYear;
                [$gy2, $gm2, $gd2] = jalali_to_gregorian($nextJYear, $nextJMonth, 1);
                $monthEndDate = Carbon::createFromDate($gy2, $gm2, $gd2)->subDay()->startOfDay();

                $duration = (int) $startDate->diffInDays($monthEndDate) + 1;

                $today = Carbon::today();
                $effectiveEnd = $monthEndDate->lt($today) ? $monthEndDate : $today;
                $effectiveDays = (int) $startDate->diffInDays($effectiveEnd) + 1;

                $logsByDate = $monthLogs->keyBy(
                    fn (AttendanceLog $log) => $log->log_date instanceof Carbon ? $log->log_date->toDateString() : (string) $log->log_date
                );

                $workDays = $effectiveDays;
                $presentDays = 0;
                $absentDays = 0;
                $overtimeMin = 0;
                $autoOvertimeMin = 0;
                $undertimeMin = 0;
                $missionMin = 0;
                $paidLeaveMin = 0;
                $unpaidLeaveMin = 0;
                $remoteWorkMin = 0;
                $fridayMin = 0;

                for ($i = 0; $i < $effectiveDays; $i++) {
                    $day = $startDate->copy()->addDays($i);
                    $dateStr = $day->toDateString();
                    $isFriday = $day->dayOfWeek === Carbon::FRIDAY;

                    if (! isset($logsByDate[$dateStr])) {
                        if (! $isFriday) {
                            $absentDays++;
                        }

                        continue;
                    }

                    $log = $logsByDate[$dateStr];
                    $presentDays++;
                    $missionMin += (int) $log->mission;
                    $remoteWorkMin += (int) $log->remote_work;

                    if ($isFriday) {
                        $fridayMin += (int) $log->worked + (int) $log->mission;

                        continue;
                    }

                    $overtimeMin += (int) $log->overtime;
                    $autoOvertimeMin += (int) $log->auto_overtime;
                    $paidLeaveMin += (int) $log->paid_leave;
                    $unpaidLeaveMin += (int) $log->unpaid_leave;
                    $undertimeMin += (int) $log->early_leave + (int) $log->delay;
                }

                $attendance = MonthlyAttendance::withoutGlobalScopes()->updateOrCreate(
                    [
                        'company_id' => 1,
                        'employee_id' => $employee->id,
                        'year' => $jYear,
                        'month' => $jMonth,
                    ],
                    [
                        'start_date' => $startDate->toDateString(),
                        'duration' => $duration,
                        'work_days' => $workDays,
                        'present_days' => $presentDays,
                        'absent_days' => $absentDays,
                        'overtime' => $overtimeMin,
                        'auto_overtime' => $autoOvertimeMin,
                        'undertime' => $undertimeMin,
                        'mission' => $missionMin,
                        'paid_leave' => $paidLeaveMin,
                        'unpaid_leave' => $unpaidLeaveMin,
                        'remote_work' => $remoteWorkMin,
                        'friday' => $fridayMin,
                        'holiday' => 0,
                    ]
                );

                AttendanceLog::withoutGlobalScopes()->where('company_id', 1)->where('employee_id', $employee->id)
                    ->whereBetween('log_date', [$startDate->toDateString(), $effectiveEnd->toDateString()])
                    ->update(['monthly_attendance_id' => $attendance->id]);
            }
        }
    }
}
