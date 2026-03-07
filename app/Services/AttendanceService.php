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

        $employee = Employee::withoutGlobalScopes()
            ->with(['workShift' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('id', $employeeId)
            ->where('company_id', $companyId)
            ->first();

        $workShift = $employee?->workShift;

        $logs = AttendanceLog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

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
                    'start_date' => $startDate->toDateString(),
                    'duration' => $durationDays,
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
     * Compute aggregated totals from logs and calendar info,
     * then persist per-day calculated fields back to the database.
     *
     * @param  Carbon  $startDate  Period start (inclusive)
     * @param  int  $durationDays  Number of days to evaluate
     * @param  Collection  $logs  Attendance log records
     * @param  array  $holidayDates  Gregorian date strings  e.g. ['2025-01-01']
     * @param  WorkShift|null  $workShift  Employee's shift (null = default shift)
     * @return array Aggregated period totals
     */
    public function computeTotals(Carbon $startDate, int $durationDays, Collection $logs, array $holidayDates, ?WorkShift $workShift = null): array
    {

        $workMinutesPerDay = $this->shiftWorkMinutes($workShift);

        /** @var array<string, mixed> Key = 'Y-m-d' date string */
        $logsByDate = $logs->keyBy(
            fn ($log) => $log->log_date instanceof Carbon
                ? $log->log_date->toDateString()
                : (string) $log->log_date
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

        // ── Day loop ──────────────────────────────────────────────────────────
        for ($i = 0; $i < $durationDays; $i++) {

            $day = $startDate->copy()->addDays($i);
            $dateStr = $day->toDateString();

            $isFriday = $day->dayOfWeek === Carbon::FRIDAY;
            $isHoliday = in_array($dateStr, $holidayDates, true);

            // ── Off-day (Friday / public holiday) ────────────────────────────
            if ($isFriday || $isHoliday) {
                if (isset($logsByDate[$dateStr])) {
                    $workedMin = $this->workedMinutes($logsByDate[$dateStr], $workShift);

                    $isFriday
                        ? $fridayMin += $workedMin
                        : $holidayMin += $workedMin;

                    // Persist off-day overtime to the log record
                    $logsByDate[$dateStr]->update([
                        'worked' => $workedMin,
                        'delay' => 0,
                        'early_leave' => 0,
                        'overtime' => $workedMin,   // every minute is overtime on an off-day
                        'mission' => 0,
                    ]);
                }

                continue;
            }

            // ── Regular work day ──────────────────────────────────────────────
            $workDays++;

            if (! isset($logsByDate[$dateStr])) {
                $absentDays++;

                continue;
            }

            $log = $logsByDate[$dateStr];
            $workedMin = $this->workedMinutes($log, $workShift);
            $logWorked = 0;
            $logDelay = 0;
            $logEarlyLeave = 0;
            $logOvertime = 0;
            $logMission = 0;

            if ($log->mission > 0) {
                $missionDays++;
                $presentDays++;

                $logMission = $log->mission;
                $logWorked = $workMinutesPerDay;   // treat full shift as worked
            } elseif ($log->paid_leave > 0) {
                $paidLeaveDays++;
                $presentDays++;

                $logWorked = $workMinutesPerDay;    // counts as a full day worked
            } elseif ($log->unpaid_leave > 0) {
                $unpaidLeaveDays++;
                // unpaid leave is neither present nor absent in the period totals
            } elseif ($workedMin > 0) {
                $presentDays++;

                $logWorked = $workedMin;

                $extra = $workedMin - $workMinutesPerDay;
                if ($extra > 0) {
                    $overtimeMin += $extra;
                    $logOvertime = $extra;
                }

                // Delay  : clocked in late  → negative "extra" on the opening side
                // Early leave: clocked out early → computed inside workedMinutes helper;
                // expose them here if your helper or log model carries those values.
                $logDelay = max(0, $log->delay ?? 0);
                $logEarlyLeave = max(0, $log->early_leave ?? 0);
            }

            // ── Absent (log exists but no minutes recorded) ───────────────────
            else {
                $absentDays++;
            }

            $log->update([
                'worked' => $logWorked,
                'delay' => $logDelay,
                'early_leave' => $logEarlyLeave,
                'overtime' => $logOvertime,
                'mission' => $logMission,
            ]);
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
