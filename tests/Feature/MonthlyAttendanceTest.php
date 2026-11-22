<?php

namespace Tests\Feature;

use App\Enums\PersonnelRequestStatus;
use App\Enums\PersonnelRequestType;
use App\Enums\ThursdayStatus;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\MonthlyAttendance;
use App\Models\PersonnelRequest;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MonthlyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $fiscalYearId;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $fiscalYear = FiscalYear::factory()->create(['year' => 1402]);
        $this->fiscalYearId = $fiscalYear->id;

        $this->user = User::factory()->create();
        $fiscalYear->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'attendance.monthly-attendances.*'])
        );

        $this->actingAs($this->user);
        request()->cookies->set('active-fiscal-year-id', $this->fiscalYearId);
        $this->withCookies(['active-fiscal-year-id' => $this->fiscalYearId]);

        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $workShift = WorkShift::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);

        $this->employee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ]);
    }

    private function makeMonthlyAttendance(array $overrides = []): MonthlyAttendance
    {
        return MonthlyAttendance::factory()->create(array_merge([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
        ], $overrides));
    }

    private function validCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'start_date' => '2025/10/23',  // 1404/08/01 in Jalali (Aban 1404)
            'duration' => 30,
        ], $overrides);
    }

    private function validUpdatePayload(array $overrides = []): array
    {
        return array_merge([
            'work_days' => 22,
            'present_days' => 20,
            'absent_days' => 2,
            'overtime' => 120,
            'mission' => 1,
            'paid_leave' => 1,
            'unpaid_leave' => 0,
            'friday' => 0,
            'holiday' => 0,
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_returns_200(): void
    {
        $response = $this->get(route('attendance.monthly-attendances.index'));

        $response->assertStatus(200);
    }

    public function test_index_lists_records_for_active_company(): void
    {
        $this->makeMonthlyAttendance(['year' => 1403, 'month' => 7]);
        $this->makeMonthlyAttendance(['year' => 1403, 'month' => 8]);

        $response = $this->get(route('attendance.monthly-attendances.index'));

        $response->assertStatus(200);
        $response->assertSee('مهر');
        $response->assertSee('آبان');
    }

    public function test_index_filters_by_employee(): void
    {
        $workSite2 = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $other = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite2->id,
        ]);

        $this->makeMonthlyAttendance(['employee_id' => $this->employee->id, 'year' => 1403, 'month' => 7]);
        $this->makeMonthlyAttendance(['employee_id' => $other->id, 'year' => 1403, 'month' => 8]);

        $response = $this->get(route('attendance.monthly-attendances.index', ['employee_id' => $this->employee->id]));

        $response->assertStatus(200);
        $response->assertSee($this->employee->id);
    }

    public function test_index_does_not_show_other_company_records(): void
    {
        $otherFiscalYear = FiscalYear::factory()->create(['year' => 1403]);
        $otherWorkSite = WorkSite::factory()->create(['fiscal_year_id' => $otherFiscalYear->id]);
        $otherEmployee = Employee::factory()->create([
            'fiscal_year_id' => $otherFiscalYear->id,
            'work_site_id' => $otherWorkSite->id,
        ]);
        MonthlyAttendance::factory()->create([
            'fiscal_year_id' => $otherFiscalYear->id,
            'employee_id' => $otherEmployee->id,
            'year' => 1403,
            'month' => 7,
        ]);

        $response = $this->get(route('attendance.monthly-attendances.index'));

        $response->assertStatus(200);
        $response->assertDontSee($otherEmployee->first_name);
    }

    // ----------------------------------------------------------------
    // bulk store
    // ----------------------------------------------------------------

    private function validBulkPayload(array $overrides = []): array
    {
        return array_merge([
            'employee_ids' => [$this->employee->id],
            'start_date' => '2025/10/23',
            'duration' => 30,
        ], $overrides);
    }

    public function test_bulk_create_returns_200(): void
    {
        $response = $this->get(route('attendance.monthly-attendances.bulk-create'));

        $response->assertStatus(200);
    }

    public function test_bulk_store_creates_attendance_for_each_selected_employee(): void
    {
        $workSite2 = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $workShift2 = WorkShift::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $employee2 = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite2->id,
            'work_shift_id' => $workShift2->id,
        ]);

        $response = $this->post(
            route('attendance.monthly-attendances.bulk-store'),
            $this->validBulkPayload(['employee_ids' => [$this->employee->id, $employee2->id]])
        );

        $response->assertRedirect(route('attendance.monthly-attendances.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('monthly_attendances', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
        ]);
        $this->assertDatabaseHas('monthly_attendances', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $employee2->id,
        ]);
    }

    public function test_bulk_store_only_creates_for_selected_employees(): void
    {
        $workSite2 = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $workShift2 = WorkShift::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $unselectedEmployee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite2->id,
            'work_shift_id' => $workShift2->id,
        ]);

        $this->post(
            route('attendance.monthly-attendances.bulk-store'),
            $this->validBulkPayload(['employee_ids' => [$this->employee->id]])
        );

        $this->assertDatabaseHas('monthly_attendances', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
        ]);
        $this->assertDatabaseMissing('monthly_attendances', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $unselectedEmployee->id,
        ]);
    }

    public function test_bulk_store_validates_employee_ids_required(): void
    {
        $response = $this->post(
            route('attendance.monthly-attendances.bulk-store'),
            ['start_date' => '2025/10/23', 'duration' => 30]
        );

        $response->assertSessionHasErrors(['employee_ids']);
    }

    public function test_bulk_store_validates_start_date_required(): void
    {
        $response = $this->post(
            route('attendance.monthly-attendances.bulk-store'),
            ['employee_ids' => [$this->employee->id], 'duration' => 30]
        );

        $response->assertSessionHasErrors(['start_date']);
    }

    public function test_bulk_store_validates_duration_range(): void
    {
        $response = $this->post(
            route('attendance.monthly-attendances.bulk-store'),
            $this->validBulkPayload(['duration' => 10])
        );

        $response->assertSessionHasErrors(['duration']);
    }

    public function test_bulk_store_rejects_nonexistent_employee_id(): void
    {
        $response = $this->post(
            route('attendance.monthly-attendances.bulk-store'),
            $this->validBulkPayload(['employee_ids' => [99999]])
        );

        $response->assertSessionHasErrors(['employee_ids.0']);
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('attendance.monthly-attendances.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_record_and_redirects(): void
    {
        $payload = $this->validCreatePayload();

        $response = $this->post(route('attendance.monthly-attendances.store'), $payload);

        $response->assertRedirect(route('attendance.monthly-attendances.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('monthly_attendances', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('attendance.monthly-attendances.store'), []);

        $response->assertSessionHasErrors(['employee_id', 'start_date', 'duration']);
    }

    public function test_store_validates_duration_range(): void
    {
        $payload = $this->validCreatePayload(['duration' => 10]);

        $response = $this->post(route('attendance.monthly-attendances.store'), $payload);

        $response->assertSessionHasErrors(['duration']);
    }

    public function test_store_calculates_attendance_with_logs(): void
    {
        // Create 5 present days and 1 absent day within the period
        $baseDate = Carbon::create(2025, 10, 23); // start of the test period

        for ($i = 0; $i < 5; $i++) {
            $day = $baseDate->copy()->addDays($i);
            // Skip Fridays in log creation
            if ($day->dayOfWeek === Carbon::FRIDAY) {
                continue;
            }
            AttendanceLog::factory()->create([
                'fiscal_year_id' => $this->fiscalYearId,
                'employee_id' => $this->employee->id,
                'log_date' => $day->toDateString(),
                'entry_time' => '08:00:00',
                'exit_time' => '17:00:00',
                'worked' => 480,
            ]);
        }

        $this->post(route('attendance.monthly-attendances.store'), $this->validCreatePayload());

        $record = MonthlyAttendance::where('fiscal_year_id', $this->fiscalYearId)
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertGreaterThan(0, $record->work_days);
    }

    // ----------------------------------------------------------------
    // show
    // ----------------------------------------------------------------

    public function test_show_returns_200(): void
    {
        $attendance = $this->makeMonthlyAttendance(['year' => 1403, 'month' => 7]);

        $response = $this->get(route('attendance.monthly-attendances.show', $attendance));

        $response->assertStatus(200);
        $response->assertSee('مهر');
    }

    public function test_admin_and_employee_monthly_attendance_show_all_log_statuses(): void
    {
        $attendance = $this->makeMonthlyAttendance([
            'year' => 1403,
            'month' => 10,
            'start_date' => '2025-01-06',
            'duration' => 1,
        ]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'monthly_attendance_id' => $attendance->id,
            'log_date' => '2025-01-06',
            'entry_time' => '08:00:00',
            'exit_time' => '12:00:00',
            'worked' => 360,
            'remote_work' => 120,
            'paid_leave' => 60,
        ]);

        $this->employee->update(['user_id' => $this->user->id]);
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'employee-portal.dashboard'])
        );

        $responses = [
            $this->get(route('attendance.monthly-attendances.show', $attendance)),
            $this->get(route('employee-portal.monthly-attendances.show', $attendance)),
        ];

        foreach ($responses as $response) {
            $response->assertOk();
            $response->assertSeeHtml('<span class="badge badge-info badge-sm">'.__('Paid Leave').'</span>');
            $response->assertSeeHtml('<span class="badge badge-primary badge-sm">'.__('Remote Work').'</span>');
            $response->assertSeeHtml('<span class="badge badge-success badge-sm">'.__('Present').'</span>');
        }
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $attendance = $this->makeMonthlyAttendance(['year' => 1403, 'month' => 1]);

        $response = $this->get(route('attendance.monthly-attendances.edit', $attendance));

        $response->assertStatus(200);
    }

    public function test_update_saves_changes_and_redirects(): void
    {
        $attendance = $this->makeMonthlyAttendance(['year' => 1403, 'month' => 1]);
        $payload = $this->validUpdatePayload(['present_days' => 18, 'absent_days' => 4]);

        $response = $this->put(route('attendance.monthly-attendances.update', $attendance), $payload);

        $response->assertRedirect(route('attendance.monthly-attendances.show', $attendance));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('monthly_attendances', [
            'id' => $attendance->id,
            'present_days' => 18,
            'absent_days' => 4,
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $attendance = $this->makeMonthlyAttendance(['year' => 1403, 'month' => 1]);

        $response = $this->put(route('attendance.monthly-attendances.update', $attendance), []);

        $response->assertSessionHasErrors(['work_days', 'present_days', 'absent_days']);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_and_redirects(): void
    {
        $attendance = $this->makeMonthlyAttendance(['year' => 1403, 'month' => 1]);

        $response = $this->delete(route('attendance.monthly-attendances.destroy', $attendance));

        $response->assertRedirect(route('attendance.monthly-attendances.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('monthly_attendances', ['id' => $attendance->id]);
    }

    // ----------------------------------------------------------------
    // recalculate
    // ----------------------------------------------------------------

    public function test_recalculate_updates_record(): void
    {
        $attendance = $this->makeMonthlyAttendance([
            'year' => 1403,
            'month' => 8,
            'present_days' => 0,
            'absent_days' => 22,
        ]);

        // Add a log for the first day of the period
        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2024-10-22', // 1403/08/01
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
            'worked' => 480,
        ]);

        $response = $this->post(route('attendance.monthly-attendances.recalculate', $attendance), [
            'start_date' => '1403/08/01',
            'duration' => 30,
        ]);
        $response->assertSessionHas('success');

        $newMonthlyAttendances = MonthlyAttendance::where('start_date', '2024-10-22')->where('duration', 30)->get();

        $this->assertNotEquals($attendance, $newMonthlyAttendances);

        $notFoundResponse = $this->get(route('attendance.monthly-attendances.show', $attendance));
        $notFoundResponse->assertNotFound();
    }

    public function test_recalculate_all_recalculates_each_linked_attendance_log(): void
    {
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'attendance.attendance-logs.recalculate-all'])
        );

        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break' => 60,
            'float' => 0,
            'max_auto_overtime' => 120,
        ]);

        $this->employee->update(['work_shift_id' => $workShift->id]);

        $attendance = $this->makeMonthlyAttendance([
            'year' => 1403,
            'month' => 8,
            'start_date' => '2024-10-22',
            'duration' => 30,
        ]);

        $firstLog = AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'monthly_attendance_id' => $attendance->id,
            'log_date' => '2024-10-22',
            'entry_time' => '08:00:00',
            'exit_time' => '17:07:00',
            'worked' => 1,
            'delay' => 12,
            'early_leave' => 9,
            'overtime' => 7,
            'auto_overtime' => 5,
        ]);

        $secondLog = AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'monthly_attendance_id' => $attendance->id,
            'log_date' => '2024-10-23',
            'entry_time' => '08:15:00',
            'exit_time' => '17:00:00',
            'worked' => 2,
            'delay' => 0,
            'early_leave' => 3,
            'overtime' => 4,
            'auto_overtime' => 6,
        ]);

        $response = $this->post(route('attendance.attendance-logs.recalculate-all', $attendance));

        $response->assertRedirect(route('attendance.monthly-attendances.show', $attendance));
        $response->assertSessionHas('success', __('All monthly Attendance logs recalculated successfully.'));

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $firstLog->id,
            'worked' => 547,
            'delay' => 0,
            'early_leave' => 0,
            'overtime' => 7,
            'auto_overtime' => 0,
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $secondLog->id,
            'worked' => 525,
            'delay' => 15,
            'early_leave' => 0,
            'overtime' => 0,
            'auto_overtime' => 0,
        ]);
    }

    // ----------------------------------------------------------------
    // AttendanceService unit-style tests
    // ----------------------------------------------------------------

    public function test_attendance_service_counts_present_and_absent_days(): void
    {
        $service = new AttendanceService;
        $startDate = Carbon::create(2025, 1, 6); // A Monday (not Friday)

        // Build 5 working day logs: 3 present, 2 absent
        $logs = new Collection;

        foreach ([0, 1, 2] as $offset) {
            $day = $startDate->copy()->addDays($offset);
            if ($day->dayOfWeek === Carbon::FRIDAY) {
                continue;
            }
            $log = new AttendanceLog([
                'log_date' => $day->toDateString(),
                'entry_time' => '08:00:00',
                'exit_time' => '17:00:00',
                'worked' => 480,
                'overtime' => 0,
                'delay' => 0,
                'mission' => 0,
                'paid_leave' => 0,
                'unpaid_leave' => 0,
                'is_friday' => false,
                'is_holiday' => false,
            ]);
            $log->setAttribute('log_date', $day->toDateString());
            $logs->push($log);
        }

        $totals = $service->computeTotals($startDate, 5, $logs, []);

        $this->assertEquals(3, $totals['present_days']);
    }

    public function test_attendance_service_includes_fridays_from_work_days(): void
    {
        $service = new AttendanceService;

        // Find a Friday
        $friday = Carbon::now()->next(Carbon::FRIDAY);

        $totals = $service->computeTotals($friday, 1, new Collection, []);

        $this->assertEquals(1, $totals['work_days']);
        $this->assertEquals(0, $totals['absent_days']);
    }

    public function test_attendance_service_includes_public_holidays_from_work_days(): void
    {
        $service = new AttendanceService;

        $monday = Carbon::create(2025, 1, 6); // Known Monday
        $holiday = $monday->toDateString();

        $totals = $service->computeTotals($monday, 1, new Collection, [$holiday]);

        $this->assertEquals(1, $totals['work_days']);
        $this->assertEquals(0, $totals['absent_days']);
    }

    public function test_attendance_service_counts_overtime(): void
    {
        $service = new AttendanceService;
        $monday = Carbon::create(2025, 1, 6);

        // 600 worked minutes = 480 standard + 120 overtime (already stored on the log)
        $log = new AttendanceLog([
            'worked' => 600,
            'overtime' => 120,
            'delay' => 0,
            'mission' => 0,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
            'is_friday' => false,
            'is_holiday' => false,
        ]);
        $log->log_date = $monday;

        $totals = $service->computeTotals($monday, 1, new Collection([$log]), []);

        $this->assertEquals(120, $totals['overtime']);
    }

    public function test_attendance_service_sums_auto_overtime_and_remote_work_minutes(): void
    {
        $service = new AttendanceService;
        $startDate = Carbon::create(2025, 1, 6); // Monday

        $first = new AttendanceLog([
            'worked' => 480,
            'overtime' => 60,
            'auto_overtime' => 90,
            'remote_work' => 30,
            'delay' => 0,
            'early_leave' => 0,
            'mission' => 0,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
            'is_friday' => false,
            'is_holiday' => false,
        ]);
        $first->setAttribute('log_date', $startDate->toDateString());

        $second = new AttendanceLog([
            'worked' => 480,
            'overtime' => 0,
            'auto_overtime' => 30,
            'remote_work' => 120,
            'delay' => 15,
            'early_leave' => 45,
            'mission' => 0,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
            'is_friday' => false,
            'is_holiday' => false,
        ]);
        $second->setAttribute('log_date', $startDate->copy()->addDay()->toDateString());

        $totals = $service->computeTotals($startDate, 2, new Collection([$first, $second]), []);

        $this->assertEquals(2, $totals['present_days']);
        $this->assertEquals(60, $totals['overtime']);
        $this->assertEquals(120, $totals['auto_overtime']);
        $this->assertEquals(150, $totals['remote_work']);
        $this->assertEquals(60, $totals['undertime']);
    }

    public function test_attendance_service_does_not_count_thursday_holiday_as_absent(): void
    {
        $service = new AttendanceService;
        $thursday = Carbon::create(2025, 1, 9); // Thursday
        $workShift = WorkShift::factory()->make([
            'thursday_status' => ThursdayStatus::HOLIDAY,
        ]);

        $totals = $service->computeTotals($thursday, 1, new Collection, [], $workShift);

        $this->assertEquals(1, $totals['work_days']);
        $this->assertEquals(0, $totals['present_days']);
        $this->assertEquals(0, $totals['absent_days']);
        $this->assertEquals(0, $totals['holiday']);
    }

    public function test_attendance_service_counts_regular_missing_day_as_absent(): void
    {
        $service = new AttendanceService;
        $monday = Carbon::create(2025, 1, 6);

        $totals = $service->computeTotals($monday, 1, new Collection, []);

        $this->assertEquals(1, $totals['work_days']);
        $this->assertEquals(0, $totals['present_days']);
        $this->assertEquals(1, $totals['absent_days']);
    }

    public function test_daily_leave_without_pay_is_neither_present_nor_absent_and_has_no_undertime(): void
    {
        $workShift = $this->employee->workShift;
        $workShift->update(['start_time' => '08:00:00', 'end_time' => '16:00:00', 'float' => 0]);

        $request = PersonnelRequest::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_WITHOUT_PAY,
            'start_date' => '2025-01-06 08:00:00',
            'end_date' => '2025-01-06 16:00:00',
            'status' => PersonnelRequestStatus::APPROVED,
        ]);

        $service = new AttendanceService;
        $service->syncPersonnelRequestLogs($request);

        $log = AttendanceLog::where('employee_id', $this->employee->id)->whereDate('log_date', '2025-01-06')->firstOrFail();
        $totals = $service->computeTotals(Carbon::parse('2025-01-06'), 1, new Collection([$log]), [], $workShift->fresh());

        $this->assertSame(480, $log->unpaid_leave);
        $this->assertSame(0, $log->delay + $log->early_leave);
        $this->assertSame(0, $totals['present_days']);
        $this->assertSame(0, $totals['absent_days']);
        $this->assertSame(0, $totals['undertime']);
        $this->assertSame(480, $totals['unpaid_leave']);
        $this->assertSame(1, $totals['unpaid_leave_days']);
    }

    public function test_hourly_leave_without_pay_is_present_without_leave_undertime(): void
    {
        $workShift = $this->employee->workShift;
        $workShift->update(['start_time' => '08:00:00', 'end_time' => '16:00:00', 'float' => 0]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2025-01-06',
            'entry_time' => '10:00:00',
            'exit_time' => '16:00:00',
        ]);

        $request = PersonnelRequest::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_WITHOUT_PAY_HOURLY,
            'start_date' => '2025-01-06 08:00:00',
            'end_date' => '2025-01-06 10:00:00',
            'status' => PersonnelRequestStatus::APPROVED,
        ]);

        $service = new AttendanceService;
        $service->syncPersonnelRequestLogs($request);

        $log = AttendanceLog::where('employee_id', $this->employee->id)->whereDate('log_date', '2025-01-06')->firstOrFail();
        $totals = $service->computeTotals(Carbon::parse('2025-01-06'), 1, new Collection([$log]), [], $workShift->fresh());

        $this->assertSame(120, $log->unpaid_leave);
        $this->assertSame(0, $log->delay + $log->early_leave);
        $this->assertSame(1, $totals['present_days']);
        $this->assertSame(0, $totals['absent_days']);
        $this->assertSame(0, $totals['undertime']);
        $this->assertSame(120, $totals['unpaid_leave']);
        $this->assertSame(0, $totals['unpaid_leave_days']);
    }

    // ----------------------------------------------------------------
    // leave_remain accrual & portal display
    // ----------------------------------------------------------------

    public function test_store_accrues_worked_month_paid_leave_and_deducts_used_leave(): void
    {
        $this->employee->workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);
        $this->employee->update([
            'is_active' => true,
            'contract_end_date' => null,
            'leave_remain' => 0,
        ]);

        // 1404/08/01 = 2025-10-23, duration 30 => Aban 1404
        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2025-10-25',
            'paid_leave' => 300,
        ]);

        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $this->employee->id,
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        // 0 (previous) + 1200 (earned for Aban) - 300 (used in Aban) = 900
        $this->assertSame(900, $this->employee->fresh()->leave_remain);
    }

    public function test_store_accrues_only_worked_month_for_departing_employee(): void
    {
        $this->employee->workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);
        $this->employee->update([
            'is_active' => false,
            'contract_end_date' => '2025-11-21',
            'leave_remain' => 0,
        ]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2025-10-25',
            'paid_leave' => 480,
        ]);

        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $this->employee->id,
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        // 0 (previous) + 1200 (earned for Aban) - 480 (used in Aban) = 720 (no next-month grant)
        $this->assertSame(720, $this->employee->fresh()->leave_remain);
    }

    public function test_bulk_store_accrues_worked_month_paid_leave_for_each_employee(): void
    {
        $workShift = $this->employee->workShift;
        $workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);

        $this->employee->update([
            'is_active' => true,
            'leave_remain' => 600,
        ]);

        $secondEmployee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $this->employee->work_site_id,
            'work_shift_id' => $workShift->id,
            'is_active' => true,
            'leave_remain' => 0,
        ]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $secondEmployee->id,
            'log_date' => '2025-10-25',
            'paid_leave' => 200,
        ]);

        $this->post(route('attendance.monthly-attendances.bulk-store'), [
            'employee_ids' => [$this->employee->id, $secondEmployee->id],
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(1800, $this->employee->fresh()->leave_remain);
        $this->assertSame(1000, $secondEmployee->fresh()->leave_remain);
    }

    public function test_destroy_reverts_worked_month_accrual_and_used_leave(): void
    {
        $this->employee->workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);
        $this->employee->update([
            'leave_remain' => 0,
        ]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2025-10-25',
            'paid_leave' => 300,
        ]);

        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $this->employee->id,
            'start_date' => '1404/08/01',
            'duration' => 30,
        ]);

        $this->assertSame(900, $this->employee->fresh()->leave_remain);

        $attendance = MonthlyAttendance::where('employee_id', $this->employee->id)->firstOrFail();

        $this->delete(route('attendance.monthly-attendances.destroy', $attendance))
            ->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(0, $this->employee->fresh()->leave_remain);
    }

    public function test_destroy_reverts_original_earned_paid_leave_even_after_work_shift_changes(): void
    {
        $this->employee->workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);
        $this->employee->update([
            'leave_remain' => 0,
        ]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2025-10-25',
            'paid_leave' => 300,
        ]);

        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $this->employee->id,
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        $attendance = MonthlyAttendance::where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(1200, $attendance->earned_paid_leave);
        $this->assertSame(900, $this->employee->fresh()->leave_remain);

        // Changing the work shift's paid_leave later must not corrupt destroy() reversal
        $this->employee->workShift->update(['paid_leave' => 1500]);

        // Recalculating must also preserve the originally earned paid leave
        $this->post(route('attendance.monthly-attendances.recalculate', $attendance), [
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect();

        $recalculated = MonthlyAttendance::where('employee_id', $this->employee->id)->firstOrFail();
        $this->assertSame(1200, $recalculated->earned_paid_leave);
        $this->assertSame(900, $this->employee->fresh()->leave_remain);

        $this->delete(route('attendance.monthly-attendances.destroy', $recalculated))
            ->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(0, $this->employee->fresh()->leave_remain);
    }

    public function test_duplicate_store_does_not_double_accrue_paid_leave(): void
    {
        $this->employee->workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);
        $this->employee->update([
            'is_active' => true,
            'leave_remain' => 0,
        ]);

        $log = AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2025-10-25',
            'paid_leave' => 300,
        ]);

        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $this->employee->id,
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(900, $this->employee->fresh()->leave_remain);

        // Re-submitting the same month after paid_leave increased by 100 must only adjust by -100, not re-accrue 1200
        $log->update(['paid_leave' => 400]);

        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $this->employee->id,
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(800, $this->employee->fresh()->leave_remain);
    }

    public function test_duplicate_bulk_store_does_not_double_accrue_paid_leave(): void
    {
        $this->employee->workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);
        $this->employee->update([
            'is_active' => true,
            'leave_remain' => 0,
        ]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2025-10-25',
            'paid_leave' => 300,
        ]);

        $payload = [
            'employee_ids' => [$this->employee->id],
            'start_date' => '1404/08/01',
            'duration' => 30,
        ];

        $this->post(route('attendance.monthly-attendances.bulk-store'), $payload)->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(900, $this->employee->fresh()->leave_remain);

        $this->post(route('attendance.monthly-attendances.bulk-store'), $payload)->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(900, $this->employee->fresh()->leave_remain);
    }

    public function test_update_adjusts_leave_remain_by_paid_leave_delta(): void
    {
        $this->employee->update(['leave_remain' => 900]);
        $attendance = $this->makeMonthlyAttendance([
            'year' => 1404,
            'month' => 8,
            'start_date' => '2025-10-23',
            'duration' => 30,
            'paid_leave' => 300,
        ]);

        $this->put(route('attendance.monthly-attendances.update', $attendance), [
            'work_days' => 26,
            'present_days' => 25,
            'absent_days' => 1,
            'overtime' => 0,
            'mission' => 0,
            'paid_leave' => 450,
            'unpaid_leave' => 0,
            'friday' => 0,
            'holiday' => 0,
        ])->assertRedirect(route('attendance.monthly-attendances.show', $attendance));

        $this->assertSame(750, $this->employee->fresh()->leave_remain);
        $this->assertSame(450, $attendance->fresh()->paid_leave);
    }

    public function test_recalculate_adjusts_leave_remain_by_paid_leave_delta_without_reaccruing(): void
    {
        $this->employee->workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);
        $this->employee->update(['leave_remain' => 0]);

        $log = AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2025-10-25',
            'paid_leave' => 300,
        ]);

        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $this->employee->id,
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(900, $this->employee->fresh()->leave_remain);

        $attendance = MonthlyAttendance::where('employee_id', $this->employee->id)->firstOrFail();
        $log->update(['paid_leave' => 420]);

        $this->post(route('attendance.monthly-attendances.recalculate', $attendance), [
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect();

        $this->assertSame(780, $this->employee->fresh()->leave_remain);
    }

    public function test_remaining_paid_leave_including_current_month_for_employee_portal(): void
    {
        $fiscalYear = FiscalYear::findOrFail($this->fiscalYearId);
        $fiscalYear->update(['year' => 1404]);

        $this->employee->workShift->update(['paid_leave' => 1200]);
        $this->employee->update([
            'user_id' => $this->user->id,
            'is_active' => true,
            'contract_end_date' => null,
            'leave_remain' => 600,
        ]);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'employee-portal.*'])
        );

        try {
            // 2025-11-06 is 1404/08/15 (mid-Aban)
            Carbon::setTestNow(Carbon::create(2025, 11, 6, 12, 0, 0));

            // Before Aban's monthly attendance is created: 600 + 1200 = 1800
            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(1800).' '.__('Remaining Paid Leave'));

            // Once Aban's monthly attendance exists: do not double-count Aban's 1200
            $this->makeMonthlyAttendance([
                'year' => 1404,
                'month' => 8,
                'start_date' => '2025-10-23',
                'duration' => 30,
            ]);
            $this->employee->update(['leave_remain' => 1500]);
            $this->user->unsetRelation('employee');

            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(1500).' '.__('Remaining Paid Leave'));

            // Next month (1404/09/01 = 2025-11-22): Azar is not calculated yet => 1500 + 1200 = 2700
            Carbon::setTestNow(Carbon::create(2025, 11, 22, 12, 0, 0));

            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(2700).' '.__('Remaining Paid Leave'));

            // If employee's contract ended at end of Aban (2025-11-21): Azar is not included
            $this->employee->update(['contract_end_date' => '2025-11-21']);
            $this->user->unsetRelation('employee');

            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(1500).' '.__('Remaining Paid Leave'));

            // If employee is inactive: current month is not included
            $this->employee->update(['contract_end_date' => null, 'is_active' => false]);
            $this->user->unsetRelation('employee');

            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(1500).' '.__('Remaining Paid Leave'));

            // If viewing a closed fiscal year: current month is not included
            $this->employee->update(['is_active' => true]);
            $this->user->unsetRelation('employee');
            $fiscalYear->update(['closed_at' => now()]);

            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(1500).' '.__('Remaining Paid Leave'));

            // If viewing a past (non-current) open fiscal year: current month is not included
            $fiscalYear->update(['closed_at' => null, 'year' => 1403]);

            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(1500).' '.__('Remaining Paid Leave'));

            // Negative remaining paid leave renders signed duration instead of clamping to 0
            $fiscalYear->update(['closed_at' => null, 'year' => 1404]);
            $this->employee->update(['is_active' => true, 'leave_remain' => -1500]);
            $this->user->unsetRelation('employee');

            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(-300, true).' '.__('Remaining Paid Leave'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_legacy_pre_merge_monthly_attendance_preserves_existing_employee_leave_remain(): void
    {
        $this->employee->workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);

        // Existing employee with a carried-over balance from before #472 was merged
        $this->employee->update([
            'is_active' => true,
            'leave_remain' => 1800,
        ]);

        // Pre-merge MonthlyAttendance for Mehr 1404 (month 7) where earned_paid_leave is null
        $legacyLog = AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2025-09-25',
            'paid_leave' => 300,
        ]);

        $legacyAttendance = $this->makeMonthlyAttendance([
            'year' => 1404,
            'month' => 7,
            'start_date' => '2025-09-23',
            'duration' => 30,
            'paid_leave' => 300,
            'earned_paid_leave' => null,
        ]);

        // Re-submitting the pre-merge month with unchanged logs must not alter leave_remain or re-accrue
        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $this->employee->id,
            'start_date' => '1404/07/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(1800, $this->employee->fresh()->leave_remain);
        $this->assertNull($legacyAttendance->fresh()->earned_paid_leave);

        // Manually editing the pre-merge month's paid_leave only applies the delta (-150)
        $this->put(route('attendance.monthly-attendances.update', $legacyAttendance), [
            'work_days' => 26,
            'present_days' => 25,
            'absent_days' => 1,
            'overtime' => 0,
            'mission' => 0,
            'paid_leave' => 450,
            'unpaid_leave' => 0,
            'friday' => 0,
            'holiday' => 0,
        ])->assertRedirect(route('attendance.monthly-attendances.show', $legacyAttendance));

        $this->assertSame(1650, $this->employee->fresh()->leave_remain);

        // Recalculating the pre-merge month back to the log's 300 paid_leave restores +150 without re-accruing
        $legacyLog->update(['paid_leave' => 300]);
        $this->post(route('attendance.monthly-attendances.recalculate', $legacyAttendance), [
            'start_date' => '1404/07/01',
            'duration' => 30,
        ])->assertRedirect();

        $recalculated = MonthlyAttendance::where('employee_id', $this->employee->id)
            ->where('year', 1404)
            ->where('month', 7)
            ->firstOrFail();

        $this->assertSame(1800, $this->employee->fresh()->leave_remain);
        $this->assertNull($recalculated->earned_paid_leave);

        // Deleting a pre-merge record (earned_paid_leave = null) falls back to workShift->paid_leave: 1800 + 300 - 1200 = 900
        $this->delete(route('attendance.monthly-attendances.destroy', $recalculated))
            ->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(900, $this->employee->fresh()->leave_remain);
    }

    public function test_existing_employee_future_months_accrue_leave_remain_continuously_after_merge(): void
    {
        $fiscalYear = FiscalYear::findOrFail($this->fiscalYearId);
        $fiscalYear->update(['year' => 1404]);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'employee-portal.*'])
        );

        $workShift = $this->employee->workShift;
        $workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);

        // Existing employee with pre-merge balance (1500) and pre-merge Mehr 1404 attendance
        $this->employee->update([
            'user_id' => $this->user->id,
            'is_active' => true,
            'contract_end_date' => null,
            'leave_remain' => 1500,
        ]);

        $preMergeAttendance = $this->makeMonthlyAttendance([
            'year' => 1404,
            'month' => 7,
            'start_date' => '2025-09-23',
            'duration' => 30,
            'paid_leave' => 240,
            'earned_paid_leave' => null,
        ]);

        try {
            // Month 8 (Aban 1404): uses 300 -> 1500 + 1200 - 300 = 2400
            AttendanceLog::factory()->create([
                'fiscal_year_id' => $this->fiscalYearId,
                'employee_id' => $this->employee->id,
                'log_date' => '2025-10-25',
                'paid_leave' => 300,
            ]);

            $this->post(route('attendance.monthly-attendances.store'), [
                'employee_id' => $this->employee->id,
                'start_date' => '1404/08/01',
                'duration' => 30,
            ])->assertRedirect(route('attendance.monthly-attendances.index'));

            $this->assertSame(2400, $this->employee->fresh()->leave_remain);
            $this->assertSame(
                1200,
                MonthlyAttendance::where('employee_id', $this->employee->id)->where('month', 8)->value('earned_paid_leave')
            );
            $this->assertNull($preMergeAttendance->fresh()->earned_paid_leave);

            // Mid-Azar before Azar is calculated: portal displays 2400 + 1200 = 3600 without mutating DB balance
            Carbon::setTestNow(Carbon::create(2025, 11, 25, 12, 0, 0));
            $this->user->unsetRelation('employee');

            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(3600).' '.__('Remaining Paid Leave'));
            $this->assertSame(2400, $this->employee->fresh()->leave_remain);

            // Month 9 (Azar 1404) via bulkStore: uses 1500 -> 2400 + 1200 - 1500 = 2100
            AttendanceLog::factory()->create([
                'fiscal_year_id' => $this->fiscalYearId,
                'employee_id' => $this->employee->id,
                'log_date' => '2025-11-25',
                'paid_leave' => 1500,
            ]);

            $this->post(route('attendance.monthly-attendances.bulk-store'), [
                'employee_ids' => [$this->employee->id],
                'start_date' => '1404/09/01',
                'duration' => 30,
            ])->assertRedirect(route('attendance.monthly-attendances.index'));

            $this->assertSame(2100, $this->employee->fresh()->leave_remain);
            $this->assertSame(
                1200,
                MonthlyAttendance::where('employee_id', $this->employee->id)->where('month', 9)->value('earned_paid_leave')
            );

            // Now that Azar is calculated, portal displays 2100 (does not double-count Azar's quota)
            $this->user->unsetRelation('employee');
            $this->get(route('employee-portal.employee.show'))
                ->assertOk()
                ->assertSee(formatMinutesAsTime(2100).' '.__('Remaining Paid Leave'));

            // Month 10 (Dey 1404) after workShift paid_leave changes to 1500: uses 4000 -> 2100 + 1500 - 4000 = -400
            $workShift->update(['paid_leave' => 1500]);
            $this->assertSame(2100, $this->employee->fresh()->leave_remain);

            AttendanceLog::factory()->create([
                'fiscal_year_id' => $this->fiscalYearId,
                'employee_id' => $this->employee->id,
                'log_date' => '2025-12-25',
                'paid_leave' => 4000,
            ]);

            $this->post(route('attendance.monthly-attendances.store'), [
                'employee_id' => $this->employee->id,
                'start_date' => '1404/10/01',
                'duration' => 30,
            ])->assertRedirect(route('attendance.monthly-attendances.index'));

            $this->assertSame(-400, $this->employee->fresh()->leave_remain);
            $this->assertSame(
                1500,
                MonthlyAttendance::where('employee_id', $this->employee->id)->where('month', 10)->value('earned_paid_leave')
            );

            // Month 11 (Bahman 1404): uses 0 -> -400 + 1500 - 0 = 1100
            $this->post(route('attendance.monthly-attendances.store'), [
                'employee_id' => $this->employee->id,
                'start_date' => '1404/11/01',
                'duration' => 30,
            ])->assertRedirect(route('attendance.monthly-attendances.index'));

            $this->assertSame(1100, $this->employee->fresh()->leave_remain);
            $this->assertSame(
                1500,
                MonthlyAttendance::where('employee_id', $this->employee->id)->where('month', 11)->value('earned_paid_leave')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_new_employee_starts_at_zero_and_accrues_future_months_without_affecting_existing_employees(): void
    {
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'hr.employees.*'])
        );

        $workShift = $this->employee->workShift;
        $workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'paid_leave' => 1200,
        ]);

        // Existing employee's balance must remain untouched by new hires and their monthly calculations
        $this->employee->update([
            'is_active' => true,
            'leave_remain' => 2400,
        ]);

        $this->post(route('hr.employees.store'), [
            'code' => 'EMP-POST-MERGE',
            'first_name' => 'New',
            'last_name' => 'Hire',
            'nationality' => 'iranian',
            'work_site_id' => $this->employee->work_site_id,
            'work_shift_id' => $workShift->id,
            'is_active' => '1',
        ])->assertRedirect();

        $newEmployee = Employee::where('code', 'EMP-POST-MERGE')->firstOrFail();
        $this->assertSame(0, $newEmployee->leave_remain);
        $this->assertSame(2400, $this->employee->fresh()->leave_remain);

        // Month 8 (Aban 1404): uses 300 -> 0 + 1200 - 300 = 900
        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $newEmployee->id,
            'log_date' => '2025-10-25',
            'paid_leave' => 300,
        ]);
        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $newEmployee->id,
            'start_date' => '1404/08/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(900, $newEmployee->fresh()->leave_remain);

        // Month 9 (Azar 1404): uses 1200 -> 900 + 1200 - 1200 = 900
        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $newEmployee->id,
            'log_date' => '2025-11-25',
            'paid_leave' => 1200,
        ]);
        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $newEmployee->id,
            'start_date' => '1404/09/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(900, $newEmployee->fresh()->leave_remain);

        // Month 10 (Dey 1404): uses 600 -> 900 + 1200 - 600 = 1500
        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $newEmployee->id,
            'log_date' => '2025-12-25',
            'paid_leave' => 600,
        ]);
        $this->post(route('attendance.monthly-attendances.store'), [
            'employee_id' => $newEmployee->id,
            'start_date' => '1404/10/01',
            'duration' => 30,
        ])->assertRedirect(route('attendance.monthly-attendances.index'));

        $this->assertSame(1500, $newEmployee->fresh()->leave_remain);
        $this->assertSame(2400, $this->employee->fresh()->leave_remain);
    }

    public function test_cutover_migration_removes_prepaid_aban_quota_only_for_employees_without_aban_attendance(): void
    {
        $this->employee->workShift->update(['paid_leave' => 1200]);
        $this->employee->update(['leave_remain' => 1500]);

        $calculatedEmployee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $this->employee->work_site_id,
            'work_shift_id' => $this->employee->work_shift_id,
            'leave_remain' => 1500,
        ]);
        $this->makeMonthlyAttendance([
            'employee_id' => $calculatedEmployee->id,
            'year' => 1405,
            'month' => 8,
        ]);

        // Employee row of a closed fiscal year must never be rewritten by the cutover
        $closedFiscalYear = FiscalYear::factory()->create(['year' => 1404, 'closed_at' => now()]);
        $closedWorkShift = WorkShift::factory()->create([
            'fiscal_year_id' => $closedFiscalYear->id,
            'paid_leave' => 1200,
        ]);
        $closedYearEmployee = Employee::factory()->create([
            'fiscal_year_id' => $closedFiscalYear->id,
            'work_site_id' => $this->employee->work_site_id,
            'work_shift_id' => $closedWorkShift->id,
            'leave_remain' => 1500,
        ]);

        // Freeze the clock in Azar 1405 so the derived cutover month (Aban 1405) is deterministic
        Carbon::setTestNow(Carbon::create(2026, 11, 22, 12, 0, 0));

        try {
            $migration = require database_path('migrations/2026_11_22_000002_remove_prepaid_leave_for_uncalculated.php');
            $migration->up();
        } finally {
            Carbon::setTestNow();
        }

        // Aban 1405 not calculated yet: prepaid quota removed so the upcoming accrual does not double count it
        $this->assertSame(300, $this->employee->fresh()->leave_remain);
        // Aban 1405 already calculated by the old logic: untouched
        $this->assertSame(1500, $calculatedEmployee->fresh()->leave_remain);
        // Closed fiscal year: untouched
        $this->assertSame(1500, (int) Employee::withoutGlobalScopes()->whereKey($closedYearEmployee->id)->value('leave_remain'));
    }
}
