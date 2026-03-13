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
 * Strategy: each AttendanceLog row is always kept fully calculated.
 * Whenever a log changes (entry/exit recorded, leave/mission/overtime
 * approved or revoked) call recalculateLog() to recompute that single
 * row.  computeTotals() then simply SUMs the pre-calculated columns —
 * no business logic lives there anymore.
 *
 * Hourly-leave interaction rules
 * ─────────────────────────────
 * • Any hourly leave (start / middle / end of shift) makes
 *   worked = actual_worked + paid_leave_minutes  (≤ shiftMinutes)
 * • delay and early_leave are always measured against the ORIGINAL
 *   shift boundaries (not adjusted for the leave window).
 * • Daily leave / mission → worked = shiftMinutes, delay = 0, early_leave = 0.
 */
class AttendanceService
{
    /** Fallback shift start used when the employee has no WorkShift */
    public const DEFAULT_SHIFT_START = '08:00';

    /** Fallback shift end used when the employee has no WorkShift */
    public const DEFAULT_SHIFT_END = '17:00';

    /** Fallback working minutes per day (8 hours) */
    public const DEFAULT_WORK_MINUTES_PER_DAY = 480;

    // ══════════════════════════════════════════════════════════════════════
    // PUBLIC: single-log recalculation  (call this on every log mutation)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Recompute all derived columns for one AttendanceLog row and persist them.
     *
     * Call this whenever any of the following changes on a log:
     *   • entry_time / exit_time updated
     *   • paid_leave, unpaid_leave, mission, overtime approved or revoked
     *
     * The method is idempotent — calling it multiple times produces the
     * same result.
     */
    public function recalculateLog(AttendanceLog $log): AttendanceLog
    {
        $employee  = Employee::with('workShift')->find($log->employee_id);
        $workShift = $employee?->workShift;

        $logDate = $log->log_date;
        $isFriday  = $logDate->dayOfWeek === Carbon::FRIDAY;
        $isHoliday = PublicHoliday::where('date', $logDate->toDateString())->exists();

        $columns = $this->computeLogColumns($log, $workShift, $isFriday, $isHoliday);

        $log->update($columns);

        return $log->fresh();
    }

