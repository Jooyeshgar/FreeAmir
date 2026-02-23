<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\MonthlyAttendance;
use App\Models\PublicHoliday;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * AttendanceService
 *
 * Calculates monthly attendance totals from daily attendance logs
 * using a fixed working shift (08:00–17:00, 8 working hours per day).
 */
class AttendanceService
{
    /** Standard shift start (HH:MM) */
    public const SHIFT_START = '08:00';

    /** Standard shift end (HH:MM) */
    public const SHIFT_END = '17:00';

    /** Working minutes per standard day (8 hours) */
    public const WORK_MINUTES_PER_DAY = 480;

    /**
     * Calculate attendance totals for a given employee over a date range
     * and persist a MonthlyAttendance record (or update an existing one).
     *
     * @param  Carbon  $startDate  First day of the period (Gregorian)
     * @param  int  $durationDays  Number of calendar days in the month (28–31)
     */
    public function calculateAndStore(
        int $employeeId,
        int $companyId,
        Carbon $startDate,
        int $durationDays,
        int $jalaliYear,
        int $jalaliMonth
    ): MonthlyAttendance {
        $endDate = $startDate->copy()->addDays($durationDays - 1);

        // Load logs for this period
        $logs = AttendanceLog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Load public holidays for this period
        $holidays = PublicHoliday::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->toArray();

        $totals = $this->computeTotals($startDate, $durationDays, $logs, $holidays);

        /** @var MonthlyAttendance $attendance */
        $attendance = MonthlyAttendance::withoutGlobalScopes()
            ->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'year' => $jalaliYear,
                    'month' => $jalaliMonth,
                ],
                array_merge($totals, [
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'year' => $jalaliYear,
                    'month' => $jalaliMonth,
                ])
            );

        // Link logs to this monthly_attendance record
        AttendanceLog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->update(['monthly_attendance_id' => $attendance->id]);

        return $attendance;
    }

    /**
     * Compute aggregated totals from logs and calendar info.
     *
     * @param  array  $holidayDates  Gregorian date strings
     */
    public function computeTotals(
        Carbon $startDate,
        int $durationDays,
        Collection $logs,
        array $holidayDates
    ): array {
        $logsByDate = $logs->keyBy(fn ($l) => $l->log_date instanceof Carbon
            ? $l->log_date->toDateString()
            : (string) $l->log_date
        );

        $workDays = 0;
        $presentDays = 0;
        $absentDays = 0;
        $overtimeMin = 0;
        $missionDays = 0;
        $paidLeaveDays = 0;
        $unpaidLeaveDays = 0;
        $fridayMin = 0;
        $holidayMin = 0;

        for ($i = 0; $i < $durationDays; $i++) {
            $day = $startDate->copy()->addDays($i);
            $dateStr = $day->toDateString();
            $isFriday = $day->dayOfWeek === Carbon::FRIDAY;
            $isHoliday = in_array($dateStr, $holidayDates, true);

            // Fridays and public holidays do not count as regular work days
            if ($isFriday || $isHoliday) {
                // If the employee still worked on that day, count overtime minutes
                if (isset($logsByDate[$dateStr])) {
                    $workedMin = $this->workedMinutes($logsByDate[$dateStr]);
                    if ($isFriday) {
                        $fridayMin += $workedMin;
                    } else {
                        $holidayMin += $workedMin;
                    }
                }

                continue;
            }

            $workDays++;

            if (! isset($logsByDate[$dateStr])) {
                $absentDays++;

                continue;
            }

            $log = $logsByDate[$dateStr];
            $workedMin = $this->workedMinutes($log);

            // Mission day: no entry/exit but still "present"
            if ($log->mission > 0 || ($log->entry_time === null && $log->mission > 0)) {
                $missionDays++;
                $presentDays++;

                continue;
            }

            // Paid / unpaid leave
            if ($log->paid_leave > 0) {
                $paidLeaveDays++;
                $presentDays++;

                continue;
            }
            if ($log->unpaid_leave > 0) {
                $unpaidLeaveDays++;

                continue;
            }

            if ($workedMin > 0) {
                $presentDays++;

                // Overtime: minutes worked beyond standard shift
                $extra = $workedMin - self::WORK_MINUTES_PER_DAY;
                if ($extra > 0) {
                    $overtimeMin += $extra;
                }
            } else {
                $absentDays++;
            }
        }

        return [
            'work_days' => $workDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'overtime' => $overtimeMin,
            'mission_days' => $missionDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'friday' => $fridayMin,
            'holiday' => $holidayMin,
        ];
    }

    /**
     * Calculate actual worked minutes from an AttendanceLog entry.
     */
    private function workedMinutes(AttendanceLog $log): int
    {
        if ($log->worked > 0) {
            return (int) $log->worked;
        }

        if ($log->entry_time === null || $log->exit_time === null) {
            return 0;
        }

        $entry = Carbon::createFromFormat('H:i:s', $log->entry_time)
            ?? Carbon::createFromFormat('H:i', $log->entry_time);
        $exit = Carbon::createFromFormat('H:i:s', $log->exit_time)
            ?? Carbon::createFromFormat('H:i', $log->exit_time);

        if ($entry === null || $exit === null) {
            return 0;
        }

        return max(0, (int) $entry->diffInMinutes($exit));
    }
}
