<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\PublicHoliday;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AttendanceServiceCalculationTest
 *
 * Verifies that AttendanceService uses each employee's assigned WorkShift
 * instead of a fixed 08:00-17:00 schedule when computing monthly totals.
 *
 * Period used across tests: 2025-03-01 → 2025-03-31 (31 calendar days).
 * 2025-03-01 is a Saturday. Fridays in March 2025: 7, 14, 21, 28 → 4 Fridays.
 * Non-Friday work days = 31 − 4 = 27.
 */
class AttendanceServiceCalculationTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    private Company $company;

    private WorkSite $workSite;

    /** @var Carbon Start of the test period (2025-03-01, Saturday) */
    private Carbon $startDate;

    private int $durationDays;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AttendanceService;

        $this->company = Company::factory()->create();
        $this->workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);

        // March 2025: 31 days, 4 Fridays (7,14,21,28), 27 non-Friday work days
        $this->startDate = Carbon::create(2025, 3, 1);
        $this->durationDays = 31;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeShift(array $overrides = []): WorkShift
    {
        return WorkShift::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'crosses_midnight' => false,
            'break' => 0,
            'float_before' => 0,
            'float_after' => 0,
            'is_active' => true,
        ], $overrides));
    }

    private function makeEmployee(?WorkShift $shift = null): Employee
    {
        return Employee::factory()->create([
            'company_id' => $this->company->id,
            'work_site_id' => $this->workSite->id,
            'work_shift_id' => $shift?->id,
        ]);
    }

    /**
     * Insert a work-day log using raw entry/exit times (no pre-computed `worked`).
     */
    private function insertLog(
        Employee $employee,
        string $date,
        string $entry,
        string $exit,
        array $extra = []
    ): AttendanceLog {
        return AttendanceLog::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'log_date' => $date,
            'entry_time' => $entry,
            'exit_time' => $exit,
            'worked' => 0,  // force calculation from entry/exit
            'mission' => 0,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
        ], $extra));
    }

    /**
     * Insert a log with a pre-computed `worked` value (minutes).
     */
    private function insertPrecomputedLog(
        Employee $employee,
        string $date,
        int $workedMinutes,
        array $extra = []
    ): AttendanceLog {
        return AttendanceLog::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'log_date' => $date,
            'entry_time' => null,
            'exit_time' => null,
            'worked' => $workedMinutes,
            'mission' => 0,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
        ], $extra));
    }

    // -----------------------------------------------------------------------
    // shiftWorkMinutes unit tests
    // -----------------------------------------------------------------------

    public function test_shift_work_minutes_returns_default_when_no_shift(): void
    {
        $minutes = $this->service->shiftWorkMinutes(null);

        $this->assertSame(AttendanceService::DEFAULT_WORK_MINUTES_PER_DAY, $minutes);
    }

    public function test_shift_work_minutes_standard_8h_shift(): void
    {
        $shift = $this->makeShift(['start_time' => '08:00:00', 'end_time' => '17:00:00', 'break' => 60]);
        // 9h window − 60 min break = 480 min
        $this->assertSame(480, $this->service->shiftWorkMinutes($shift));
    }

    public function test_shift_work_minutes_6h_shift_no_break(): void
    {
        $shift = $this->makeShift(['start_time' => '07:00:00', 'end_time' => '13:00:00', 'break' => 0]);
        // 6h × 60 = 360 min
        $this->assertSame(360, $this->service->shiftWorkMinutes($shift));
    }

    public function test_shift_work_minutes_crosses_midnight(): void
    {
        $shift = $this->makeShift([
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'crosses_midnight' => true,
            'break' => 30,
        ]);
        // 8h window − 30 min break = 450 min
        $this->assertSame(450, $this->service->shiftWorkMinutes($shift));
    }

    public function test_shift_work_minutes_with_no_break(): void
    {
        $shift = $this->makeShift(['start_time' => '09:00:00', 'end_time' => '18:00:00', 'break' => 0]);
        // Exactly 9 h = 540 min
        $this->assertSame(540, $this->service->shiftWorkMinutes($shift));
    }

    // -----------------------------------------------------------------------
    // Full calculateAndStore integration tests (DB-backed)
    // -----------------------------------------------------------------------

    /**
     * Scenario: employee works a 6-hour shift (07:00–13:00, no break).
     * 5 weekday logs, each 360 min → no overtime.
     * The remaining 22 non-Friday weekdays have no log → counted absent.
     */
    public function test_calculate_and_store_uses_employee_work_shift_6h(): void
    {
        $shift = $this->makeShift([
            'start_time' => '07:00:00',
            'end_time' => '13:00:00',
            'break' => 0,
        ]);
        $employee = $this->makeEmployee($shift);

        // Insert logs for Mon–Fri of the first week (2025-03-03 to 2025-03-07)
        // Skip Friday 2025-03-07 as it is a non-workday
        $presentDates = ['2025-03-03', '2025-03-04', '2025-03-05', '2025-03-06']; // 4 weekdays (Mon-Thu)
        foreach ($presentDates as $date) {
            $this->insertLog($employee, $date, '07:00:00', '13:00:00'); // 360 min raw, 0 break
        }

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        // 31 days − 4 Fridays = 27 work days
        $this->assertSame(27, $attendance->work_days);
        // 4 logs present
        $this->assertSame(4, $attendance->present_days);
        // 27 − 4 = 23 absent
        $this->assertSame(23, $attendance->absent_days);
        // No overtime: 360 min worked == 360 min shift
        $this->assertSame(0, $attendance->overtime);
    }

    /**
     * Scenario: employee works a 6-hour shift (07:00–13:00, no break).
     * One day they stay 90 extra minutes → 90 min overtime.
     */
    public function test_overtime_calculated_relative_to_employee_shift_duration(): void
    {
        $shift = $this->makeShift([
            'start_time' => '07:00:00',
            'end_time' => '13:00:00',
            'break' => 0,
        ]);
        $employee = $this->makeEmployee($shift);

        // Normal day: exactly 360 min (shift length)
        $this->insertPrecomputedLog($employee, '2025-03-03', 360);
        // Overtime day: 450 min = 360 + 90 overtime
        $this->insertPrecomputedLog($employee, '2025-03-04', 450);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        $this->assertSame(2, $attendance->present_days);
        $this->assertSame(90, $attendance->overtime);
    }

    /**
     * Scenario: standard 8-hour shift (09:00–18:00, no break = 540 min net).
     * One log with 480 min (pre-computed) should produce NO overtime because
     * the shift is 540 min. Verifies that using the employee's shift, not the
     * old hardcoded 480, is the baseline.
     */
    public function test_no_overtime_for_480_min_on_540_min_shift(): void
    {
        $shift = $this->makeShift([
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'break' => 0, // net = 540 min
        ]);
        $employee = $this->makeEmployee($shift);

        $this->insertPrecomputedLog($employee, '2025-03-03', 480);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        // 480 < 540 shift → no overtime
        $this->assertSame(0, $attendance->overtime);
        $this->assertSame(1, $attendance->present_days);
    }

    /**
     * Scenario: a public holiday falls on a weekday and the employee works on it.
     * The worked minutes should appear in `holiday`, not counted as overtime.
     */
    public function test_holiday_minutes_are_credited_to_holiday_column(): void
    {
        $shift = $this->makeShift([
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 0, // 480 min
        ]);
        $employee = $this->makeEmployee($shift);

        // Wednesday 2025-03-05 is declared a public holiday
        PublicHoliday::factory()->create([
            'company_id' => $this->company->id,
            'date' => '2025-03-05',
        ]);

        // Employee shows up on the holiday: 480 min (raw entry/exit, shift break=0)
        $this->insertLog($employee, '2025-03-05', '08:00:00', '16:00:00');

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        // Holiday should carry the 480 worked minutes
        $this->assertSame(480, $attendance->holiday);
        // No regular workday minutes for the holiday, so present/absent reflect it as non-workday
        $this->assertSame(0, $attendance->present_days);
        $this->assertSame(0, $attendance->overtime);
    }

    /**
     * Scenario: employee works on Friday.
     * Friday minutes go to the `friday` column, not overtime.
     */
    public function test_friday_minutes_are_credited_to_friday_column(): void
    {
        $shift = $this->makeShift([
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 0,
        ]);
        $employee = $this->makeEmployee($shift);

        // First Friday of March 2025 is 2025-03-07
        $this->insertPrecomputedLog($employee, '2025-03-07', 480);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        $this->assertSame(480, $attendance->friday);
        $this->assertSame(0, $attendance->present_days);
    }

    /**
     * Scenario: mission day — employee has a mission log (no entry/exit).
     * Should be counted as present + mission, not absent.
     */
    public function test_mission_day_counted_as_present(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        AttendanceLog::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'log_date' => '2025-03-03',
            'entry_time' => null,
            'exit_time' => null,
            'worked' => 0,
            'mission' => 480,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
        ]);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        $this->assertSame(1, $attendance->mission_days);
        $this->assertSame(1, $attendance->present_days);
    }

    /**
     * Scenario: two employees with different shifts in the same company.
     * Each should get their own overtime calculated with their own shift baseline.
     */
    public function test_two_employees_with_different_shifts_get_independent_calculations(): void
    {
        // Employee A: 6-hour shift (360 min)
        $shiftA = $this->makeShift(['start_time' => '07:00:00', 'end_time' => '13:00:00', 'break' => 0]);
        $employeeA = $this->makeEmployee($shiftA);

        // Employee B: 9-hour shift (540 min)
        $shiftB = $this->makeShift(['start_time' => '09:00:00', 'end_time' => '18:00:00', 'break' => 0]);
        $employeeB = $this->makeEmployee($shiftB);

        // Both work 420 minutes on the same day
        $this->insertPrecomputedLog($employeeA, '2025-03-03', 420);
        $this->insertPrecomputedLog($employeeB, '2025-03-03', 420);

        $attendanceA = $this->service->calculateAndStore(
            $employeeA->id, $this->startDate, $this->durationDays, 1404, 1
        );
        $attendanceB = $this->service->calculateAndStore(
            $employeeB->id, $this->startDate, $this->durationDays, 1404, 1
        );

        // A: 420 − 360 = 60 min overtime
        $this->assertSame(60, $attendanceA->overtime);
        // B: 420 < 540 → 0 overtime
        $this->assertSame(0, $attendanceB->overtime);
    }

    /**
     * Scenario: shift with a 60-minute break.
     * Raw clock-in/out span of 9h (540 min) minus 60 min break = 480 min net.
     * A log with pre-computed worked=540 should yield 60 min overtime relative
     * to the 480-min net shift.
     */
    public function test_shift_break_is_subtracted_from_expected_minutes(): void
    {
        $shift = $this->makeShift([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break' => 60, // net = 540 − 60 = 480 min
        ]);
        $employee = $this->makeEmployee($shift);

        // 540 pre-computed worked minutes (already net, e.g. from device that excludes break)
        $this->insertPrecomputedLog($employee, '2025-03-03', 540);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        // 540 − 480 (net shift) = 60 min overtime
        $this->assertSame(60, $attendance->overtime);
    }

    /**
     * Scenario: verify that attendance logs are linked to the monthly_attendance record.
     */
    public function test_logs_are_linked_to_monthly_attendance_record(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        $log1 = $this->insertPrecomputedLog($employee, '2025-03-03', 480);
        $log2 = $this->insertPrecomputedLog($employee, '2025-03-04', 480);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $log1->id,
            'monthly_attendance_id' => $attendance->id,
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'id' => $log2->id,
            'monthly_attendance_id' => $attendance->id,
        ]);
    }

    /**
     * Scenario: calling calculateAndStore twice for the same period updates the record
     * rather than creating a duplicate.
     */
    public function test_recalculate_updates_existing_record(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        // First calculation: 1 present day
        $this->insertPrecomputedLog($employee, '2025-03-03', 480);

        $first = $this->service->calculateAndStore(
            $employee->id, $this->startDate, $this->durationDays, 1404, 1
        );

        // Add another log and recalculate
        $this->insertPrecomputedLog($employee, '2025-03-04', 480);

        $second = $this->service->calculateAndStore(
            $employee->id, $this->startDate, $this->durationDays, 1404, 1
        );

        // Same DB record, updated values
        $this->assertSame($first->id, $second->id);
        $this->assertSame(2, $second->present_days);
        $this->assertSame(1, MonthlyAttendance::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('year', 1404)
            ->where('month', 1)
            ->count()
        );
    }
}