    /**
     * Compute the derived columns for a log row WITHOUT persisting.
     * Useful for previewing or testing.
     *
     * @return array{worked: int, delay: int, early_leave: int, overtime: int, mission: int}
     */
    public function computeLogColumns(AttendanceLog $log, ?WorkShift    $workShift, bool $isFriday = false, bool $isHoliday = false): array 
    {
        $shiftMinutes = $this->shiftWorkMinutes($workShift);

        // ── Off-day (Friday / public holiday) ────────────────────────────
        if ($isFriday || $isHoliday) {
            $workedMin = $this->rawWorkedMinutes($log, $workShift);
            return [
                'worked'      => $workedMin,
                'delay'       => 0,
                'is_friday'   => $isFriday,
                'is_holiday'  => $isHoliday,
            ];
        }

        // ── Daily leave / sick leave ──────────────────────────────────────
        if ($log->paid_leave >= $shiftMinutes) {
            return [
                'worked'      => $shiftMinutes,
                'delay'       => 0,
                'is_friday'   => $isFriday,
                'is_holiday'  => $isHoliday,
            ];
        }

        // ── Mission (daily) ───────────────────────────────────────────────
        if ($log->mission > 0 && $log->mission >= $shiftMinutes) {
            return [
                'worked'      => $shiftMinutes,
                'delay'       => 0,
                'early_leave' => 0,
                'overtime'    => 0,
                'mission'     => $log->mission,
            ];
        }

        // ── No clock data ─────────────────────────────────────────────────
        if ($log->entry_time === null || $log->exit_time === null) {
            // Unpaid leave: no clock-in required
            if ($log->unpaid_leave > 0) {
                return [
                    'worked'      => 0,
                    'delay'       => 0,
                    'early_leave' => 0,
                    'overtime'    => 0,
                    'mission'     => 0,
                ];
            }

            return [
                'worked'      => 0,
                'delay'       => 0,
                'early_leave' => 0,
                'overtime'    => 0,
                'mission'     => 0,
            ];
        }

        // ── Hourly paid leave (any position: start / middle / end) ───────
        // worked = actual + leave_minutes, capped at shiftMinutes
        // delay / early_leave measured against ORIGINAL shift boundaries
        $hourlyLeave = (int) $log->paid_leave;   // minutes already stored on the log
        if ($hourlyLeave > 0 && $hourlyLeave < $shiftMinutes) {
            $rawWorked   = $this->rawWorkedMinutes($log, $workShift);
            $totalWorked = min($shiftMinutes, $rawWorked + $hourlyLeave);

            $bounds = $this->shiftDelayEarlyLeave($log, $workShift);

            return [
                'worked'      => $totalWorked,
                'delay'       => $bounds['delay'],
                'early_leave' => $bounds['early_leave'],
                'overtime'    => 0,   // leave days don't earn overtime
                'mission'     => (int) ($log->mission ?? 0),
            ];
        }

        // ── Hourly mission ────────────────────────────────────────────────
        if ($log->mission > 0 && $log->mission < $shiftMinutes) {
            $rawWorked   = $this->rawWorkedMinutes($log, $workShift);
            $totalWorked = min($shiftMinutes, $rawWorked + (int) $log->mission);

            $bounds = $this->shiftDelayEarlyLeave($log, $workShift);

            return [
                'worked'      => $totalWorked,
                'delay'       => $bounds['delay'],
                'early_leave' => $bounds['early_leave'],
                'overtime'    => 0,
                'mission'     => (int) $log->mission,
            ];
        }

        // ── Plain attendance (no leave, no mission) ───────────────────────
        $rawWorked    = $this->rawWorkedMinutes($log, $workShift);
        $bounds       = $this->shiftDelayEarlyLeave($log, $workShift);

        // Overtime: only count up to the manager-approved amount
        $computedOvertime  = $bounds['overtime'];
        $approvedOvertime  = (int) ($log->overtime ?? 0);
        $earnedOvertime    = ($computedOvertime > 0 && $approvedOvertime > 0)
            ? min($approvedOvertime, $computedOvertime)
            : 0;

        return [
            'worked'      => $rawWorked,
            'delay'       => $bounds['delay'],
            'early_leave' => $bounds['early_leave'],
            'overtime'    => $earnedOvertime,
            'mission'     => 0,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // PUBLIC: monthly aggregation  (pure SUM — no business logic)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Calculate attendance totals for a given employee over a date range
     * and persist (or update) a MonthlyAttendance record.
     *
     * Assumes all AttendanceLog rows in the range are already up-to-date
     * (i.e. recalculateLog() has been called for each log that changed).
     */
    public function calculateAndStore(
        int    $employeeId,
        Carbon $startDate,
        int    $durationDays,
        int    $jalaliYear,
        int    $jalaliMonth
    ): MonthlyAttendance {
        $companyId = getActiveCompany();
        $endDate   = $startDate->copy()->addDays($durationDays - 1);

        $logs = AttendanceLog::where('employee_id', $employeeId)
            ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $holidays = PublicHoliday::whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->pluck('date')
            ->map(fn($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->toArray();

        $totals = $this->computeTotals($startDate, $durationDays, $logs, $holidays);

        /** @var MonthlyAttendance $attendance */
        $attendance = MonthlyAttendance::updateOrCreate(
            [
                'company_id'  => $companyId,
                'employee_id' => $employeeId,
                'year'        => $jalaliYear,
                'month'       => $jalaliMonth,
            ],
            array_merge($totals, [
                'company_id'  => $companyId,
                'employee_id' => $employeeId,
                'year'        => $jalaliYear,
                'month'       => $jalaliMonth,
                'start_date'  => $startDate->toDateString(),
                'duration'    => $durationDays,
            ])
        );

        AttendanceLog::where('employee_id', $employeeId)
            ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->update(['monthly_attendance_id' => $attendance->id]);

        return $attendance;
    }

    /**
     * Aggregate pre-calculated log columns into period totals.
     *
     * This method contains NO business logic — it only counts days and
     * sums minutes that are already stored on each log row.
     *
     * @param  Collection  $logs        AttendanceLog records (already recalculated)
     * @param  array       $holidayDates  Gregorian date strings e.g. ['2025-03-21']
     */
    public function computeTotals(
        Carbon     $startDate,
        int        $durationDays,
        Collection $logs,
        array      $holidayDates
    ): array {
        $logsByDate = $logs->keyBy(
            fn($log) => $log->log_date instanceof Carbon
                ? $log->log_date->toDateString()
                : (string) $log->log_date
        );

        $workDays      = 0;
        $presentDays   = 0;
        $absentDays    = 0;
        $overtimeMin   = 0;
        $missionDays   = 0;
        $paidLeaveDays = 0;
        $unpaidLeaveMin = 0;
        $fridayMin     = 0;
        $holidayMin    = 0;

        for ($i = 0; $i < $durationDays; $i++) {
            $day     = $startDate->copy()->addDays($i);
            $dateStr = $day->toDateString();

            $isFriday  = $day->dayOfWeek === Carbon::FRIDAY;
            $isHoliday = in_array($dateStr, $holidayDates, true);

            // ── Off-day ───────────────────────────────────────────────────
            if ($isFriday || $isHoliday) {
                if (isset($logsByDate[$dateStr])) {
                    $log = $logsByDate[$dateStr];
                    $isFriday
                        ? $fridayMin  += (int) $log->overtime
                        : $holidayMin += (int) $log->overtime;
                }
                continue;
            }

            // ── Regular work day ──────────────────────────────────────────
            $workDays++;

            if (! isset($logsByDate[$dateStr])) {
                $absentDays++;
                continue;
            }

            $log = $logsByDate[$dateStr];

            // Determine presence type by what's stored on the log
            if ((int) $log->mission >= $this->shiftWorkMinutesFromLog($log)) {
                $missionDays++;
                $presentDays++;
            } elseif ((int) $log->paid_leave > 0 && (int) $log->worked > 0) {
                // Could be daily or hourly leave — count as present
                $paidLeaveDays++;
                $presentDays++;
                $overtimeMin += (int) $log->overtime;
            } elseif ((int) $log->unpaid_leave > 0) {
                $unpaidLeaveMin += (int) $log->unpaid_leave;
            } elseif ((int) $log->worked > 0) {
                $presentDays++;
                $overtimeMin += (int) $log->overtime;
            } else {
                $absentDays++;
            }
        }

        return [
            'work_days'       => $workDays,
            'present_days'    => $presentDays,
            'absent_days'     => $absentDays,
            'overtime'        => $overtimeMin,
            'mission_days'    => $missionDays,
            'paid_leave_days' => $paidLeaveDays,
            'unpaid_leave_min'=> $unpaidLeaveMin,
            'friday'          => $fridayMin,
            'holiday'         => $holidayMin,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // PUBLIC: personnel-request sync  (unchanged contract)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Apply (or reverse) a personnel request onto AttendanceLog records,
     * then recalculate each affected log.
     *
     * When $subtract is false (approval):  field is incremented per day.
     * When $subtract is true  (reverting): field is decremented.
     */
    public function syncPersonnelRequestLogs(PersonnelRequest $personnelRequest, bool $subtract = false): void
    {
        $field = match ($personnelRequest->request_type) {
            PersonnelRequestType::LEAVE_HOURLY,
            PersonnelRequestType::LEAVE_DAILY,
            PersonnelRequestType::SICK_LEAVE       => 'paid_leave',
            PersonnelRequestType::LEAVE_WITHOUT_PAY => 'unpaid_leave',
            PersonnelRequestType::MISSION_HOURLY,
            PersonnelRequestType::MISSION_DAILY    => 'mission',
            PersonnelRequestType::OVERTIME_ORDER   => 'overtime',
            default                                => null,
        };

        if ($field === null) {
            return;
        }

        $start      = $personnelRequest->start_date;
        $end        = $personnelRequest->end_date;
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
            $current  = $start->copy()->startOfDay();
            $endDay   = $end->copy()->startOfDay();

            while ($current->lte($endDay)) {
                $log = $this->applyDeltaToLog($employeeId, $companyId, $current, $field, $delta * $duration);
                if ($log) {
                    $this->recalculateLog($log);
                }
                $current->addDay();
            }
        } else {
            // Hourly / overtime: exact minutes, single day
            $minutes = max(0, (int) $start->diffInMinutes($end));
            $log     = $this->applyDeltaToLog($employeeId, $companyId, $start, $field, $delta * $minutes);
            if ($log) {
                $this->recalculateLog($log);
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // PUBLIC: shift helpers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Return the productive working minutes for a shift day.
     * Formula: (end − start) − break.  Cross-midnight shifts are handled.
     * Falls back to DEFAULT_WORK_MINUTES_PER_DAY when no shift is provided.
     */
    public function shiftWorkMinutes(?WorkShift $workShift): int
    {
        if ($workShift === null) {
            return self::DEFAULT_WORK_MINUTES_PER_DAY;
        }

        $start = Carbon::createFromFormat('H:i:s', $workShift->start_time)
            ?? Carbon::createFromFormat('H:i', $workShift->start_time);
        $end   = Carbon::createFromFormat('H:i:s', $workShift->end_time)
            ?? Carbon::createFromFormat('H:i', $workShift->end_time);

        if ($start === null || $end === null) {
            return self::DEFAULT_WORK_MINUTES_PER_DAY;
        }

        $total = (int) $start->diffInMinutes($end, false);

        return max(0, $total - max(0, (int) ($workShift->break ?? 0)));
    }

    // ══════════════════════════════════════════════════════════════════════
    // PRIVATE: calculation helpers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Raw worked minutes = (exit − entry) − break, with no leave/mission logic.
     */
    private function rawWorkedMinutes(AttendanceLog $log, ?WorkShift $workShift): int
    {
        if ($log->entry_time === null || $log->exit_time === null) {
            return 0;
        }

        $entry = Carbon::createFromFormat('H:i:s', $log->entry_time)
            ?? Carbon::createFromFormat('H:i', $log->entry_time);
        $exit  = Carbon::createFromFormat('H:i:s', $log->exit_time)
            ?? Carbon::createFromFormat('H:i', $log->exit_time);

        if ($entry === null || $exit === null) {
            return 0;
        }

        $breakMin  = max(0, (int) ($workShift?->break ?? 0));
        $rawMin    = (int) $entry->diffInMinutes($exit, false);

        return max(0, $rawMin - $breakMin);
    }

    /**
     * Compute delay, early_leave, and raw overtime relative to the
     * ORIGINAL shift boundaries (float_before grace window applied).
     *
     * @return array{delay: int, early_leave: int, overtime: int}
     */
    private function shiftDelayEarlyLeave(AttendanceLog $log, ?WorkShift $workShift): array
    {
        $empty = ['delay' => 0, 'early_leave' => 0, 'overtime' => 0];

        if ($workShift === null || $log->entry_time === null || $log->exit_time === null) {
            return $empty;
        }

        $entry = Carbon::createFromFormat('H:i:s', $log->entry_time)
            ?? Carbon::createFromFormat('H:i', $log->entry_time);
        $exit  = Carbon::createFromFormat('H:i:s', $log->exit_time)
            ?? Carbon::createFromFormat('H:i', $log->exit_time);

        $shiftStart = Carbon::createFromFormat('H:i:s', $workShift->start_time)
            ?? Carbon::createFromFormat('H:i', $workShift->start_time);
        $shiftEnd   = Carbon::createFromFormat('H:i:s', $workShift->end_time)
            ?? Carbon::createFromFormat('H:i', $workShift->end_time);

        if ($entry === null || $exit === null || $shiftStart === null || $shiftEnd === null) {
            return $empty;
        }

        $floatBefore  = max(0, (int) ($workShift->float_before ?? 0));
        $floatCutoff  = $shiftStart->copy()->addMinutes($floatBefore);
        $lateMinutes  = (int) $floatCutoff->diffInMinutes($entry, false);

        if ($lateMinutes <= 0) {
            // Within grace window: shift end extends proportionally
            $offset      = max(0, (int) $shiftStart->diffInMinutes($entry, false));
            $adjustedEnd = $shiftEnd->copy()->addMinutes($offset);
            $arrivalDelay = 0;
        } else {
            $arrivalDelay = $lateMinutes;
            $adjustedEnd  = $shiftEnd->copy()->addMinutes($floatBefore);
        }

        $exitOffset = (int) $adjustedEnd->diffInMinutes($exit, false);

        return [
            'delay'       => $arrivalDelay,
            'early_leave' => max(0, -$exitOffset),
            'overtime'    => max(0,  $exitOffset),
        ];
    }

    /**
     * Attempt to read the shift minutes directly from a loaded log's
     * relationship to avoid an extra DB query in computeTotals().
     * Falls back to DEFAULT_WORK_MINUTES_PER_DAY.
     */
    private function shiftWorkMinutesFromLog(AttendanceLog $log): int
    {
        // If the employee/shift relation is already loaded use it;
        // otherwise fall back to the default so computeTotals() stays a pure
        // aggregator with no extra queries.
        $shift = $log->employee?->workShift ?? null;
        return $this->shiftWorkMinutes($shift);
    }

    /**
     * Find (or create) an AttendanceLog for the given employee/date and apply
     * a signed delta to the specified field.
     * Returns the log (or null if nothing was changed).
     */
    private function applyDeltaToLog(
        int    $employeeId,
        int    $companyId,
        Carbon $date,
        string $field,
        int    $minutes
    ): ?AttendanceLog {
        $log = AttendanceLog::where('employee_id', $employeeId)
            ->where('log_date', $date->toDateString())
            ->first();

        if ($log === null) {
            if ($minutes < 0) {
                return null; // nothing to subtract
            }
            $log = AttendanceLog::create([
                'employee_id' => $employeeId,
                'company_id'  => $companyId,
                'log_date'    => $date->toDateString(),
            ]);
        }

        $log->update([
            $field => max(0, (int) $log->{$field} + $minutes),
        ]);

        return $log->fresh();
    }
}