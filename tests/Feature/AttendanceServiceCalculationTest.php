<?php

namespace Tests\Feature;

use App\Enums\PersonnelRequestType;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PersonnelRequest;
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
 * Tests complex attendance scenarios including problematic combinations:
 * - Friday + remote_work/mission/overtime
 * - Holiday + remote_work/mission
 * - Hourly leave + hourly mission conflicts
 * - Remote work (partial) + overtime
 * - Daily leave + daily mission conflicts
 *
 * Period: 2025-03-01 → 2025-03-31 (31 days, 4 Fridays: 7,14,21,28)
 */
class AttendanceServiceCalculationTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    private Company $company;

    private WorkSite $workSite;

    private Carbon $startDate;

    private int $durationDays;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AttendanceService;
        $this->company = Company::factory()->create();
        $this->workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);

        request()->cookies->set('active-company-id', $this->company->id);
        $this->withCookies(['active-company-id' => $this->company->id]);

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
            'break' => 60,
            'float' => 0,
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

    private function insertLog(Employee $employee, string $date, array $data = [], $recalculate = true): AttendanceLog
    {
        $log = AttendanceLog::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'log_date' => $date,
            'entry_time' => $data['entry_time'] ?? null,
            'exit_time' => $data['exit_time'] ?? null,
            'worked' => $data['worked'] ?? 0,
            'overtime' => $data['overtime'] ?? 0,
            'mission' => $data['mission'] ?? 0,
            'paid_leave' => $data['paid_leave'] ?? 0,
            'unpaid_leave' => $data['unpaid_leave'] ?? 0,
            'remote_work' => $data['remote_work'] ?? 0,
        ], $data));

        if ($recalculate) {
            return $this->service->recalculateLog($log);
        }

        return $log;
    }

    // -----------------------------------------------------------------------
    // Core shift calculation tests (kept minimal)
    // -----------------------------------------------------------------------

    public function test_shift_work_minutes_with_break(): void
    {
        $shift = $this->makeShift(['start_time' => '08:00:00', 'end_time' => '17:00:00', 'break' => 60]);
        $this->assertSame(540, $this->service->shiftWorkMinutes($shift));
    }

    public function test_two_employees_different_shifts_independent_calculations(): void
    {
        $shiftA = $this->makeShift(['start_time' => '07:00:00', 'end_time' => '13:00:00', 'break' => 0]);
        $shiftB = $this->makeShift(['start_time' => '09:00:00', 'end_time' => '18:00:00', 'break' => 0]);
        $employeeA = $this->makeEmployee($shiftA);
        $employeeB = $this->makeEmployee($shiftB);

        $this->insertLog($employeeA, '2025-03-03', [
            'entry_time' => '07:00:00',
            'exit_time' => '14:00:00',
            // 'auto_overtime' => 60,
        ]);
        $this->insertLog($employeeB, '2025-03-03', [
            'entry_time' => '09:00:00',
            'exit_time' => '16:00:00',
            // 'auto_overtime' => 0,
        ]);

        $attendanceA = $this->service->calculateAndStore($employeeA->id, $this->startDate, $this->durationDays, 1404, 1);
        $attendanceB = $this->service->calculateAndStore($employeeB->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(60, $attendanceA->auto_overtime); // 420 - 360
        $this->assertSame(0, $attendanceB->auto_overtime); // 420 < 540
    }

    // -----------------------------------------------------------------------
    // PROBLEMATIC SCENARIO 1: Friday + remote_work
    // -----------------------------------------------------------------------

    public function test_friday_with_remote_work_should_be_recorded(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        $friday = $this->startDate->next(Carbon::FRIDAY);
        $this->insertLog($employee, $friday->toDateString(), ['remote_work' => 480]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(480, $attendance->remote_work, 'Remote work on Friday should be recorded');
        $this->assertSame(480, $attendance->friday, 'Friday column should also reflect work');
    }

    public function test_auto_overtime_is_capped_by_work_shift_limit(): void
    {
        $shift = $this->makeShift([
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 0,
            'max_auto_overtime' => 120,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-03', [
            'entry_time' => '08:00:00',
            'exit_time' => '20:00:00',
        ]);

        $this->assertSame(0, $log->overtime);
        $this->assertSame(120, $log->auto_overtime);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        $this->assertSame(0, $attendance->overtime);
        $this->assertSame(120, $attendance->auto_overtime);
    }

    public function test_approved_and_auto_overtime_can_be_combined_on_same_day(): void
    {
        $shift = $this->makeShift([
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 0,
            'max_auto_overtime' => 120,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-03', [
            'entry_time' => '08:00:00',
            'exit_time' => '20:00:00',
            'overtime' => 60,
        ]);

        $this->assertSame(60, $log->overtime);
        $this->assertSame(120, $log->auto_overtime);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        $this->assertSame(60, $attendance->overtime);
        $this->assertSame(120, $attendance->auto_overtime);
    }

    public function test_remote_work_above_shift_generates_auto_overtime_when_enabled(): void
    {
        $shift = $this->makeShift([
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 0,
            'max_auto_overtime' => 120,
        ]);

        $employee = $this->makeEmployee($shift);

        // Shift time is 480 minutes, remote work is 600 minutes => 120 minutes auto overtime
        $log = $this->insertLog($employee, '2025-03-10', [
            'remote_work' => 600,
        ]);

        $this->assertSame(600, $log->remote_work);
        $this->assertSame(0, $log->overtime);
        $this->assertSame(120, $log->auto_overtime);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        $this->assertSame(600, $attendance->remote_work);
        $this->assertSame(0, $attendance->overtime);
        $this->assertSame(120, $attendance->auto_overtime);
    }

    public function test_overtime_above_approved_and_auto_cap_is_discarded(): void
    {
        $shift = $this->makeShift([
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 0,
            'max_auto_overtime' => 120,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-03', [
            'entry_time' => '08:00:00',
            'exit_time' => '21:00:00',
            'overtime' => 60,
        ]);

        $this->assertSame(60, $log->overtime);
        $this->assertSame(120, $log->auto_overtime);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        $this->assertSame(60, $attendance->overtime);
        $this->assertSame(120, $attendance->auto_overtime);
    }

    public function test_store_calculates_automatic_overtime_with_leave_coverage(): void
    {
        $shift = $this->makeShift([
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 0,
            'float' => 60,
            'max_auto_overtime' => 120,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '09:51:00',
            'exit_time' => '17:01:00',
            'paid_leave' => 90,
        ]);

        $this->assertSame(430, $log->worked);
        $this->assertSame(0, $log->delay);
        $this->assertSame(0, $log->early_leave);
        $this->assertSame(0, $log->overtime);
        $this->assertSame(40, $log->auto_overtime);
        $this->assertSame(90, $log->paid_leave);
    }

    /**
     * Scenario: verify that attendance logs are linked to the monthly_attendance record.
     */
    public function test_logs_are_linked_to_monthly_attendance_record(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        // Friday 2025-03-07
        $this->insertLog($employee, '2025-03-07', ['remote_work' => 480]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(480, $attendance->remote_work, 'Remote work on Friday should be recorded');
        $this->assertSame(480, $attendance->friday, 'Friday column should also reflect work');
    }

    public function test_friday_with_partial_remote_work(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        // Friday with 4 hours remote work
        $this->insertLog($employee, '2025-03-14', ['remote_work' => 240]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(240, $attendance->remote_work);
        $this->assertSame(240, $attendance->friday);
    }

    // -----------------------------------------------------------------------
    // PROBLEMATIC SCENARIO 2: Friday + mission
    // -----------------------------------------------------------------------

    public function test_friday_with_mission_should_be_recorded(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        // Friday 2025-03-21 with mission
        $this->insertLog($employee, '2025-03-21', ['mission' => 480]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(480, $attendance->mission, 'Mission on Friday should be recorded');
        $this->assertSame(480, $attendance->friday);
    }

    public function test_friday_with_mission_spanning_multiple_days(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        // Mission Thursday-Saturday, Friday portion should count
        $this->insertLog($employee, '2025-03-06', ['mission' => 480]); // Thursday
        $this->insertLog($employee, '2025-03-07', ['mission' => 480]); // Friday
        $this->insertLog($employee, '2025-03-08', ['mission' => 480]); // Saturday

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertGreaterThanOrEqual(480, $attendance->mission, 'Friday mission should be included');
    }

    // -----------------------------------------------------------------------
    // PROBLEMATIC SCENARIO 4: Holiday + remote_work
    // -----------------------------------------------------------------------

    public function test_holiday_with_remote_work_should_be_recorded(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        PublicHoliday::factory()->create(['company_id' => $this->company->id, 'date' => '2025-03-05']);

        $this->insertLog($employee, '2025-03-05', ['remote_work' => 480]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(480, $attendance->remote_work, 'Remote work on holiday should be recorded');
        $this->assertSame(480, $attendance->holiday);
    }

    // -----------------------------------------------------------------------
    // PROBLEMATIC SCENARIO 5: Thursday holiday + mission
    // -----------------------------------------------------------------------

    public function test_thursday_holiday_with_mission_should_be_recorded(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        // Thursday 2025-03-13 as holiday
        PublicHoliday::factory()->create(['company_id' => $this->company->id, 'date' => '2025-03-13']);

        $this->insertLog($employee, '2025-03-13', ['mission' => 480]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(480, $attendance->mission, 'Mission on Thursday holiday should be recorded');
        $this->assertSame(480, $attendance->holiday);
    }

    // -----------------------------------------------------------------------
    // PROBLEMATIC SCENARIO 6: Hourly leave + hourly mission
    // -----------------------------------------------------------------------

    public function test_hourly_leave_and_hourly_mission_both_recorded(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        // Same day: 2 hours leave + 3 hours mission
        $this->insertLog($employee, '2025-03-10', [
            'paid_leave' => 120,
            'mission' => 180,
            'worked' => 180, // remaining work time
        ]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(120, $attendance->paid_leave, 'Hourly leave should be recorded');
        $this->assertSame(180, $attendance->mission, 'Hourly mission should be recorded');
        $this->assertSame(1, $attendance->present_days);
    }

    public function test_hourly_leave_and_mission_full_day_coverage(): void
    {
        $shift = $this->makeShift(); // 480 min shift
        $employee = $this->makeEmployee($shift);

        // 4 hours leave + 4 hours mission = full 8-hour day
        $this->insertLog($employee, '2025-03-17', [
            'paid_leave' => 240,
            'mission' => 240,
        ]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(240, $attendance->paid_leave);
        $this->assertSame(240, $attendance->mission);
        $this->assertSame(1, $attendance->present_days, 'Full day covered by leave+mission should count as present');
    }

    // -----------------------------------------------------------------------
    // PROBLEMATIC SCENARIO 7: Remote work (partial) + overtime
    // -----------------------------------------------------------------------

    public function test_partial_remote_work_with_overtime_both_recorded(): void
    {
        $shift = $this->makeShift(); // 480 min shift
        $employee = $this->makeEmployee($shift);

        // 4 hours remote + 2 hours overtime
        $this->insertLog($employee, '2025-03-18', [
            'remote_work' => 240,
            'worked' => 360, // 6 hours total work (240 remote + 120 office)
            'overtime' => 120,
        ], false); // Insert without recalculation to preserve raw values

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(240, $attendance->remote_work, 'Partial remote work should be recorded');
        $this->assertGreaterThan(0, $attendance->overtime, 'Overtime should not be zero with partial remote work');
    }

    public function test_full_remote_work_with_overtime(): void
    {
        $shift = $this->makeShift(); // 480 min
        $employee = $this->makeEmployee($shift);

        // 8 hours remote + 3 hours overtime
        $this->insertLog($employee, '2025-03-19', [
            'remote_work' => 480,
            'worked' => 660, // 11 hours total
            'overtime' => 180,
        ], false);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(480, $attendance->remote_work);
        $this->assertSame(180, $attendance->overtime, 'Overtime with full remote work should be recorded');
    }

    // -----------------------------------------------------------------------
    // PROBLEMATIC SCENARIO 8: Daily leave + daily mission
    // -----------------------------------------------------------------------

    public function test_daily_leave_and_daily_mission_conflict_resolution(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        // Same day: full-day leave + full-day mission
        $this->insertLog($employee, '2025-03-20', [
            'paid_leave' => 480,
            'mission' => 480,
        ]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        // Both should be recorded (or system should have clear conflict resolution)
        $this->assertTrue(
            $attendance->paid_leave > 0 || $attendance->mission > 0,
            'At least one of daily leave or mission should be recorded'
        );

        // If both are recorded, verify totals
        if ($attendance->paid_leave > 0 && $attendance->mission > 0) {
            $this->assertSame(480, $attendance->paid_leave);
            $this->assertSame(480, $attendance->mission);
        }
    }

    // -----------------------------------------------------------------------
    // COMPLEX COMBINED SCENARIOS
    // -----------------------------------------------------------------------

    public function test_friday_with_remote_work_and_overtime(): void
    {
        $shift = $this->makeShift(['break' => 0]); // 540 min
        $employee = $this->makeEmployee($shift);

        $friday = $this->startDate->next(Carbon::FRIDAY);
        // Friday: remote work + overtime
        $this->insertLog($employee, $friday, [
            'remote_work' => 540,
            'worked' => 660,
            'overtime' => 120,
        ], false);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(540, $attendance->remote_work);
        $this->assertSame(0, $attendance->overtime); // Overtime should not be recorded on holiday
        $this->assertSame(660, $attendance->friday);
    }

    public function test_holiday_with_multiple_conditions(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        PublicHoliday::factory()->create(['company_id' => $this->company->id, 'date' => '2025-03-11']);

        $friday = $this->startDate->next(Carbon::FRIDAY);
        // Holiday: remote work + mission + overtime
        $this->insertLog($employee, $friday, [
            'remote_work' => 240,
            'mission' => 240,
            'overtime' => 120,
        ], false);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(240, $attendance->remote_work);
        $this->assertSame(240, $attendance->mission);
        $this->assertSame(0, $attendance->overtime); // Overtime should not be recorded on holiday
    }

    public function test_hourly_leave_mission_and_overtime_combination(): void
    {
        $shift = $this->makeShift(); // 480 min
        $employee = $this->makeEmployee($shift);

        // 2h leave + 2h mission + 2h overtime
        $this->insertLog($employee, '2025-03-24', [
            'paid_leave' => 120,
            'mission' => 120,
            'worked' => 360,
            'overtime' => 120,
        ], false);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(120, $attendance->paid_leave);
        $this->assertSame(120, $attendance->mission);
        $this->assertSame(120, $attendance->overtime);
    }

    public function test_mission_spanning_thursday_holiday_and_friday(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        PublicHoliday::factory()->create(['company_id' => $this->company->id, 'date' => '2025-03-27']);

        // Mission spanning Thursday (holiday) and Friday
        $this->insertLog($employee, '2025-03-27', ['mission' => 480]); // Thursday holiday
        $this->insertLog($employee, '2025-03-28', ['mission' => 480]); // Friday

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(960, $attendance->mission, 'Both days mission should be calculated');
        $this->assertSame(480, $attendance->holiday);
        $this->assertSame(480, $attendance->friday);
    }

    public function test_complex_week_with_multiple_special_conditions(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        // Mon: normal work
        $this->insertLog($employee, '2025-03-03', ['entry_time' => '08:00:00', 'exit_time' => '17:00:00']);
        // Tue: partial remote + overtime
        $this->insertLog($employee, '2025-03-04', ['remote_work' => 240, 'worked' => 600, 'overtime' => 120], false);
        // Wed: hourly leave + mission
        $this->insertLog($employee, '2025-03-05', ['paid_leave' => 120, 'mission' => 180, 'worked' => 180]);
        // Thu: full mission
        $this->insertLog($employee, '2025-03-06', ['mission' => 480]);
        // Fri: remote work
        $this->insertLog($employee, '2025-03-07', ['remote_work' => 480]);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(5, $attendance->present_days);
        $this->assertGreaterThan(0, $attendance->remote_work);
        $this->assertGreaterThan(0, $attendance->mission);
        $this->assertGreaterThan(0, $attendance->overtime);
        $this->assertGreaterThan(0, $attendance->paid_leave);
    }

    // -----------------------------------------------------------------------
    // Early check-in overtime tests
    // -----------------------------------------------------------------------

    public function test_early_checkin_before_shift_start_counts_as_auto_overtime(): void
    {
        $shift = $this->makeShift([
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 120,
        ]);
        $employee = $this->makeEmployee($shift);

        // Check-in 30 min early, checkout exactly at shift end
        $log = $this->insertLog($employee, '2025-03-03', [
            'entry_time' => '07:00:00',
            'exit_time' => '15:30:00',
        ]);

        $this->assertSame(0, $log->delay);
        $this->assertSame(0, $log->early_leave);
        $this->assertSame(30, $log->auto_overtime, 'Early check-in of 30 min should be counted as auto overtime');
    }

    public function test_early_checkin_and_late_checkout_combine_as_overtime(): void
    {
        $shift = $this->makeShift([
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 120,
        ]);
        $employee = $this->makeEmployee($shift);

        // Check-in 30 min early, checkout 15 min late → 45 min total auto overtime
        $log = $this->insertLog($employee, '2025-03-03', [
            'entry_time' => '07:00:00',
            'exit_time' => '15:45:00',
        ]);

        $this->assertSame(0, $log->delay);
        $this->assertSame(0, $log->early_leave);
        $this->assertSame(45, $log->auto_overtime, '30 min early + 15 min late = 45 min auto overtime');
    }

    public function test_early_checkin_auto_overtime_is_capped_by_shift_limit(): void
    {
        $shift = $this->makeShift([
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 20,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-03', [
            'entry_time' => '07:00:00', // 30 min early
            'exit_time' => '15:45:00',  // 15 min late
        ]);

        $this->assertSame(20, $log->auto_overtime, 'Auto overtime should be capped at max_auto_overtime');
    }

    public function test_late_checkin_with_leave_coverage_and_late_checkout(): void
    {
        // shift 07:30–15:30, check-in 08:15 (45 min delay covered by leave),
        // check-out 15:45 (15 min overtime) → worked=7h30m, paid_leave=45, auto_overtime=15
        $shift = $this->makeShift([
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 120,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-03', [
            'entry_time' => '08:15:00',
            'exit_time' => '15:45:00',
            'paid_leave' => 45,
        ]);

        $this->assertSame(0, $log->delay, 'Delay should be 0 — covered by paid leave');
        $this->assertSame(0, $log->early_leave);
        $this->assertSame(45, $log->paid_leave);
        $this->assertSame(15, $log->auto_overtime, '15 min late checkout → 15 min auto overtime');
        $this->assertSame(450, $log->worked, 'Physical presence 08:15–15:45 = 450 min (7h30m)');
    }

    // -----------------------------------------------------------------------
    // Essential system tests (kept)
    // -----------------------------------------------------------------------

    public function test_recalculate_updates_existing_record(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        $this->insertLog($employee, '2025-03-03', ['worked' => 480]);
        $first = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->insertLog($employee, '2025-03-04', ['worked' => 480]);
        $second = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(2, $second->present_days);
    }

    // -----------------------------------------------------------------------
    // Remote work merged with local work — uses REMOTE_WORK PersonnelRequest
    // start/end times to compute delay / early_leave / overtime, matching
    // local work semantics.
    // -----------------------------------------------------------------------

    public function test_remote_only_with_late_start_produces_delay(): void
    {
        // Shift 09:00–17:00 (480 min). Remote work 10:00–17:00 → delay = 60.
        $shift = $this->makeShift([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 0,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-10', [], false);

        $request = PersonnelRequest::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
            'start_date' => '2025-03-10 10:00:00',
            'end_date' => '2025-03-10 17:00:00',
            'status' => 'approved',
        ]);
        $this->service->syncPersonnelRequestLogs($request);

        $log = $log->fresh();

        $this->assertSame(420, (int) $log->remote_work, '7h of remote work recorded');
        $this->assertSame(420, (int) $log->worked, 'worked = remote minutes');
        $this->assertSame(60, (int) $log->delay, 'late remote start = 60 min delay');
        $this->assertSame(0, (int) $log->early_leave);
        $this->assertSame(0, (int) $log->auto_overtime);
    }

    public function test_remote_only_with_early_end_produces_early_leave(): void
    {
        // Shift 09:00–17:00. Remote 09:00–15:00 → early_leave = 120.
        $shift = $this->makeShift([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 0,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-10', [], false);

        $request = PersonnelRequest::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
            'start_date' => '2025-03-10 09:00:00',
            'end_date' => '2025-03-10 15:00:00',
            'status' => 'approved',
        ]);
        $this->service->syncPersonnelRequestLogs($request);

        $log = $log->fresh();

        $this->assertSame(360, (int) $log->remote_work);
        $this->assertSame(360, (int) $log->worked);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(120, (int) $log->early_leave, 'early end = 120 min early_leave');
    }

    public function test_remote_past_shift_end_generates_auto_overtime(): void
    {
        // Shift 09:00–17:00. Remote 09:00–19:00 → 120 min auto_overtime (capped).
        $shift = $this->makeShift([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 120,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-10', [], false);

        $request = PersonnelRequest::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
            'start_date' => '2025-03-10 09:00:00',
            'end_date' => '2025-03-10 19:00:00',
            'status' => 'approved',
        ]);
        $this->service->syncPersonnelRequestLogs($request);

        $log = $log->fresh();

        $this->assertSame(600, (int) $log->remote_work);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(0, (int) $log->early_leave);
        $this->assertSame(0, (int) $log->overtime);
        $this->assertSame(120, (int) $log->auto_overtime, '2h past shift end = 120 min auto overtime');
    }

    // -----------------------------------------------------------------------
    // OVERLAP DEDUPLICATION: office window and remote window share time
    // -----------------------------------------------------------------------

    public function test_partial_overlap_office_and_remote_deduplicates_worked_and_early_leave(): void
    {
        // Shift 09:00–17:00 (480 min).
        // Office 09:00–12:00 (180 min).  Remote 10:00–15:00 (300 min).
        // Overlap window 10:00–12:00 = 120 min.
        // Union 09:00–15:00 = 360 min.
        // Expected: worked = 360, delay = 0, early_leave = 120 (480 - 360).
        $shift = $this->makeShift([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 0,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '09:00:00',
            'exit_time' => '12:00:00',
        ], false);

        PersonnelRequest::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
            'start_date' => '2025-03-10 10:00:00',
            'end_date' => '2025-03-10 15:00:00',
            'status' => 'approved',
        ]);

        $log = $this->service->recalculateLog($log);

        $this->assertSame(360, (int) $log->worked, 'union of 09:00–12:00 and 10:00–15:00 = 360 min, not 480');
        $this->assertSame(300, (int) $log->remote_work, 'remote_work stores the full request duration');
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(120, (int) $log->early_leave, '480 − 360 = 120 min short');
    }

    public function test_remote_fully_contains_office_deduplicates_worked(): void
    {
        // Shift 09:00–17:00 (480 min).
        // Office 10:00–13:00 (180 min).  Remote 09:00–17:00 (480 min, full day).
        // Overlap = 180 min.  Union = 09:00–17:00 = 480 min.
        // Expected: worked = 480, delay = 0, early_leave = 0.
        $shift = $this->makeShift([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 0,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '10:00:00',
            'exit_time' => '13:00:00',
        ], false);

        PersonnelRequest::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
            'start_date' => '2025-03-10 09:00:00',
            'end_date' => '2025-03-10 17:00:00',
            'status' => 'approved',
        ]);

        $log = $this->service->recalculateLog($log);

        $this->assertSame(480, (int) $log->worked, '180 office + 480 remote − 180 overlap = 480');
        $this->assertSame(480, (int) $log->remote_work);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(0, (int) $log->early_leave);
    }

    public function test_identical_office_and_remote_windows_deduplicates_fully(): void
    {
        // Shift 09:00–17:00 (480 min).  Both office and remote cover 09:00–17:00.
        // Overlap = 480 min.  Union = 480 min.
        // Expected: worked = 480, not 960.
        $shift = $this->makeShift([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 0,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '09:00:00',
            'exit_time' => '17:00:00',
        ], false);

        PersonnelRequest::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
            'start_date' => '2025-03-10 09:00:00',
            'end_date' => '2025-03-10 17:00:00',
            'status' => 'approved',
        ]);

        $log = $this->service->recalculateLog($log);

        $this->assertSame(480, (int) $log->worked, '480 + 480 − 480 overlap = 480');
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(0, (int) $log->early_leave);
    }

    public function test_office_and_remote_merged_calculation(): void
    {
        // User's example:
        //   shift 09:00–17:00 (480 min)
        //   office 10:00–12:00 (2h, 60min late)
        //   remote 13:00–16:00 (3h)
        // Expected: worked = 300, delay = 60, early_leave = shift - worked = 180.
        $shift = $this->makeShift([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 0,
        ]);
        $employee = $this->makeEmployee($shift);

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '10:00:00',
            'exit_time' => '12:00:00',
        ], false);

        $request = PersonnelRequest::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
            'start_date' => '2025-03-10 13:00:00',
            'end_date' => '2025-03-10 16:00:00',
            'status' => 'approved',
        ]);
        $this->service->syncPersonnelRequestLogs($request);

        $log = $log->fresh();

        $this->assertSame(180, (int) $log->remote_work, '3h remote recorded');
        $this->assertSame(300, (int) $log->worked, 'office 2h + remote 3h = 5h');
        $this->assertSame(60, (int) $log->delay, '1h delay from late office entry');
        // Missing time = shift(480) - worked(300) = 180; split between delay(60)
        // and the rest (mid-gap 60 + early end 60). delay is tracked separately,
        // early_leave covers the remaining 120.
        $this->assertSame(120, (int) $log->early_leave, 'mid-gap + early end = 120');
    }

    // -----------------------------------------------------------------------
    // MID-SHIFT HOURLY LEAVE — clocked window spans the leave window.
    //
    // 08:00→16:00 with hourly leave 11:00–13:00 reported 8h worked and 2h leave (the leave was double-counted as both work and leave).
    // It must report 6h worked + 2h leave.
    // -----------------------------------------------------------------------

    /** Create an approved hourly request and apply it to the day's log. */
    private function applyHourlyRequest(Employee $employee, PersonnelRequestType $type, string $start, string $end): void
    {
        $request = PersonnelRequest::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'request_type' => $type->value,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'approved',
        ]);

        $this->service->syncPersonnelRequestLogs($request);
    }

    private function eightToFourShift(array $overrides = []): WorkShift
    {
        return $this->makeShift(array_merge([
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 0,
            'float' => 0,
            'max_auto_overtime' => 120,
        ], $overrides));
    }

    public function test_mid_shift_hourly_leave_is_excluded_from_worked(): void
    {
        // Shift 08:00–16:00 (480 min). Clock 08:00→16:00, leave 11:00–13:00.
        // Expected: 6h worked + 2h leave, no delay / early_leave / overtime.
        $employee = $this->makeEmployee($this->eightToFourShift());

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '08:00:00',
            'exit_time' => '16:00:00',
        ], false);

        $this->applyHourlyRequest($employee, PersonnelRequestType::LEAVE_HOURLY, '2025-03-10 11:00:00', '2025-03-10 13:00:00');

        $log = $log->fresh();

        $this->assertSame(360, (int) $log->worked, '8h clocked − 2h mid-shift leave = 6h worked');
        $this->assertSame(120, (int) $log->paid_leave);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(0, (int) $log->early_leave);
        $this->assertSame(0, (int) $log->overtime);
        $this->assertSame(0, (int) $log->auto_overtime);
    }

    public function test_full_day_without_leave_records_full_worked(): void
    {
        // Control: same clock, no leave → 8h worked, nothing else.
        $employee = $this->makeEmployee($this->eightToFourShift());

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '08:00:00',
            'exit_time' => '16:00:00',
        ]);

        $this->assertSame(480, (int) $log->worked);
        $this->assertSame(0, (int) $log->paid_leave);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(0, (int) $log->early_leave);
        $this->assertSame(0, (int) $log->auto_overtime);
    }

    public function test_mid_shift_leave_with_delay_keeps_delay(): void
    {
        // Late arrival 08:30 (30 min delay) + mid-shift leave 11:00–13:00. Mid-shift leave must NOT absorb the delay.
        $employee = $this->makeEmployee($this->eightToFourShift());

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '08:30:00',
            'exit_time' => '16:00:00',
        ], false);

        $this->applyHourlyRequest($employee, PersonnelRequestType::LEAVE_HOURLY, '2025-03-10 11:00:00', '2025-03-10 13:00:00');

        $log = $log->fresh();

        $this->assertSame(330, (int) $log->worked, 'presence 7h30m − 2h leave = 5h30m');
        $this->assertSame(120, (int) $log->paid_leave);
        $this->assertSame(30, (int) $log->delay, 'mid-shift leave does not cover the late arrival');
        $this->assertSame(0, (int) $log->early_leave);
    }

    public function test_mid_shift_leave_with_early_leave_keeps_early_leave(): void
    {
        // Early departure 15:00 (60 min early) + mid-shift leave 11:00–13:00. Mid-shift leave must NOT absorb the early_leave.
        $employee = $this->makeEmployee($this->eightToFourShift());

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '08:00:00',
            'exit_time' => '15:00:00',
        ], false);

        $this->applyHourlyRequest($employee, PersonnelRequestType::LEAVE_HOURLY, '2025-03-10 11:00:00', '2025-03-10 13:00:00');

        $log = $log->fresh();

        $this->assertSame(300, (int) $log->worked, 'presence 7h − 2h leave = 5h');
        $this->assertSame(120, (int) $log->paid_leave);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(60, (int) $log->early_leave, 'left 1h early; mid-shift leave does not cover it');
    }

    public function test_edge_leave_at_start_still_absorbs_delay_and_keeps_worked(): void
    {
        // Leave 08:00–09:00 fills the start gap; clock 09:00→16:00.
        // The leave is NOT mid-shift (no overlap with presence) so it absorbs the delay and does not reduce worked.
        $employee = $this->makeEmployee($this->eightToFourShift());

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '09:00:00',
            'exit_time' => '16:00:00',
        ], false);

        $this->applyHourlyRequest($employee, PersonnelRequestType::LEAVE_HOURLY, '2025-03-10 08:00:00', '2025-03-10 09:00:00');

        $log = $log->fresh();

        $this->assertSame(420, (int) $log->worked, 'edge leave does not reduce worked (presence 7h)');
        $this->assertSame(60, (int) $log->paid_leave);
        $this->assertSame(0, (int) $log->delay, 'start-gap leave absorbs the 1h delay');
        $this->assertSame(0, (int) $log->early_leave);
    }

    public function test_mid_shift_leave_with_overtime(): void
    {
        // Clock 08:00→18:00 (2h past shift end) + mid-shift leave 11:00–13:00.
        // worked = 8h in-shift presence − 2h leave + 2h overtime presence = 8h.
        $employee = $this->makeEmployee($this->eightToFourShift());

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '08:00:00',
            'exit_time' => '18:00:00',
        ], false);

        $this->applyHourlyRequest($employee, PersonnelRequestType::LEAVE_HOURLY, '2025-03-10 11:00:00', '2025-03-10 13:00:00');

        $log = $log->fresh();

        $this->assertSame(480, (int) $log->worked, '10h presence − 2h mid-shift leave = 8h');
        $this->assertSame(120, (int) $log->paid_leave);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(0, (int) $log->early_leave);
        $this->assertSame(120, (int) $log->auto_overtime, '2h past shift end → auto overtime (capped at 120)');
    }

    public function test_mid_shift_mission_is_excluded_from_worked(): void
    {
        // Same as the leave case but with an hourly mission 11:00–13:00.
        $employee = $this->makeEmployee($this->eightToFourShift());

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '08:00:00',
            'exit_time' => '16:00:00',
        ], false);

        $this->applyHourlyRequest($employee, PersonnelRequestType::MISSION_HOURLY, '2025-03-10 11:00:00', '2025-03-10 13:00:00');

        $log = $log->fresh();

        $this->assertSame(360, (int) $log->worked, '8h clocked − 2h mid-shift mission = 6h worked');
        $this->assertSame(120, (int) $log->mission);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(0, (int) $log->early_leave);
    }

    public function test_mid_shift_leave_monthly_totals(): void
    {
        // The corrected day flows into the monthly aggregate: present, no undertime, 2h paid leave.
        $employee = $this->makeEmployee($this->eightToFourShift());

        $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '08:00:00',
            'exit_time' => '16:00:00',
        ], false);

        $this->applyHourlyRequest($employee, PersonnelRequestType::LEAVE_HOURLY, '2025-03-10 11:00:00', '2025-03-10 13:00:00');

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(1, $attendance->present_days);
        $this->assertSame(120, $attendance->paid_leave);
        $this->assertSame(0, $attendance->undertime, 'no delay/early_leave when leave is mid-shift');
    }

    // -----------------------------------------------------------------------
    // EARLY ARRIVAL → AUTO OVERTIME, combined with mid-shift hourly leave.
    // Auto overtime must still be computed (and capped by max_auto_overtime)
    // even when an hourly leave was taken mid-shift.
    // -----------------------------------------------------------------------

    public function test_early_arrival_generates_auto_overtime_with_mid_shift_leave(): void
    {
        // Shift 08:00–16:00 (480 min, cap 120). Clock 07:30→16:00 (30 min early),
        // leave 11:00–13:00. Early arrival → 30 min auto overtime; mid-shift leave still excluded from worked.
        $employee = $this->makeEmployee($this->eightToFourShift());

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '07:30:00',
            'exit_time' => '16:00:00',
        ], false);

        $this->applyHourlyRequest($employee, PersonnelRequestType::LEAVE_HOURLY, '2025-03-10 11:00:00', '2025-03-10 13:00:00');

        $log = $log->fresh();

        $this->assertSame(390, (int) $log->worked, 'presence 8h30m − 2h mid-shift leave = 6h30m');
        $this->assertSame(120, (int) $log->paid_leave);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(0, (int) $log->early_leave);
        $this->assertSame(0, (int) $log->overtime);
        $this->assertSame(30, (int) $log->auto_overtime, '30 min early arrival → 30 min auto overtime');
    }

    public function test_early_arrival_and_late_departure_auto_overtime_capped_with_mid_shift_leave(): void
    {
        // Shift 08:00–16:00 (480 min), max_auto_overtime = 90.
        // Clock 07:00→17:00 (60 min early + 60 min late = 120 min raw overtime),
        // leave 11:00–13:00. Raw overtime 120 is capped to 90; the mid-shift leave is still excluded from worked.
        $employee = $this->makeEmployee($this->eightToFourShift(['max_auto_overtime' => 90]));

        $log = $this->insertLog($employee, '2025-03-10', [
            'entry_time' => '07:00:00',
            'exit_time' => '17:00:00',
        ], false);

        $this->applyHourlyRequest($employee, PersonnelRequestType::LEAVE_HOURLY, '2025-03-10 11:00:00', '2025-03-10 13:00:00');

        $log = $log->fresh();

        $this->assertSame(480, (int) $log->worked, 'presence 10h − 2h mid-shift leave = 8h');
        $this->assertSame(120, (int) $log->paid_leave);
        $this->assertSame(0, (int) $log->delay);
        $this->assertSame(0, (int) $log->early_leave);
        $this->assertSame(0, (int) $log->overtime);
        $this->assertSame(90, (int) $log->auto_overtime, '60 early + 60 late = 120 raw, capped at max 90');
    }
}
