<?php

namespace App\Services;

use App\Enums\PersonnelRequestType;
use App\Enums\ThursdayStatus;
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
 * • Hourly leave/mission taken MID-SHIFT (its time window overlaps the
 *   clocked entry..exit window) is excluded from worked, because the raw
 *   clocked duration already counts that time as present.
 *   worked = (actual_clocked − mid_shift_leave_overlap) + remote_work
 *   e.g. clock 08:00→16:00 with leave 11:00–13:00 → worked = 6h, leave = 2h.
 * • Leave/mission that fills an EDGE gap (before entry / after exit, i.e. it
 *   does not overlap the clocked window) instead absorbs delay/early_leave.
 *   delay and early_leave are first computed against the ORIGINAL shift
 *   boundaries, then reduced by that edge coverage (covers the gap — no penalty).
 *   Net delay   = max(0, raw_delay − edge_coverage)
 *   Net early_leave = max(0, raw_early_leave − remaining_edge_coverage)
 * • Daily leave / mission → worked = shiftMinutes, delay = 0, early_leave = 0.
 * • Daily remote work → worked = shiftMinutes, delay = 0, early_leave = 0
 *   (employee is fully present from home; an AttendanceLog is auto-created).
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
        $logDate = $log->log_date instanceof Carbon
            ? $log->log_date
            : Carbon::parse($log->log_date);

        $employee = Employee::with('workShift')->find($log->employee_id);
        $workShift = $employee?->workShift;
        $isFriday = $logDate->dayOfWeek === Carbon::FRIDAY;
        $isThursday = $logDate->dayOfWeek === Carbon::THURSDAY;
        $isHoliday = PublicHoliday::where('date', $logDate->toDateString())->exists();

        $remoteRequest = $employee?->personnelRequests()
            ->ofType(PersonnelRequestType::REMOTE_WORK)
            ->approved()
            ->coveringDate($logDate->toDateString())
            ->orderByDesc('id')
            ->first();

        // Approved hourly leave / mission requests with their exact time windows.
        // Their overlap with the clocked presence window is what tells us how
        // much of the leave/mission happened "while the employee was clocked in" (mid-shift),
        // so it can be excluded from worked rather than being counted as both work and leave.
        $hourlyCoverageRequests = $employee?->personnelRequests()->whereIn('request_type', [
            PersonnelRequestType::LEAVE_HOURLY->value,
            PersonnelRequestType::LEAVE_WITHOUT_PAY_HOURLY->value,
            PersonnelRequestType::MISSION_HOURLY->value,
        ])->approved()->coveringDate($logDate->toDateString())->get() ?? collect();

        $midShiftCoverage = $this->midShiftCoverageMinutes($log, $hourlyCoverageRequests);

        $columns = $this->computeLogColumns($log, $workShift, $isFriday, $isHoliday, $isThursday, $remoteRequest, $midShiftCoverage, $hourlyCoverageRequests);

        $log->update($columns);

        return $log->fresh();
    }

    /**
     * Compute derived attendance columns without persisting.
     *
     * Rules:
     * - worked = actual clocked minutes only
     * - paid_leave + mission + remote_work are coverage minutes, not worked minutes
     * - coverage absorbs delay and early_leave
     * - total coverage is capped by shift minutes
     *
     * @return array{
     *     worked:int,
     *     delay:int,
     *     early_leave:int,
     *     overtime:int,
     *     auto_overtime:int,
     *     mission:int,
     *     remote_work?:int,
     *     is_friday?:bool,
     *     is_holiday?:bool
     * }
     */
    public function computeLogColumns(AttendanceLog $log, ?WorkShift $workShift, bool $isFriday = false, bool $isHoliday = false, bool $isThursday = false, ?PersonnelRequest $remoteRequest = null, int $midShiftCoverage = 0, $hourlyCoverageRequests = null): array
    {
        // ── Thursday handling ──────────────────────────────────────────────
        $thursdayStatus = $isThursday ? ($workShift?->thursday_status ?? ThursdayStatus::FULL_DAY) : null;

        // Thursday holiday behaves like holiday
        if ($isThursday && $thursdayStatus === ThursdayStatus::HOLIDAY) {
            $isHoliday = true;
        }

        // Thursday half-day uses special exit time
        if ($isThursday && $thursdayStatus === ThursdayStatus::HALF_DAY && $workShift?->thursday_exit_time) {
            $workShift = clone $workShift;
            $workShift->end_time = $workShift->thursday_exit_time;
        }

        $shiftMinutes = $this->shiftWorkMinutes($workShift);
        $autoOvertimeCap = max(0, (int) ($workShift?->max_auto_overtime ?? 0));

        // Base values
        $paidLeave = max(0, (int) ($log->paid_leave ?? 0));
        $unpaidLeave = max(0, (int) ($log->unpaid_leave ?? 0));
        $mission = max(0, (int) ($log->mission ?? 0));
        $remoteWork = max(0, (int) ($log->remote_work ?? 0));
        $approvedOvertimeInput = max(0, (int) ($log->overtime ?? 0));

        $hasClockData = $log->entry_time !== null && $log->exit_time !== null;
        $rawWorked = $hasClockData ? $this->rawWorkedMinutes($log, $workShift) : 0;

        // ── Off-day (Friday / holiday) ─────────────────────────────────────
        if ($isFriday || $isHoliday) {
            return [
                'worked' => $rawWorked + $remoteWork,
                'delay' => 0,
                'early_leave' => 0,
                'overtime' => 0,
                'auto_overtime' => 0,
                'mission' => $mission,
                'remote_work' => $remoteWork,
                'is_friday' => $isFriday,
                'is_holiday' => $isHoliday,
            ];
        }

        // ── Approved REMOTE_WORK request → merge with office clock data ────
        // Remote work has explicit start/end times (from the PersonnelRequest)
        // so it must contribute to delay/early_leave/overtime exactly like
        // local work. Falls back to the legacy paths below when no request is
        // attached (e.g. seeded fixtures or pre-existing logs).
        if ($remoteRequest !== null && $workShift !== null) {
            return $this->computeMergedRemoteColumns(
                $log,
                $workShift,
                $remoteRequest,
                $paidLeave,
                $mission,
                $approvedOvertimeInput,
                $isFriday,
                $isHoliday,
            );
        }

        // ── No clock data ──────────────────────────────────────────────────
        if (! $hasClockData) {
            $coveredMinutes = min($shiftMinutes, $paidLeave + $unpaidLeave + $mission + min($remoteWork, $shiftMinutes));
            $remoteExtra = max(0, $remoteWork - $shiftMinutes);
            $autoOvertime = min($remoteExtra, $autoOvertimeCap);

            return [
                'worked' => $remoteWork,
                'delay' => 0,
                'early_leave' => max(0, $shiftMinutes - $coveredMinutes),
                'overtime' => 0,
                'auto_overtime' => $autoOvertime,
                'mission' => $mission,
                'remote_work' => $remoteWork,
                'is_friday' => $isFriday,
                'is_holiday' => $isHoliday,
            ];
        }

        // ── Normal working day ─────────────────────────────────────────────
        $bounds = $this->shiftDelayEarlyLeave($log, $workShift);

        // Determine explicit coverage boundaries if request objects are passed
        $explicitDelayCoverage = 0;
        $explicitEarlyLeaveCoverage = 0;

        if ($hourlyCoverageRequests !== null) {
            $entryTime = $log->entry_time ? Carbon::parse($log->entry_time)->format('H:i:s') : null;
            $exitTime = $log->exit_time ? Carbon::parse($log->exit_time)->format('H:i:s') : null;

            foreach ($hourlyCoverageRequests as $req) {
                $reqStart = Carbon::parse($req->start_date)->format('H:i:s');
                $reqEnd = Carbon::parse($req->end_date)->format('H:i:s');

                // Leave occurring prior to physical check-in covers morning delays
                if ($entryTime) {
                    $start = $reqStart;
                    $end = min($reqEnd, $entryTime);
                    if ($start < $end) {
                        $explicitDelayCoverage += Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
                    }
                }

                // Leave occurring after physical check-out covers early leaves
                if ($exitTime) {
                    $start = max($reqStart, $exitTime);
                    $end = $reqEnd;
                    if ($start < $end) {
                        $explicitEarlyLeaveCoverage += Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
                    }
                }
            }
        }

        // Hourly leave/mission taken while the clock was running (mid-shift) is already inside the clocked window,
        // so it must be removed from worked and is NOT available to absorb delay / early_leave (it covered the mid-shift gap, not the edges).
        $totalCoverage = $paidLeave + $unpaidLeave + $mission;
        $midShift = max(0, min($midShiftCoverage, $totalCoverage));
        $edgeCoverage = $totalCoverage - $midShift;

        $coveredMinutes = min($shiftMinutes, $edgeCoverage + min($remoteWork, $shiftMinutes));

        // Ensure explicit coverage mappings don't exceed logically allowed boundaries
        $explicitDelayCoverage = min($explicitDelayCoverage, $coveredMinutes);
        $explicitEarlyLeaveCoverage = min($explicitEarlyLeaveCoverage, max(0, $coveredMinutes - $explicitDelayCoverage));

        // Any coverage originating from DB logs strictly missing request records (legacy or manual inputs)
        $blindCoverage = max(0, $coveredMinutes - $explicitDelayCoverage - $explicitEarlyLeaveCoverage);

        $netDelay = max(0, $bounds['delay'] - $explicitDelayCoverage);
        $unusedExplicitDelay = max(0, $explicitDelayCoverage - $bounds['delay']);

        $usedBlindForDelay = min($netDelay, $blindCoverage);
        $netDelay -= $usedBlindForDelay;
        $blindCoverage -= $usedBlindForDelay;

        $netEarlyLeave = max(0, $bounds['early_leave'] - $explicitEarlyLeaveCoverage);
        $unusedExplicitEarlyLeave = max(0, $explicitEarlyLeaveCoverage - $bounds['early_leave']);

        $usedBlindForEarlyLeave = min($netEarlyLeave, $blindCoverage);
        $netEarlyLeave -= $usedBlindForEarlyLeave;
        $blindCoverage -= $usedBlindForEarlyLeave;

        // Unused explicit coverage correctly converts to unused for overtime offset
        $remainingCoverage = $blindCoverage + $unusedExplicitDelay + $unusedExplicitEarlyLeave;

        $computedOvertime = max(0, (int) ($bounds['overtime'] ?? 0));
        $approvedOvertime = min($approvedOvertimeInput, $computedOvertime);

        $remainingOvertime = max(0, $computedOvertime - $approvedOvertime);

        $remoteExtra = max(0, $remoteWork - $shiftMinutes);

        $unusedCoverageForOvertime = $remainingCoverage;

        $autoOvertime = min($remainingOvertime + $unusedCoverageForOvertime + $remoteExtra, $autoOvertimeCap);

        return [
            'worked' => max(0, $rawWorked - $midShift) + $remoteWork,
            'delay' => $netDelay,
            'early_leave' => $netEarlyLeave,
            'overtime' => $approvedOvertime,
            'auto_overtime' => $autoOvertime,
            'mission' => $mission,
            'remote_work' => $remoteWork,
            'is_friday' => $isFriday,
            'is_holiday' => $isHoliday,
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
    public function calculateAndStore(int $employeeId, Carbon $startDate, int $durationDays, int $jalaliYear, int $jalaliMonth): MonthlyAttendance
    {
        $companyId = getActiveCompany();
        $endDate = $startDate->copy()->addDays($durationDays - 1);

        $employee = Employee::with('workShift')->find($employeeId);
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

        AttendanceLog::where('employee_id', $employeeId)
            ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->update(['monthly_attendance_id' => $attendance->id]);

        return $attendance;
    }

    /**
     * Aggregate pre-calculated log columns into period totals.
     *
     * Strategy: every value (worked, overtime, leave, mission …) is already
     * stored correctly on each AttendanceLog row — this method only SUMs them.
     * The only thing calculated here is absent_days and work_days, because
     * an absent day has NO log row in the database.
     *
     * Thursday handling: whether Thursday is a work day depends on the
     * employee's WorkShift thursday_status:
     *   FULL_DAY  → regular work day
     *   HALF_DAY  → regular work day (shorter, but still a work day)
     *   HOLIDAY   → off-day (treated like Friday)
     * When no WorkShift is provided, Thursday defaults to a full work day.
     *
     * Stored units:
     *   work_days, present_days, absent_days → days (integer count)
     *   overtime, mission, paid_leave, unpaid_leave, friday, holiday → minutes
     *   unpaid_leave_days → full days
     *
     * @param  Collection  $logs  AttendanceLog records (already recalculated)
     * @param  array  $holidayDates  Gregorian date strings e.g. ['2025-03-21']
     * @param  WorkShift|null  $workShift  Employee's work shift (for Thursday rules)
     */
    public function computeTotals(Carbon $startDate, int $durationDays, Collection $logs, array $holidayDates, ?WorkShift $workShift = null): array
    {
        $logsByDate = $logs->keyBy(
            fn ($log) => $log->log_date instanceof Carbon
                ? $log->log_date->toDateString()
                : (string) $log->log_date
        );

        $thursdayIsHoliday = ($workShift?->thursday_status ?? ThursdayStatus::FULL_DAY) === ThursdayStatus::HOLIDAY;

        $workDays = 0;
        $presentDays = 0;
        $absentDays = 0;
        $overtimeMin = 0;
        $autoOvertimeMin = 0;
        $undertimeMin = 0;
        $missionMin = 0;
        $paidLeaveMin = 0;
        $unpaidLeaveMin = 0;
        $unpaidLeaveDays = 0;
        $fridayMin = 0;
        $holidayMin = 0;
        $remoteWorkMin = 0;

        for ($i = 0; $i < $durationDays; $i++) {
            $day = $startDate->copy()->addDays($i);
            $dateStr = $day->toDateString();

            $isFriday = $day->dayOfWeek === Carbon::FRIDAY;
            $isThursday = $day->dayOfWeek === Carbon::THURSDAY;
            $isHoliday = in_array($dateStr, $holidayDates, true);
            $isOffDay = $isFriday || $isHoliday || ($isThursday && $thursdayIsHoliday);

            $workDays++;

            if (! isset($logsByDate[$dateStr])) {
                if (! $isOffDay) {
                    $absentDays++;
                }

                continue;
            }

            $log = $logsByDate[$dateStr];

            $missionMin += (int) $log->mission;
            $remoteWorkMin += (int) $log->remote_work;

            if ($isOffDay) {
                $presentDays++;

                if (isset($logsByDate[$dateStr])) {
                    $log = $logsByDate[$dateStr];
                    $totalWork = (int) $log->worked + (int) $log->mission;
                    if ($isFriday) {
                        $fridayMin += $totalWork;
                    } else {
                        $holidayMin += $totalWork;
                    }
                }

                continue;
            }

            // ── Regular work day ──────────────────────────────────────────
            $dailyUnpaidLeave = (int) $log->unpaid_leave >= $this->shiftWorkMinutes($workShift);
            $unpaidLeaveMin += (int) $log->unpaid_leave;

            if ($dailyUnpaidLeave) {
                $unpaidLeaveDays++;

                continue;
            }

            $presentDays++;
            $overtimeMin += (int) $log->overtime;
            $autoOvertimeMin += (int) $log->auto_overtime;
            $paidLeaveMin += (int) $log->paid_leave;
            $undertimeMin += (int) $log->early_leave + $log->delay;
        }

        return [
            'work_days' => $workDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'overtime' => $overtimeMin,
            'auto_overtime' => $autoOvertimeMin,
            'undertime' => $undertimeMin,
            'mission' => $missionMin,
            'paid_leave' => $paidLeaveMin,
            'unpaid_leave' => $unpaidLeaveMin,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'remote_work' => $remoteWorkMin,
            'friday' => $fridayMin,
            'holiday' => $holidayMin,
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
            PersonnelRequestType::SICK_LEAVE => 'paid_leave',
            PersonnelRequestType::LEAVE_WITHOUT_PAY,
            PersonnelRequestType::LEAVE_WITHOUT_PAY_HOURLY => 'unpaid_leave',
            PersonnelRequestType::MISSION_HOURLY,
            PersonnelRequestType::MISSION_DAILY => 'mission',
            PersonnelRequestType::OVERTIME_ORDER => 'overtime',
            PersonnelRequestType::REMOTE_WORK => 'remote_work',
            default => null,
        };

        if ($field === null) {
            return;
        }

        $start = $personnelRequest->start_date;
        $end = $personnelRequest->end_date;
        $companyId = $personnelRequest->company_id;
        $employeeId = $personnelRequest->employee_id;
        $delta = $subtract ? -1 : 1;

        $isDailyType = in_array($personnelRequest->request_type, [
            PersonnelRequestType::LEAVE_DAILY,
            PersonnelRequestType::SICK_LEAVE,
            PersonnelRequestType::LEAVE_WITHOUT_PAY,
            PersonnelRequestType::MISSION_DAILY,
        ], strict: true);

        if ($isDailyType) {
            $duration = $this->shiftWorkMinutes($personnelRequest->employee->workShift);
            $current = $start->copy()->startOfDay();
            $endDay = $end->copy()->startOfDay();

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
            $log = $this->applyDeltaToLog($employeeId, $companyId, $start, $field, $delta * $minutes);
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
        $end = Carbon::createFromFormat('H:i:s', $workShift->end_time)
            ?? Carbon::createFromFormat('H:i', $workShift->end_time);

        if ($start === null || $end === null) {
            return self::DEFAULT_WORK_MINUTES_PER_DAY;
        }

        $total = (int) $start->diffInMinutes($end, false);

        return max(0, $total);
    }

    // ══════════════════════════════════════════════════════════════════════
    // PRIVATE: calculation helpers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Compute columns when an approved REMOTE_WORK request exists for the day.
     *
     * The request's start_date / end_date supply the remote-work time window,
     * which is merged with any office entry/exit to drive delay, early_leave
     * and overtime — the same way local clock data does. Coverage from
     * paid_leave / mission still absorbs delay and early_leave; remote_work
     * is NOT coverage (it's real work, just from home).
     *
     * @return array{worked:int, delay:int, early_leave:int, overtime:int, auto_overtime:int, mission:int, remote_work:int, is_friday:bool, is_holiday:bool}
     */
    private function computeMergedRemoteColumns(
        AttendanceLog $log,
        WorkShift $workShift,
        PersonnelRequest $remoteRequest,
        int $paidLeave,
        int $mission,
        int $approvedOvertimeInput,
        bool $isFriday,
        bool $isHoliday,
    ): array {
        $shiftMinutes = $this->shiftWorkMinutes($workShift);
        $autoOvertimeCap = max(0, (int) ($workShift->max_auto_overtime ?? 0));
        $float = max(0, (int) ($workShift->float ?? 0));

        $shiftStart = $this->parseTime($workShift->start_time);
        $shiftEnd = $this->parseTime($workShift->end_time);

        $officeEntry = $log->entry_time !== null ? $this->parseTime($log->entry_time) : null;
        $officeExit = $log->exit_time !== null ? $this->parseTime($log->exit_time) : null;
        $officeRaw = ($officeEntry !== null && $officeExit !== null)
            ? max(0, (int) $officeEntry->diffInMinutes($officeExit, false))
            : 0;

        $remoteStart = $this->parseTime(Carbon::parse($remoteRequest->start_date)->format('H:i:s'));
        $remoteEnd = $this->parseTime(Carbon::parse($remoteRequest->end_date)->format('H:i:s'));
        $remoteMinutes = ($remoteStart !== null && $remoteEnd !== null)
            ? max(0, (int) $remoteStart->diffInMinutes($remoteEnd, false))
            : 0;

        $starts = array_values(array_filter([$officeEntry, $remoteStart]));
        $ends = array_values(array_filter([$officeExit, $remoteEnd]));
        $mergedStart = empty($starts) ? null : min($starts);
        $mergedEnd = empty($ends) ? null : max($ends);

        $actualShiftStart = $shiftStart;
        $actualShiftEnd = $shiftEnd;
        if ($mergedStart !== null && $shiftStart !== null && $float > 0 && $mergedStart->greaterThan($shiftStart)) {
            $offset = min($float, $shiftStart->diffInMinutes($mergedStart));
            $actualShiftStart = $shiftStart->copy()->addMinutes($offset);
            $actualShiftEnd = $shiftEnd->copy()->addMinutes($offset);
        }

        $windowOverlapRaw = 0;
        $windowOverlapInShift = 0;
        if ($officeEntry !== null && $officeExit !== null && $remoteStart !== null && $remoteEnd !== null) {
            $intersectStart = $officeEntry->greaterThan($remoteStart) ? $officeEntry : $remoteStart;
            $intersectEnd = $officeExit->lessThan($remoteEnd) ? $officeExit : $remoteEnd;
            $windowOverlapRaw = max(0, (int) $intersectStart->diffInMinutes($intersectEnd, false));
            if ($windowOverlapRaw > 0) {
                $windowOverlapInShift = $this->overlapMinutes($intersectStart, $intersectEnd, $actualShiftStart, $actualShiftEnd);
            }
        }

        $effectiveInShift = 0;
        if ($officeEntry !== null && $officeExit !== null) {
            $effectiveInShift += $this->overlapMinutes($officeEntry, $officeExit, $actualShiftStart, $actualShiftEnd);
        }
        if ($remoteStart !== null && $remoteEnd !== null) {
            $effectiveInShift += $this->overlapMinutes($remoteStart, $remoteEnd, $actualShiftStart, $actualShiftEnd);
        }
        $effectiveInShift = min($shiftMinutes, $effectiveInShift - $windowOverlapInShift);

        $delayTime = 0;
        $overtimeTime = 0;
        if ($mergedStart !== null && $mergedEnd !== null) {
            $delayTime = max(0, (int) $actualShiftStart->diffInMinutes($mergedStart, false));
            $earlyArrival = max(0, (int) $mergedStart->diffInMinutes($actualShiftStart, false));
            $lateExit = max(0, (int) $actualShiftEnd->diffInMinutes($mergedEnd, false));
            $overtimeTime = $earlyArrival + $lateExit;
        }

        // Missing shift time, excluding the delay portion (delay is reported
        // separately). Remaining gap becomes early_leave.
        $earlyLeaveTime = max(0, $shiftMinutes - $effectiveInShift - $delayTime);

        $coverage = min($shiftMinutes, $paidLeave + $mission);
        $netDelay = max(0, $delayTime - $coverage);
        $remainingCoverage = max(0, $coverage - $delayTime);
        $netEarlyLeave = max(0, $earlyLeaveTime - $remainingCoverage);

        $approvedOvertime = min($approvedOvertimeInput, $overtimeTime);
        $remainingOvertime = max(0, $overtimeTime - $approvedOvertime);
        $autoOvertime = min($remainingOvertime, $autoOvertimeCap);

        return [
            'worked' => $officeRaw + $remoteMinutes - $windowOverlapRaw,
            'delay' => $netDelay,
            'early_leave' => $netEarlyLeave,
            'overtime' => $approvedOvertime,
            'auto_overtime' => $autoOvertime,
            'mission' => $mission,
            'remote_work' => $remoteMinutes,
            'is_friday' => $isFriday,
            'is_holiday' => $isHoliday,
        ];
    }

    /**
     * Minutes of approved hourly leave / mission whose time window overlaps the employee's clocked presence window.
     *
     * These minutes were spent on leave/mission "while the clock was running", so the raw clocked duration counts them as work.
     * They must be excluded from `worked` and must not be reused as coverage to absorb delay / early_leave.
     *
     * @param  Collection<int,PersonnelRequest>  $requests
     */
    private function midShiftCoverageMinutes(AttendanceLog $log, Collection $requests): int
    {
        if ($log->entry_time === null || $log->exit_time === null || $requests->isEmpty()) {
            return 0;
        }

        $entry = $this->parseTime($log->entry_time);
        $exit = $this->parseTime($log->exit_time);

        if ($entry === null || $exit === null) {
            return 0;
        }

        $overlap = 0;
        foreach ($requests as $request) {
            $start = $this->parseTime(Carbon::parse($request->start_date)->format('H:i:s'));
            $end = $this->parseTime(Carbon::parse($request->end_date)->format('H:i:s'));

            if ($start === null || $end === null) {
                continue;
            }

            $overlap += $this->overlapMinutes($start, $end, $entry, $exit);
        }

        return max(0, $overlap);
    }

    private function parseTime(string $time): ?Carbon
    {
        return Carbon::createFromFormat('H:i:s', $time) ?: Carbon::createFromFormat('H:i', $time) ?: null;
    }

    private function overlapMinutes(Carbon $start, Carbon $end, Carbon $shiftStart, Carbon $shiftEnd): int
    {
        $effStart = $start->greaterThan($shiftStart) ? $start : $shiftStart;
        $effEnd = $end->lessThan($shiftEnd) ? $end : $shiftEnd;

        return max(0, (int) $effStart->diffInMinutes($effEnd, false));
    }

    /**
     * Raw worked minutes = (exit − entry) − break, with no leave/mission logic.
     */
    private function rawWorkedMinutes(AttendanceLog $log, ?WorkShift $workShift): int
    {
        if ($log->entry_time === null || $log->exit_time === null) {
            return 0;
        }

        $entry = Carbon::createFromFormat('H:i:s', $log->entry_time) ?? Carbon::createFromFormat('H:i', $log->entry_time);
        $exit = Carbon::createFromFormat('H:i:s', $log->exit_time) ?? Carbon::createFromFormat('H:i', $log->exit_time);

        if ($entry === null || $exit === null) {
            return 0;
        }

        // $breakMin = max(0, (int) ($workShift?->break ?? 0));
        $rawMin = (int) $entry->diffInMinutes($exit, false);

        return max(0, $rawMin); // - $breakMin);
    }

    /**
     * Compute delay, early_leave, and raw overtime relative to the
     * ORIGINAL shift boundaries (float grace window applied).
     *
     * @return array{delay: int, early_leave: int, overtime: int}
     */
    private function shiftDelayEarlyLeave(AttendanceLog $log, ?WorkShift $workShift): array
    {
        $empty = ['delay' => 0, 'early_leave' => 0, 'overtime' => 0];

        if ($workShift === null || $log->entry_time === null || $log->exit_time === null) {
            return $empty;
        }

        $entry = Carbon::createFromFormat('H:i:s', $log->entry_time) ?? Carbon::createFromFormat('H:i', $log->entry_time);
        $exit = Carbon::createFromFormat('H:i:s', $log->exit_time) ?? Carbon::createFromFormat('H:i', $log->exit_time);

        $shiftStart = Carbon::createFromFormat('H:i:s', $workShift->start_time) ?? Carbon::createFromFormat('H:i', $workShift->start_time);
        $shiftEnd = Carbon::createFromFormat('H:i:s', $workShift->end_time) ?? Carbon::createFromFormat('H:i', $workShift->end_time);

        if ($entry === null || $exit === null || $shiftStart === null || $shiftEnd === null) {
            return $empty;
        }

        $float = max(0, (int) ($workShift->float ?? 0));
        $floatCutoff = $shiftStart->copy()->addMinutes($float);
        $lateMinutes = (int) $floatCutoff->diffInMinutes($entry, false);

        // Minutes employee arrived before shift start (positive = early arrival). These count as overtime.
        $earlyArrivalMinutes = 0;

        if ($lateMinutes <= 0) {
            // Within or before the grace window.
            $rawOffset = (int) $shiftStart->diffInMinutes($entry, false);
            $earlyArrivalMinutes = max(0, -$rawOffset); // > 0 only when arrived before shift start
            $offset = max(0, $rawOffset);               // > 0 only when arrived after start but within float
            $adjustedEnd = $shiftEnd->copy()->addMinutes($offset);
            $arrivalDelay = 0;
        } else {
            $arrivalDelay = $lateMinutes;
            $adjustedEnd = $shiftEnd->copy()->addMinutes($float);
        }

        $exitOffset = (int) $adjustedEnd->diffInMinutes($exit, false);

        return [
            'delay' => $arrivalDelay,
            'early_leave' => max(0, -$exitOffset),
            'overtime' => max(0, $exitOffset) + $earlyArrivalMinutes,
        ];
    }

    /**
     * Find (or create) an AttendanceLog for the given employee/date and apply
     * a signed delta to the specified field.
     * Returns the log (or null if nothing was changed).
     */
    private function applyDeltaToLog(int $employeeId, int $companyId, Carbon $date, string $field, int $minutes): ?AttendanceLog
    {
        $log = AttendanceLog::where('employee_id', $employeeId)
            ->where('log_date', $date->toDateString())
            ->first();

        if ($log === null) {
            if ($minutes < 0) {
                return null; // nothing to subtract
            }
            $log = AttendanceLog::create([
                'employee_id' => $employeeId,
                'company_id' => $companyId,
                'log_date' => $date->toDateString(),
            ]);
        }

        $log->update([
            $field => max(0, (int) $log->{$field} + $minutes),
        ]);

        return $log->fresh();
    }
}
