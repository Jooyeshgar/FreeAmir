<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\PublicHoliday;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * AttendanceService
 *
 * Calculates monthly attendance totals from daily attendance logs
 * using the employee's assigned work shift. Falls back to a default
 * 8-hour shift (08:00–17:00, 480 minutes) when no shift is assigned.
 */
class AttendanceService
{
    /** Fallback shift start (HH:MM) used when employee has no work shift */
    public const DEFAULT_SHIFT_START = '08:00';

    /** Fallback shift end (HH:MM) used when employee has no work shift */
    public const DEFAULT_SHIFT_END = '17:00';

    /** Fallback working minutes per day (8 hours) */
    public const DEFAULT_WORK_MINUTES_PER_DAY = 480;

    /**
     * Calculate attendance totals for a given employee over a date range
     * and persist a MonthlyAttendance record (or update an existing one).
     *
     * The employee's assigned WorkShift determines the expected working
     * minutes per day. When no shift is assigned, the default 480-minute
     * shift is used.
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

        // Load the employee's work shift to determine daily expected minutes.
        // withoutGlobalScopes is used on the relation to bypass FiscalYearScope
        // so the shift is always resolved regardless of the active session context.
        $employee = Employee::withoutGlobalScopes()
            ->with(['workShift' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('id', $employeeId)
            ->where('company_id', $companyId)
            ->first();

        $workShift = $employee?->workShift;

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

        $totals = $this->computeTotals($startDate, $durationDays, $logs, $holidays, $workShift);

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
     * @param  WorkShift|null  $workShift  Employee's assigned shift (null = use default)
     */
    public function computeTotals(
        Carbon $startDate,
        int $durationDays,
        Collection $logs,
        array $holidayDates,
        ?WorkShift $workShift = null
    ): array {
        $workMinutesPerDay = $this->shiftWorkMinutes($workShift);

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
                    $workedMin = $this->workedMinutes($logsByDate[$dateStr], $workShift);
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
            $workedMin = $this->workedMinutes($log, $workShift);

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

                // Overtime: minutes worked beyond the shift's standard duration
                $extra = $workedMin - $workMinutesPerDay;
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
     * Return the number of productive working minutes in a shift day.
     *
     * Formula: (end_time − start_time) − break_minutes
     * For cross-midnight shifts, 24 hours are added to the difference.
     * Falls back to DEFAULT_WORK_MINUTES_PER_DAY when no shift is provided.
     */
    public function shiftWorkMinutes(?WorkShift $workShift): int
    {
        if ($workShift === null) {
            return self::DEFAULT_WORK_MINUTES_PER_DAY;
        }

        $start = Carbon::createFromFormat('H:i:s', $workShift->start_time)
            ?? Carbon::createFromFormat('H:i', $workShift->start_time);
        $end = Carbon::createFromFormat('H:i:s', $workShift->end_time)
            ?? Carbon::createFromFormat('H:i', $workShift->end_time);

        if ($start === null || $end === null) {
            return self::DEFAULT_WORK_MINUTES_PER_DAY;
        }

        $totalMinutes = (int) $start->diffInMinutes($end, false);

        // Negative diff means the shift crosses midnight (e.g. 22:00 → 06:00)
        if ($totalMinutes <= 0) {
            $totalMinutes += 24 * 60;
        }

        $breakMinutes = max(0, (int) ($workShift->break ?? 0));

        return max(0, $totalMinutes - $breakMinutes);
    }

    /**
     * Calculate actual worked minutes from an AttendanceLog entry.
     *
     * When the log already stores pre-calculated `worked` minutes those
     * are used directly. Otherwise the duration is derived from
     * entry_time / exit_time, capped to the shift's expected minutes to
     * avoid double-counting the break period in raw clock-in/out data.
     */
    private function workedMinutes(AttendanceLog $log, ?WorkShift $workShift = null): int
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

        $rawMinutes = (int) $entry->diffInMinutes($exit, false);

        // Handle cross-midnight exit
        if ($rawMinutes < 0) {
            $rawMinutes += 24 * 60;
        }

        // Subtract break time so raw clock-in/out values are comparable to
        // the shift's net productive minutes when no pre-computed `worked` exists.
        $breakMinutes = max(0, (int) (($workShift?->break) ?? 0));
        $rawMinutes = max(0, $rawMinutes - $breakMinutes);

        return $rawMinutes;
    }
}
