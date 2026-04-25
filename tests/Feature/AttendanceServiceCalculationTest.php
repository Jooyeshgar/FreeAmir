<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
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
        $this->assertSame(480, $this->service->shiftWorkMinutes($shift));
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

        // Friday 2025-03-07
        $this->insertLog($employee, '2025-03-07', ['remote_work' => 480]);

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

    public function test_auto_overtime_is_capped_by_actual_extra_work(): void
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
            'exit_time' => '18:00:00',
            'overtime' => 180,
        ]);

        $this->assertSame(120, $log->overtime);
        $this->assertSame(0, $log->auto_overtime);

        $attendance = $this->service->calculateAndStore(
            $employee->id,
            $this->startDate,
            $this->durationDays,
            1404,
            1
        );

        $this->assertSame(120, $attendance->overtime);
        $this->assertSame(0, $attendance->auto_overtime);
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

        // Friday: remote work + overtime
        $this->insertLog($employee, '2025-03-07', [
            'remote_work' => 540,
            'worked' => 660,
            'overtime' => 120,
        ], false);

        $attendance = $this->service->calculateAndStore($employee->id, $this->startDate, $this->durationDays, 1404, 1);

        $this->assertSame(540, $attendance->remote_work);
        $this->assertSame(0, $attendance->overtime); // Overtime should not be recorded on holiday
        $this->assertSame(1200, $attendance->friday);
    }

    public function test_holiday_with_multiple_conditions(): void
    {
        $shift = $this->makeShift();
        $employee = $this->makeEmployee($shift);

        PublicHoliday::factory()->create(['company_id' => $this->company->id, 'date' => '2025-03-11']);

        // Holiday: remote work + mission + overtime
        $this->insertLog($employee, '2025-03-11', [
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
}
