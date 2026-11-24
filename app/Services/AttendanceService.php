<?php

namespace App\Services;

use App\Enums\PersonnelRequestType;
use App\Enums\ThursdayStatus;
use App\Models\Activity;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\MonthlyAttendance;
use App\Models\PersonnelRequest;
use App\Models\PublicHoliday;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
        app(FiscalYearMutationGuard::class)->ensureOpen($log);

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

        // The values stored on an attendance log are a cache of approved personnel requests.
        // A log may have been imported before a request was approved (or its cached values may otherwise be stale), so manual recalculation must rebuild those request-backed values first.
        // Keep manually entered values when there are no approved requests of the corresponding type.
        $approvedRequests = $employee?->personnelRequests()->approved()->coveringDate($logDate->toDateString())->get() ?? collect();
        $requestMinutes = $this->approvedRequestMinutesForDate($approvedRequests, $logDate, $workShift);
        foreach (['paid_leave', 'unpaid_leave', 'mission', 'overtime', 'remote_work'] as $field) {
            if ($requestMinutes[$field]['has_request']) {
                $log->setAttribute($field, $requestMinutes[$field]['minutes']);
            }
        }

        $midShiftCoverage = $this->midShiftCoverageMinutes($log, $hourlyCoverageRequests);

        $columns = $this->computeLogColumns($log, $workShift, $isFriday, $isHoliday, $isThursday, $remoteRequest, $midShiftCoverage, $hourlyCoverageRequests, $approvedRequests);

        $log->update($columns);

        return $log->fresh();
    }

    public function approvedRequestMinutesForDate(Collection $requests, Carbon $date, ?WorkShift $workShift): array
    {
        $result = array_fill_keys(['paid_leave', 'unpaid_leave', 'mission', 'overtime', 'remote_work'], ['minutes' => 0, 'has_request' => false]);
        foreach ($requests as $request) {
            $type = $request->request_type;
            $field = match ($type) {
                PersonnelRequestType::LEAVE_HOURLY, PersonnelRequestType::LEAVE_DAILY, PersonnelRequestType::SICK_LEAVE => 'paid_leave',
                PersonnelRequestType::LEAVE_WITHOUT_PAY, PersonnelRequestType::LEAVE_WITHOUT_PAY_HOURLY => 'unpaid_leave',
                PersonnelRequestType::MISSION_HOURLY, PersonnelRequestType::MISSION_DAILY => 'mission',
                PersonnelRequestType::OVERTIME_ORDER => 'overtime',
                PersonnelRequestType::REMOTE_WORK => 'remote_work',
                default => null,
            };
            if ($field === null) {
                continue;
            }
            $result[$field]['has_request'] = true;
            $isDaily = in_array($type, [PersonnelRequestType::LEAVE_DAILY, PersonnelRequestType::SICK_LEAVE, PersonnelRequestType::LEAVE_WITHOUT_PAY, PersonnelRequestType::MISSION_DAILY], true);
            $minutes = $isDaily ? $this->shiftWorkMinutesForDate($workShift, $date) : max(0, (int) $request->start_date->diffInMinutes($request->end_date));
            $result[$field]['minutes'] += $minutes;
        }

        return $result;
    }

    /**
     * Compute derived attendance columns without persisting.
     *
     * This method is called from TWO places, and each one gathers the inputs on its own:
     *   • AttendanceService::recalculateLog()      — computes and stores the columns
     *   • AttendanceLogController::show()          — the "Calculation Check" table compares
     *                                                these values with the stored ones
     * Both places MUST pass exactly the same inputs, including any pre-processing of the log
     * (e.g. request-backed paid_leave/mission/... set before the call). When you change one of
     * them, change the other too; otherwise the check table shows false mismatches.
     * AttendanceServiceCalculationTest::test_calculation_check_table_matches_stored_values_after_recalculate
     * guards this.
     *
     * All parameters are required on purpose: when you add a parameter, both call sites break
     * until they are updated. Do not give new parameters a default value.
     *
     * Rules:
     * - worked = actual clocked minutes only
     * - paid_leave + mission + remote_work are coverage minutes, not worked minutes
     * - coverage absorbs delay and early_leave
     * - total coverage is capped by shift minutes
     *
     * @param  Collection  $approvedRequests  Approved requests covering the log date; used to split off-day minutes by mission/overtime window.
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
    public function computeLogColumns(AttendanceLog $log, ?WorkShift $workShift, bool $isFriday, bool $isHoliday, bool $isThursday, ?PersonnelRequest $remoteRequest, int $midShiftCoverage, ?Collection $hourlyCoverageRequests, Collection $approvedRequests): array
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
            // Every presence minute is paid once: mission > approved overtime > holiday work.
            $split = $this->splitOffDayMinutes($log, $approvedRequests, $rawWorked, $remoteWork, $mission, $approvedOvertimeInput);

            return [
                // Like mid-shift coverage on a normal day, minutes covered by a mission are not worked minutes.
                'worked' => max(0, $rawWorked + $remoteWork - $split['mission_overlap']),
                'delay' => 0,
                'early_leave' => 0,
                'overtime' => $split['overtime'],
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
        // Track explicit edge coverage separately. For coverage that starts at the shift boundary,
        // its latest end determines how much of the entry gap is still eligible for float.
        $explicitDelayCoverage = 0;
        $explicitEarlyLeaveCoverage = 0;
        $entryCoverageEnd = null;
        $entryCoverageStart = null;
        $shiftStartCoverageOverlap = 0;
        $outsideShiftCoverage = 0;

        if ($hourlyCoverageRequests !== null) {
            $entryTime = $log->entry_time ? Carbon::parse($log->entry_time)->format('H:i:s') : null;
            $exitTime = $log->exit_time ? Carbon::parse($log->exit_time)->format('H:i:s') : null;
            $shiftStartTime = $workShift?->start_time ? Carbon::parse($workShift->start_time)->format('H:i:s') : null;

            foreach ($hourlyCoverageRequests as $req) {
                $reqStart = Carbon::parse($req->start_date)->format('H:i:s');
                $reqEnd = Carbon::parse($req->end_date)->format('H:i:s');

                // Leave minutes before shift start carry no delay to absorb and must not become overtime.
                if ($shiftStartTime && $reqStart < $shiftStartTime) {
                    $outsideShiftCoverage += (int) Carbon::parse($reqStart)->diffInMinutes(Carbon::parse(min($reqEnd, $shiftStartTime)));
                }

                if ($shiftStartTime && $reqStart <= $shiftStartTime && $reqEnd > $shiftStartTime) {
                    $entryCoverageEnd = $entryCoverageEnd === null ? $reqEnd : max($entryCoverageEnd, $reqEnd);

                    // Physical attendance before start-edge coverage ends is early arrival, not mid-shift leave.
                    // Preserve this overlap so it can contribute to automatic overtime.
                    if ($entryTime && $exitTime) {
                        $overlapStart = max($reqStart, $entryTime);
                        $overlapEnd = min($reqEnd, $exitTime);
                        if ($overlapStart < $overlapEnd) {
                            $shiftStartCoverageOverlap += Carbon::parse($overlapStart)->diffInMinutes(Carbon::parse($overlapEnd));
                        }
                    }
                }

                // A leave window may begin inside the float period and end at check-in.
                // In that case, the gap before the leave consumes float; the leave itself must not be converted into automatic overtime.
                if ($shiftStartTime && $entryTime && $reqStart > $shiftStartTime && $reqStart < $entryTime && $reqEnd >= $entryTime) {
                    $uncoveredBeforeCoverage = Carbon::parse($shiftStartTime)->diffInMinutes(Carbon::parse($reqStart));
                    if ($uncoveredBeforeCoverage <= (int) ($workShift?->float ?? 0)) {
                        $entryCoverageStart = $entryCoverageStart === null ? $reqStart : min($entryCoverageStart, $reqStart);
                    }
                }

                // Leave occurring prior to physical check-in covers morning delays
                // Only the part inside the shift counts: leave before shift start cannot absorb delay nor become overtime.
                if ($entryTime) {
                    $start = $shiftStartTime ? max($reqStart, $shiftStartTime) : $reqStart;
                    $end = min($reqEnd, $entryTime);
                    if ($start < $end) {
                        $explicitDelayCoverage += (int) Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
                    }
                }

                // Leave occurring after physical check-out covers early leaves
                if ($exitTime) {
                    $start = max($reqStart, $exitTime);
                    $end = $reqEnd;
                    if ($start < $end) {
                        $explicitEarlyLeaveCoverage += (int) Carbon::parse($start)->diffInMinutes(Carbon::parse($end));
                    }
                }
            }
        }

        $bounds = $this->shiftDelayEarlyLeave($log, $workShift, $entryCoverageEnd, $entryCoverageStart);

        // Mid-shift coverage is removed from worked and cannot absorb edge penalties. Restore only the overlap
        // belonging to start-edge coverage because arriving before that coverage ends counts as automatic overtime.
        $totalCoverage = $paidLeave + $unpaidLeave + $mission;
        $midShift = max(0, min($midShiftCoverage, $totalCoverage));
        $edgeCoverage = $totalCoverage - $midShift;

        $coveredMinutes = min($shiftMinutes, $edgeCoverage + min($shiftStartCoverageOverlap, $midShift) + min($remoteWork, $shiftMinutes));

        // Ensure explicit coverage mappings don't exceed logically allowed boundaries
        $explicitDelayCoverage = min($explicitDelayCoverage, $coveredMinutes);
        $explicitEarlyLeaveCoverage = min($explicitEarlyLeaveCoverage, max(0, $coveredMinutes - $explicitDelayCoverage));

        // Any coverage originating from DB logs strictly missing request records (legacy or manual inputs)
        $blindCoverage = max(0, $coveredMinutes - $explicitDelayCoverage - $explicitEarlyLeaveCoverage - $outsideShiftCoverage);

        $netDelay = max(0, $bounds['delay'] - $explicitDelayCoverage);
        $unusedExplicitDelay = max(0, $explicitDelayCoverage - $bounds['delay']);

        if ($entryCoverageStart !== null) {
            $unusedExplicitDelay = 0;
        }

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
        $employee = Employee::findOrFail($employeeId);
        app(FiscalYearMutationGuard::class)->ensureOpen($employee);
        $fiscalYearId = getActiveFiscalYear();
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
                'fiscal_year_id' => $fiscalYearId,
                'employee_id' => $employeeId,
                'year' => $jalaliYear,
                'month' => $jalaliMonth,
            ],
            array_merge($totals, [
                'fiscal_year_id' => $fiscalYearId,
                'employee_id' => $employeeId,
                'year' => $jalaliYear,
                'month' => $jalaliMonth,
                'start_date' => $startDate->toDateString(),
                'duration' => $durationDays,
            ])
        );

        app(ActivityLogService::class)->updateModels(
            AttendanceLog::where('employee_id', $employeeId)
                ->whereBetween('log_date', [$startDate->toDateString(), $endDate->toDateString()]),
            ['monthly_attendance_id' => $attendance->id]
        );

        return $attendance;
    }

    /**
     * Apply the paid-leave accrual or delta for a stored monthly attendance record.
     */
    public function applyMonthlyAttendanceLeaveBalance(MonthlyAttendance $monthlyAttendance, ?MonthlyAttendance $existing, ?Employee $employee = null): void
    {
        $employee ??= $monthlyAttendance->employee()->with('workShift')->first();

        if ($employee === null) {
            return;
        }

        if ($existing === null) {
            $this->ensurePrepaidLeaveReconciled($employee);

            [$monthStart, $monthEnd] = $this->paidLeaveMonthBounds($monthlyAttendance->year, $monthlyAttendance->month);
            $contractEnd = $employee->contract_end_date;
            $lastEligibleDay = $contractEnd !== null && $contractEnd->betweenIncluded($monthStart, $monthEnd)
                ? $contractEnd
                : $monthEnd;

            if ($lastEligibleDay->gte(today())) {
                throw ValidationException::withMessages([
                    'start_date' => __('Monthly attendance can only be posted after the last eligible day of the month.'),
                ]);
            }

            $earnedPaidLeave = $this->paidLeaveForMonth($employee, $monthStart, $monthEnd);
            $monthlyAttendance->earned_paid_leave = $earnedPaidLeave;
            $monthlyAttendance->save();

            $employee->leave_remain = $employee->leave_remain + $earnedPaidLeave - $monthlyAttendance->paid_leave;
            $employee->save();

            return;
        }

        $this->adjustLeaveBalanceForPaidLeaveChange($employee, (int) $existing->paid_leave, (int) $monthlyAttendance->paid_leave);
    }

    /**
     * Revert the paid-leave accrual and used leave when a monthly attendance record is deleted.
     */
    public function revertMonthlyAttendanceLeaveBalance(MonthlyAttendance $monthlyAttendance, Employee $employee): void
    {
        $this->ensurePrepaidLeaveReconciled($employee);

        $earnedPaidLeave = $monthlyAttendance->earned_paid_leave ?? $this->originalLegacyPaidLeave($monthlyAttendance);

        if ($earnedPaidLeave === null) {
            throw ValidationException::withMessages([
                'monthly_attendance' => __('This monthly attendance cannot be deleted because its original paid leave credit is unknown.'),
            ]);
        }

        $balance = $employee->leave_remain + $monthlyAttendance->paid_leave - $earnedPaidLeave;
        if ($balance < -32768 || $balance > 32767) {
            throw ValidationException::withMessages([
                'monthly_attendance' => __('The resulting remaining paid leave is outside the supported range.'),
            ]);
        }

        $employee->leave_remain = $balance;
        $employee->save();
    }

    private function originalLegacyPaidLeave(MonthlyAttendance $attendance): ?int
    {
        $postings = Activity::query()
            ->where('source', 'request')
            ->where('action', 'post')
            ->whereIn('details->route', ['attendance.monthly-attendances.store', 'attendance.monthly-attendances.bulk-store'])
            ->whereJsonContains('details->fiscal_year_ids', (int) $attendance->fiscal_year_id)
            ->whereJsonContains('details->model_types', MonthlyAttendance::class)
            ->lazyByIdDesc();

        // Recalculation can replace the database row. Match the original posting by employee and period.
        foreach ($postings as $posting) {
            $changes = collect($posting->details?->get('models', []));
            $originalPosting = $changes->first(fn (array $change) => $change['model_type'] === MonthlyAttendance::class
                && $change['event'] === 'created'
                && (int) ($change['attributes']['employee_id'] ?? 0) === (int) $attendance->employee_id
                && (int) $change['fiscal_year_id'] === (int) $attendance->fiscal_year_id
                && (int) ($change['attributes']['year'] ?? 0) === $attendance->year
                && (int) ($change['attributes']['month'] ?? 0) === $attendance->month);

            if ($originalPosting === null) {
                continue;
            }

            $used = $originalPosting['attributes']['paid_leave'] ?? null;
            if ($used === null || isset($originalPosting['attributes']['earned_paid_leave'])) {
                return null;
            }

            $employeeChange = $changes->first(fn (array $change) => $change['model_type'] === Employee::class
                && (int) $change['model_id'] === (int) $attendance->employee_id);
            $before = $employeeChange['old']['leave_remain'] ?? null;
            $after = $employeeChange['attributes']['leave_remain'] ?? null;
            if (($before === null) !== ($after === null)) {
                return null;
            }

            // The legacy posting added the original credit and subtracted leave used in the same request.
            $minutes = (int) $after - (int) $before + (int) $used;

            return $minutes >= 0 && $minutes <= 65535 ? $minutes : null;
        }

        return null;
    }

    private function ensurePrepaidLeaveReconciled(Employee $employee): void
    {
        if ($employee->prepaid_leave_reconciliation_required) {
            throw ValidationException::withMessages([
                'monthly_attendance' => __('Confirm the corrected prepaid leave balance in the employee record for :name.', [
                    'name' => $employee->first_name.' '.$employee->last_name,
                ]),
            ]);
        }
    }

    /**
     * Adjust an employee's leave balance when a monthly attendance's paid_leave changes.
     */
    public function adjustLeaveBalanceForPaidLeaveChange(Employee $employee, int $oldPaidLeave, int $newPaidLeave): void
    {
        $diffMonthPaidLeave = $newPaidLeave - $oldPaidLeave;

        if ($diffMonthPaidLeave !== 0) {
            $employee->leave_remain -= $diffMonthPaidLeave;
            $employee->save();
        }
    }

    /**
     * Compute the employee's remaining paid leave including the current Jalali month's quota when applicable.
     */
    public function remainingPaidLeaveIncludingCurrentMonth(Employee $employee, ?FiscalYear $fiscalYear = null): int
    {
        $date = now()->copy()->startOfDay();
        $balance = (int) $employee->leave_remain;

        if (! $employee->is_active || $employee->prepaid_leave_reconciliation_required) {
            return $balance;
        }

        if ($fiscalYear !== null && $fiscalYear->isClosed()) {
            return $balance;
        }

        [$jalaliYear, $jalaliMonth] = gregorian_to_jalali($date->year, $date->month, $date->day);

        if ($fiscalYear !== null && $fiscalYear->year !== null && (int) $fiscalYear->year !== (int) $jalaliYear) {
            return $balance;
        }

        if ($employee->contract_end_date !== null && $employee->contract_end_date->lt($date)) {
            return $balance;
        }

        $currentMonthCalculated = $employee->monthlyAttendances()->where('year', $jalaliYear)->where('month', $jalaliMonth)->exists();

        if ($currentMonthCalculated) {
            return $balance;
        }

        [$monthStart, $monthEnd] = $this->paidLeaveMonthBounds($jalaliYear, $jalaliMonth);

        return $balance + $this->paidLeaveForMonth($employee, $monthStart, $monthEnd);
    }

    /** @return array{Carbon, Carbon} */
    private function paidLeaveMonthBounds(int $year, int $month): array
    {
        $start = Carbon::parse(jalali_to_gregorian($year, $month, 1, '/'));
        $nextYear = $month === 12 ? $year + 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $end = Carbon::parse(jalali_to_gregorian($nextYear, $nextMonth, 1, '/'))->subDay();

        return [$start, $end];
    }

    private function paidLeaveForMonth(Employee $employee, Carbon $monthStart, Carbon $monthEnd): int
    {
        $contractStart = $employee->contract_start_date;
        $contractEnd = $employee->contract_end_date;
        $monthlyQuota = (int) ($employee->workShift?->paid_leave ?? 0);

        if ($contractStart !== null && $contractStart->gt($monthEnd)) {
            return 0;
        }

        if ($contractEnd === null || $contractEnd->gt($monthEnd)) {
            return $monthlyQuota;
        }

        $firstCoveredDay = $contractStart !== null && $contractStart->gt($monthStart) ? $contractStart : $monthStart;

        if ($contractEnd->lt($firstCoveredDay)) {
            return 0;
        }

        $coveredDays = $firstCoveredDay->diffInDays($contractEnd) + 1;
        $monthDays = $monthStart->diffInDays($monthEnd) + 1;

        return (int) round($monthlyQuota * $coveredDays / $monthDays);
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

                // Mission and approved overtime minutes are paid by their own buckets (once per minute);
                // only the remaining worked minutes (worked excludes mission-covered presence) are paid with the Friday / holiday coefficient.
                $overtimeMin += (int) $log->overtime;
                if ($isFriday) {
                    $fridayMin += max(0, (int) $log->worked - (int) $log->overtime);
                } else {
                    $holidayMin += max(0, (int) $log->worked - (int) $log->overtime);
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
        app(FiscalYearMutationGuard::class)->ensureOpen($personnelRequest);

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
        $companyId = $personnelRequest->fiscal_year_id;
        $employeeId = $personnelRequest->employee_id;
        $delta = $subtract ? -1 : 1;

        $isDailyType = in_array($personnelRequest->request_type, [
            PersonnelRequestType::LEAVE_DAILY,
            PersonnelRequestType::SICK_LEAVE,
            PersonnelRequestType::LEAVE_WITHOUT_PAY,
            PersonnelRequestType::MISSION_DAILY,
        ], strict: true);

        if ($isDailyType) {
            $current = $start->copy()->startOfDay();
            $endDay = $end->copy()->startOfDay();

            while ($current->lte($endDay)) {
                $duration = $this->shiftWorkMinutesForDate($personnelRequest->employee->workShift, $current);
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

    /**
     * Return the assigned shift duration for a specific date.
     *
     * A half-day Thursday uses thursday_exit_time. Off-day mission requests still retain the normal shift duration so they can be reported and paid.
     */
    public function shiftWorkMinutesForDate(?WorkShift $workShift, Carbon|string $date): int
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        if ($date->dayOfWeek === Carbon::THURSDAY && $workShift?->thursday_status === ThursdayStatus::HALF_DAY && $workShift->thursday_exit_time) {
            $workShift = clone $workShift;
            $workShift->end_time = $workShift->thursday_exit_time;
        }

        return $this->shiftWorkMinutes($workShift);
    }

    /**
     * Return the assigned shift bounds for a dated daily personnel request.
     *
     * @return array{start:string, end:string}
     */
    public function shiftTimesForDate(?WorkShift $workShift, Carbon|string $date): array
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $start = $workShift?->start_time ?? self::DEFAULT_SHIFT_START;
        $end = $workShift?->end_time ?? self::DEFAULT_SHIFT_END;

        if ($date->dayOfWeek === Carbon::THURSDAY && $workShift?->thursday_status === ThursdayStatus::HALF_DAY && $workShift->thursday_exit_time) {
            $end = $workShift->thursday_exit_time;
        }

        return [
            'start' => Carbon::createFromTimeString($start)->format('H:i'),
            'end' => Carbon::createFromTimeString($end)->format('H:i'),
        ];
    }

    /** Reject hourly leave outside the employee's shift window.*/
    public function validateHourlyLeaveWithinShift(Employee $employee, PersonnelRequestType $requestType, Carbon|string $date, string $startTime, string $endTime): void
    {
        if (! in_array($requestType, [PersonnelRequestType::LEAVE_HOURLY, PersonnelRequestType::LEAVE_WITHOUT_PAY_HOURLY], true)) {
            return;
        }

        $workShift = $employee->workShift;
        $shiftTimes = $this->shiftTimesForDate($workShift, $date);
        $shiftStart = Carbon::createFromTimeString($shiftTimes['start']);
        $shiftEnd = Carbon::createFromTimeString($shiftTimes['end'])->addMinutes(max(0, (int) ($workShift?->float ?? 0)));
        $leaveStart = Carbon::createFromTimeString($startTime);
        $leaveEnd = Carbon::createFromTimeString($endTime);
        $message = __('Hourly leave must be within the employee\'s work shift, including float time.');
        $errors = [];

        if ($leaveStart->lessThan($shiftStart) || $leaveStart->greaterThan($shiftEnd)) {
            $errors['start_time'] = $message;
        }

        if ($leaveEnd->lessThan($shiftStart) || $leaveEnd->greaterThan($shiftEnd)) {
            $errors['end_time'] = $message;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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
    public function midShiftCoverageMinutes(AttendanceLog $log, Collection $requests): int
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

    /**
     * Split the presence of an off day into mission-covered and overtime-covered minutes.
     *
     * The split is per minute with priority mission > overtime > holiday work, so no minute is counted twice.
     * Mission minutes themselves stay in the `mission` column (paid once with the mission coefficient);
     * only their overlap with the presence is removed from the other buckets.
     *
     * @return array{overtime:int, mission_overlap:int}
     */
    private function splitOffDayMinutes(AttendanceLog $log, Collection $approvedRequests, int $rawWorked, int $remoteWork, int $mission, int $approvedOvertimeInput): array
    {
        $presenceMinutes = $rawWorked + $remoteWork;
        if ($presenceMinutes <= 0) {
            return ['overtime' => 0, 'mission_overlap' => 0];
        }

        $presence = [];
        if ($log->entry_time !== null && $log->exit_time !== null) {
            $start = $this->minuteOfDay((string) $log->entry_time);
            $end = $this->minuteOfDay((string) $log->exit_time);
            if ($end > $start) {
                $presence = [[$start, $end]];
            }
        }

        $missionWindows = [];
        $overtimeWindows = [];
        $hasDailyMission = false;
        foreach ($approvedRequests as $request) {
            $type = $request->request_type;
            if ($type === PersonnelRequestType::MISSION_DAILY) {
                $hasDailyMission = true;
            } elseif ($type === PersonnelRequestType::MISSION_HOURLY) {
                $missionWindows[] = [$this->minuteOfDay($request->start_date->format('H:i:s')), $this->minuteOfDay($request->end_date->format('H:i:s'))];
            } elseif ($type === PersonnelRequestType::OVERTIME_ORDER) {
                $overtimeWindows[] = [$this->minuteOfDay($request->start_date->format('H:i:s')), $this->minuteOfDay($request->end_date->format('H:i:s'))];
            }
        }

        // Mission minutes overlapping the presence.
        // A daily mission covers the whole day; mission minutes without any request window (manual values) are assumed to overlap the presence.
        if ($hasDailyMission || ($mission > 0 && $missionWindows === [])) {
            $missionOverlap = $hasDailyMission ? $presenceMinutes : min($mission, $presenceMinutes);
            $missionIntervals = $presence;
        } else {
            $missionIntervals = $this->intersectIntervals($presence, $missionWindows);
            $missionOverlap = min($presenceMinutes, $this->intervalsLength($missionIntervals));
        }

        // Approved overtime outside mission minutes, capped by the remaining presence.
        if ($overtimeWindows !== []) {
            $overtimeIntervals = $this->intersectIntervals($presence, $overtimeWindows);
            $overtimeOnly = $this->intervalsLength($overtimeIntervals) - $this->intervalsLength($this->intersectIntervals($overtimeIntervals, $missionIntervals));
        } else {
            $overtimeOnly = $approvedOvertimeInput;
        }

        return [
            'overtime' => max(0, min($overtimeOnly, $presenceMinutes - $missionOverlap)),
            'mission_overlap' => $missionOverlap,
        ];
    }

    private function minuteOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    /**
     * Intersect two lists of [start, end) minute intervals (each list is merged first).
     *
     * @param  array<int, array{0:int,1:int}>  $a
     * @param  array<int, array{0:int,1:int}>  $b
     * @return array<int, array{0:int,1:int}>
     */
    private function intersectIntervals(array $a, array $b): array
    {
        $a = $this->mergeIntervals($a);
        $b = $this->mergeIntervals($b);
        $result = [];
        foreach ($a as [$aStart, $aEnd]) {
            foreach ($b as [$bStart, $bEnd]) {
                $start = max($aStart, $bStart);
                $end = min($aEnd, $bEnd);
                if ($start < $end) {
                    $result[] = [$start, $end];
                }
            }
        }

        return $this->mergeIntervals($result);
    }

    /**
     * @param  array<int, array{0:int,1:int}>  $intervals
     * @return array<int, array{0:int,1:int}>
     */
    private function mergeIntervals(array $intervals): array
    {
        $intervals = array_values(array_filter($intervals, fn ($i) => $i[0] < $i[1]));
        usort($intervals, fn ($x, $y) => $x[0] <=> $y[0]);
        $merged = [];
        foreach ($intervals as [$start, $end]) {
            $last = count($merged) - 1;
            if ($last >= 0 && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /**
     * @param  array<int, array{0:int,1:int}>  $intervals
     */
    private function intervalsLength(array $intervals): int
    {
        return array_sum(array_map(fn ($i) => $i[1] - $i[0], $this->mergeIntervals($intervals)));
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
     * ORIGINAL shift boundaries, applying float only to an entry gap that
     * remains after approved start-edge coverage.
     *
     * When coverage reaches the physical entry, the shift end must not slide.
     * When coverage ends before entry, only that uncovered interval may slide
     * the shift end, up to the configured float limit.
     *
     * @return array{delay: int, early_leave: int, overtime: int}
     */
    private function shiftDelayEarlyLeave(AttendanceLog $log, ?WorkShift $workShift, ?string $entryCoverageEnd = null, ?string $entryCoverageStart = null): array
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

        $coverageEnd = $entryCoverageEnd !== null ? $this->parseTime($entryCoverageEnd) : null;
        $coverageStart = $entryCoverageStart !== null ? $this->parseTime($entryCoverageStart) : null;
        $uncoveredEntryGap = $coverageEnd !== null ? max(0, (int) $coverageEnd->diffInMinutes($entry, false)) : null;

        if ($coverageStart !== null) {
            $uncoveredBeforeCoverage = max(0, (int) $shiftStart->diffInMinutes($coverageStart, false));
            $arrivalDelay = max(0, $lateMinutes);

            // Treat the leave start as the effective check-in, including the exact float cutoff.
            // The same offset must extend the shift end so float is not counted as overtime.
            $endExtension = min($uncoveredBeforeCoverage, $float);
            $adjustedEnd = $shiftEnd->copy()->addMinutes($endExtension);
        } elseif ($coverageEnd !== null && $coverageEnd->greaterThanOrEqualTo($entry)) {
            // Coverage fills the complete entry gap. Keep the scheduled shift end so any late exit,
            // plus attendance that overlaps the end of coverage, remains automatic overtime.
            $arrivalDelay = max(0, (int) $shiftStart->diffInMinutes($entry, false));
            $adjustedEnd = $shiftEnd;
        } elseif ($uncoveredEntryGap !== null && $uncoveredEntryGap <= $float) {
            // Coverage ends before entry: consume float only for the uncovered gap instead of
            // shifting the end by the full float measured from the original shift start.
            $arrivalDelay = max(0, $lateMinutes);
            $adjustedEnd = $shiftEnd->copy()->addMinutes($uncoveredEntryGap);
        } elseif ($lateMinutes <= 0) {
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
    private function applyDeltaToLog(int $employeeId, int $fiscalYearId, Carbon $date, string $field, int $minutes): ?AttendanceLog
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
                'fiscal_year_id' => $fiscalYearId,
                'log_date' => $date->toDateString(),
            ]);
        }

        $log->update([
            $field => max(0, (int) $log->{$field} + $minutes),
        ]);

        return $log->fresh();
    }
}
