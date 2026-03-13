<?php

namespace App\Services;

use App\Enums\PersonnelRequestType;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\PersonnelRequest;
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
    public function calculateAndStore(int $employeeId, Carbon $startDate, int $durationDays, int $jalaliYear, int $jalaliMonth): MonthlyAttendance 
    {
        $companyId = getActiveCompany();
        $endDate = $startDate->copy()->addDays($durationDays - 1);

        $employee = Employee::with(['workShift'])->where('id', $employeeId)->first();

        $workShift = $employee?->workShift;

        $logs = AttendanceLog::where('employee_id', $employeeId)
            ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $holidays = PublicHoliday::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->toArray();

        $totals = $this->computeTotals($startDate, $durationDays, $logs, $holidays, $workShift);

        /** @var MonthlyAttendance $attendance */
        $attendance = MonthlyAttendance::updateOrCreate(
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
        AttendanceLog::where('employee_id', $employeeId)
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
        $unpaidLeaveMin = 0;
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
                    $calc = $this->workedMinutes($logsByDate[$dateStr], $workShift);
                    $workedMin = $calc['worked'];

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
            $calc = $this->workedMinutes($log, $workShift);
            $workedMin = $calc['worked'];
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

                $logWorked = $log->paid_leave;
            } elseif ($log->unpaid_leave > 0) {
                $unpaidLeaveMin += $log->unpaid_leave;
            } elseif ($workedMin > 0) {
                $presentDays++;

                $logWorked = $workedMin;

                $approvedOvertime = (int) ($log->overtime ?? 0);
                $computedOvertime = $calc['overtime'];
                if ($computedOvertime > 0 && $approvedOvertime > 0) {
                    $overtimeMin += min($approvedOvertime, $computedOvertime);
                }

                $logDelay = $calc['delay'];
                $logEarlyLeave = $calc['early_leave'];
            }

            // ── Absent (log exists but no minutes recorded) ───────────────────
            else {
                $absentDays++;
            }

            $log->update([
                'worked' => $logWorked,
                'delay' => $logDelay,
                'early_leave' => $logEarlyLeave,
                // 'overtime' => $logOvertime,
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
            'unpaid_leave_min' => $unpaidLeaveMin,
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

        $start = Carbon::createFromFormat('H:i:s', $workShift->start_time) ?? Carbon::createFromFormat('H:i', $workShift->start_time);
        $end = Carbon::createFromFormat('H:i:s', $workShift->end_time) ?? Carbon::createFromFormat('H:i', $workShift->end_time);

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
     * Calculate worked minutes, arrival delay, early leave, and overtime from an AttendanceLog.
     *
     * Uses the shift's float_after window to determine whether a late arrival incurs a
     * delay penalty. Within the float window, the shift end extends proportionally so the
     * employee can leave later without penalty. Beyond the float window, the end is capped
     * at shift_end + float_after and excess lateness is counted as arrival delay.
     *
     * @return array{worked: int, delay: int, early_leave: int, overtime: int}
     */
    private function workedMinutes(AttendanceLog $log, ?WorkShift $workShift = null): array
    {
        $empty = ['worked' => 0, 'delay' => 0, 'early_leave' => 0, 'overtime' => 0];

        if ($log->entry_time === null || $log->exit_time === null) {
            return $empty;
        }

        $entry = Carbon::createFromFormat('H:i:s', $log->entry_time) ?? Carbon::createFromFormat('H:i', $log->entry_time);
        $exit = Carbon::createFromFormat('H:i:s', $log->exit_time) ?? Carbon::createFromFormat('H:i', $log->exit_time);

        if ($entry === null || $exit === null) {
            return $empty;
        }

        $breakMinutes = max(0, (int) (($workShift?->break) ?? 0));
        $rawMinutes = (int) $entry->diffInMinutes($exit, false);
        $worked = max(0, $rawMinutes - $breakMinutes);

        if ($workShift === null) {
            return ['worked' => $worked, 'delay' => 0, 'early_leave' => 0, 'overtime' => 0];
        }

        $shiftStart = Carbon::createFromFormat('H:i:s', $workShift->start_time) ?? Carbon::createFromFormat('H:i', $workShift->start_time);
        $shiftEnd = Carbon::createFromFormat('H:i:s', $workShift->end_time) ?? Carbon::createFromFormat('H:i', $workShift->end_time);

        if ($shiftStart === null || $shiftEnd === null) {
            return ['worked' => $worked, 'delay' => 0, 'early_leave' => 0, 'overtime' => 0];
        }

        // $floatAfter = max(0, (int) ($workShift->float_after ?? 0));
        $floatBefore = max(0, (int) ($workShift->float_before ?? 0));

        // Latest allowed arrival without a delay penalty
        $floatCutoff = $shiftStart->copy()->addMinutes($floatBefore);

        // Minutes past the float cutoff (positive = late beyond grace window)
        $lateMinutes = (int) $floatCutoff->diffInMinutes($entry, false);

        //No Delay
        if ($lateMinutes <= 0) {
            $offset = max(0, (int) $shiftStart->diffInMinutes($entry, false));
            $adjustedEnd = $shiftEnd->copy()->addMinutes($offset);
            $arrivalDelay = 0;
        } else {
            $arrivalDelay = $lateMinutes;
            $adjustedEnd = $shiftEnd->copy()->addMinutes($floatBefore); // Max float delay
        }

        // Positive = left late (overtime), negative = left early
        $exitOffset = (int) $adjustedEnd->diffInMinutes($exit, false);

        return [
            'worked'      => $worked,
            'delay'       => $arrivalDelay,
            'early_leave' => max(0, -$exitOffset),
            'overtime'    => max(0, $exitOffset),
        ];
    }

    /**
     * Apply (or reverse) the effect of a personnel request onto AttendanceLog records.
     *
     * When $subtract is false (approval): the relevant field is incremented per day.
     * When $subtract is true  (reverting an approved request): the field is decremented.
     *
     * Type → field mapping:
     *   LEAVE_HOURLY / LEAVE_DAILY / SICK_LEAVE → paid_leave
     *   LEAVE_WITHOUT_PAY                       → unpaid_leave
     *   MISSION_HOURLY / MISSION_DAILY          → mission
     *   OVERTIME_ORDER                          → overtime
     *   REMOTE_WORK / OTHER                     → no-op
     */
    public function syncPersonnelRequestLogs(PersonnelRequest $personnelRequest, bool $subtract = false): void
    {
        $field = match ($personnelRequest->request_type) {
            PersonnelRequestType::LEAVE_HOURLY,
            PersonnelRequestType::LEAVE_DAILY,
            PersonnelRequestType::SICK_LEAVE     => 'paid_leave',
            PersonnelRequestType::LEAVE_WITHOUT_PAY => 'unpaid_leave',
            PersonnelRequestType::MISSION_HOURLY,
            PersonnelRequestType::MISSION_DAILY  => 'mission',
            PersonnelRequestType::OVERTIME_ORDER => 'overtime',
            default                              => null,
        };

        if ($field === null) {
            return;
        }

        $start = $personnelRequest->start_date;
        $end   = $personnelRequest->end_date;
        $companyId  = $personnelRequest->company_id;
        $employeeId = $personnelRequest->employee_id;
        $delta      = $subtract ? -1 : 1;

        $isDailyType = in_array($personnelRequest->request_type, [
            PersonnelRequestType::LEAVE_DAILY,
            PersonnelRequestType::SICK_LEAVE,
            PersonnelRequestType::LEAVE_WITHOUT_PAY,
            PersonnelRequestType::MISSION_DAILY,
        ], strict: true);

        if ($isDailyType) {
            $duration = $this->shiftWorkMinutes($personnelRequest->employee->workShift);
            $current = $start->copy()->startOfDay();
            $endDay  = $end->copy()->startOfDay();
            while ($current->lte($endDay)) {
                $this->applyDeltaToLog($employeeId, $companyId, $current, $field, $delta * $duration);
                $current->addDay();
            }
        } else {
            // Hourly / overtime: exact minutes, single day
            $minutes = max(0, (int) $start->diffInMinutes($end));
            $this->applyDeltaToLog($employeeId, $companyId, $start, $field, $delta * $minutes);
        }
    }

    /**
     * Find (or create) an AttendanceLog for the given employee/date and apply
     * a signed delta to the specified field. When $subtract is true and no log
     * exists the operation is skipped silently.
     */
    private function applyDeltaToLog(int $employeeId, int $companyId, Carbon $date, string $field, int $minutes): void
    {
        $log = AttendanceLog::where('employee_id', $employeeId)
            ->where('log_date', $date->toDateString())
            ->first();

        if ($log === null) {
            if ($minutes < 0) {
                return; // nothing to subtract from
            }
            $log = AttendanceLog::create([
                'employee_id' => $employeeId,
                'company_id'  => $companyId,
                'log_date'    => $date->toDateString(),
            ]);
        }

        $log->update([
            $field => max(0, $log->{$field} + $minutes),
        ]);
    }
}
