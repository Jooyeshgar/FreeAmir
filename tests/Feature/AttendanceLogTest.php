<?php

namespace Tests\Feature;

use App\Enums\AttendanceImportType;
use App\Enums\PersonnelRequestStatus;
use App\Enums\PersonnelRequestType;
use App\Enums\ThursdayStatus;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\PersonnelRequest;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\AttendanceLogImportService;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendanceLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $fiscalYearId;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $fiscalYear = FiscalYear::factory()->create();
        $this->fiscalYearId = $fiscalYear->id;

        $this->user = User::factory()->create();
        $fiscalYear->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'attendance.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => $this->fiscalYearId]);
        config(['active-fiscal-year-id' => $this->fiscalYearId]);

        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);

        $this->employee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite->id,
        ]);
    }

    private function makeAttendanceLog(array $overrides = []): AttendanceLog
    {
        return AttendanceLog::factory()->create(array_merge([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'log_date' => formatDate('2026-02-10'),
            'entry_time' => '08:00',
            'exit_time' => '17:00',
            'is_manual' => '0',
            'description' => null,
        ], $overrides);
    }

    public function test_work_shift_defaults_to_half_day_on_thursdays(): void
    {
        $workShift = WorkShift::factory()->create(['fiscal_year_id' => $this->fiscalYearId])->refresh();

        $this->assertSame(ThursdayStatus::HALF_DAY->value, $workShift->getRawOriginal('thursday_status'));
        $this->assertSame(ThursdayStatus::HALF_DAY, $workShift->thursday_status);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_attendance_logs(): void
    {
        $Date1 = '2025-02-10';
        $Date2 = '2025-02-11';

        $this->makeAttendanceLog(['log_date' => $Date1]);
        $this->makeAttendanceLog(['log_date' => $Date2]);

        $response = $this->get(route('attendance.attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertSee(formatDate($Date1));
        $response->assertSee(formatDate($Date2));
    }

    public function test_index_filters_by_employee(): void
    {
        $workSite2 = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $other = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite2->id,
        ]);

        $this->makeAttendanceLog(['employee_id' => $this->employee->id, 'log_date' => '2026-02-10']);
        $this->makeAttendanceLog(['employee_id' => $other->id, 'log_date' => '2026-02-11']);

        $response = $this->get(route('attendance.attendance-logs.index', ['employee_id' => $this->employee->id]));

        $response->assertStatus(200);
        $response->assertSee(formatDate('2026-02-10'));
        $response->assertDontSee(formatDate('2026-02-11'));
    }

    public function test_index_filters_by_date_range(): void
    {
        $this->makeAttendanceLog(['log_date' => '2026-01-05']);
        $this->makeAttendanceLog(['log_date' => '2026-02-10']);
        $this->makeAttendanceLog(['log_date' => '2026-03-20']);

        $response = $this->get(route('attendance.attendance-logs.index', [
            'date_from' => formatDate('2026-02-01'),
            'date_to' => formatDate('2026-02-28'),
        ]));

        $response->assertStatus(200);
        $response->assertSee(formatDate('2026-02-10'));
        $response->assertDontSee(formatDate('2026-01-05'));
        $response->assertDontSee(formatDate('2026-03-20'));
    }

    public function test_index_filters_by_is_manual(): void
    {
        $this->makeAttendanceLog(['log_date' => '2026-02-10', 'is_manual' => true]);
        $this->makeAttendanceLog(['log_date' => '2026-02-11', 'is_manual' => false]);

        $response = $this->get(route('attendance.attendance-logs.index', ['is_manual' => '1']));

        $response->assertStatus(200);
        $response->assertSee(formatDate('2026-02-10'));
        $response->assertDontSee(formatDate('2026-02-11'));
    }

    // ----------------------------------------------------------------
    // show authorization
    // ----------------------------------------------------------------

    public function test_user_with_any_hr_permission_can_view_another_employees_attendance_log(): void
    {
        $this->user->syncPermissions([
            Permission::firstOrCreate(['name' => 'attendance.attendance-logs.show']),
            Permission::firstOrCreate(['name' => 'hr.org-charts.index']),
        ]);
        $this->employee->update(['user_id' => $this->user->id]);

        $otherEmployee = Employee::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $log = $this->makeAttendanceLog(['employee_id' => $otherEmployee->id]);

        $this->get(route('attendance.attendance-logs.show', $log))->assertOk();
    }

    public function test_attendance_manager_can_view_another_employees_attendance_log(): void
    {
        $this->user->syncPermissions([
            Permission::firstOrCreate(['name' => 'attendance.attendance-logs.show']),
            Permission::firstOrCreate(['name' => 'attendance.attendance-logs.index']),
        ]);
        $this->employee->update(['user_id' => $this->user->id]);

        $otherEmployee = Employee::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $log = $this->makeAttendanceLog(['employee_id' => $otherEmployee->id]);

        $this->get(route('attendance.attendance-logs.show', $log))->assertOk();
    }

    public function test_employee_cannot_view_another_employees_attendance_log(): void
    {
        $this->user->syncPermissions([
            Permission::firstOrCreate(['name' => 'attendance.attendance-logs.show']),
        ]);
        $this->employee->update(['user_id' => $this->user->id]);

        $otherEmployee = Employee::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $log = $this->makeAttendanceLog(['employee_id' => $otherEmployee->id]);

        $this->get(route('attendance.attendance-logs.show', $log))->assertForbidden();
    }

    public function test_employee_can_view_their_own_attendance_log(): void
    {
        $this->user->syncPermissions([
            Permission::firstOrCreate(['name' => 'attendance.attendance-logs.show']),
        ]);
        $this->employee->update(['user_id' => $this->user->id]);
        $log = $this->makeAttendanceLog();

        $this->get(route('attendance.attendance-logs.show', $log))->assertOk();
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('attendance.attendance-logs.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_an_attendance_log_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('attendance.attendance-logs.store'), $payload);

        $response->assertRedirect(route('attendance.attendance-logs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-10',
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('attendance.attendance-logs.store'), []);

        $response->assertSessionHasErrors(['employee_id', 'log_date']);
    }

    public function test_store_rejects_invalid_date(): void
    {
        $response = $this->post(route('attendance.attendance-logs.store'), $this->validPayload([
            'log_date' => 'not-a-date',
        ]));

        $response->assertSessionHasErrors(['log_date']);
    }

    public function test_store_rejects_exit_time_before_entry_time(): void
    {
        $response = $this->post(route('attendance.attendance-logs.store'), $this->validPayload([
            'entry_time' => '17:00',
            'exit_time' => '08:00',
        ]));

        $response->assertSessionHasErrors(['exit_time']);
    }

    public function test_store_rejects_nonexistent_employee(): void
    {
        $response = $this->post(route('attendance.attendance-logs.store'), $this->validPayload([
            'employee_id' => 99999,
        ]));

        $response->assertSessionHasErrors(['employee_id']);
    }

    public function test_store_allows_null_entry_and_exit_times(): void
    {
        $payload = $this->validPayload([
            'entry_time' => '',
            'exit_time' => '',
        ]);

        $response = $this->post(route('attendance.attendance-logs.store'), $payload);

        $response->assertRedirect(route('attendance.attendance-logs.index'));
        $response->assertSessionHas('success');
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $log = $this->makeAttendanceLog();

        $response = $this->get(route('attendance.attendance-logs.edit', $log));

        $response->assertStatus(200);
        $response->assertSee(convertToJalali($log->log_date));
    }

    public function test_update_modifies_log_and_redirects(): void
    {
        $log = $this->makeAttendanceLog(['log_date' => '2026-02-10', 'entry_time' => '08:00']);

        // Set the previous URL using ->from(...) so redirect()->back() works as expected
        $response = $this->from(route('attendance.attendance-logs.index'))
            ->put(route('attendance.attendance-logs.update', $log), $this->validPayload([
                'entry_time' => '09:00',
                'is_manual' => '1',
            ]));

        $response->assertRedirect(route('attendance.attendance-logs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_logs', [
            'id' => $log->id,
            'entry_time' => '09:00:00',
            'is_manual' => true,
        ]);
    }

    public function test_update_converts_changed_jalali_date_to_gregorian_for_storage(): void
    {
        $log = $this->makeAttendanceLog(['log_date' => '2026-02-10']);
        $changedDate = convertToJalali('2026-03-15', true);

        $response = $this->put(
            route('attendance.attendance-logs.update', $log),
            $this->validPayload(['log_date' => $changedDate])
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_logs', [
            'id' => $log->id,
            'log_date' => '2026-03-15',
        ]);
    }

    public function test_update_keeps_changed_jalali_date_as_old_input_when_validation_fails(): void
    {
        $log = $this->makeAttendanceLog(['log_date' => '2026-02-10']);
        $changedDate = convertToJalali('2026-03-15', true);

        $response = $this->from(route('attendance.attendance-logs.edit', $log))
            ->put(route('attendance.attendance-logs.update', $log), $this->validPayload([
                'log_date' => $changedDate,
                'entry_time' => '17:00',
                'exit_time' => '08:00',
            ]));

        $response->assertSessionHasErrors('exit_time');
        $response->assertSessionHasInput('log_date', $changedDate);
    }

    public function test_update_validates_required_fields(): void
    {
        $log = $this->makeAttendanceLog();

        $response = $this->put(route('attendance.attendance-logs.update', $log), []);

        $response->assertSessionHasErrors(['employee_id', 'log_date']);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_log_and_redirects(): void
    {
        $log = $this->makeAttendanceLog();

        $response = $this->delete(route('attendance.attendance-logs.destroy', $log));

        $response->assertRedirect(route('attendance.attendance-logs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('attendance_logs', ['id' => $log->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('attendance.attendance-logs.index'));

        $response->assertRedirect(route('login'));
    }

    // ----------------------------------------------------------------
    // bulk import — recalculate per created row
    // ----------------------------------------------------------------

    private function makeTsvContent(string $deviceId, string $date, string $entryTime, string $exitTime): string
    {
        $checkIn = "{$date} {$entryTime}:00";
        $checkOut = "{$date} {$exitTime}:00";

        return implode("\n", [
            implode("\t", [$deviceId, $checkIn, '0', '0', '0', '0']),
            implode("\t", [$deviceId, $checkOut, '0', '1', '0', '0']),
        ]);
    }

    public function test_import_recalculates_each_created_attendance_log(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break' => 60,
            'float' => 0,
            'max_auto_overtime' => 0,
        ]);

        $deviceId = 'DEVICE-001';
        $this->employee->update([
            'device_id' => $deviceId,
            'work_shift_id' => $workShift->id,
        ]);

        $logDate = '2026-02-10'; // not a Friday
        $tsv = $this->makeTsvContent($deviceId, $logDate, '08:00', '17:00');

        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $result = app(AttendanceLogImportService::class)->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId);

        $this->assertEquals(1, $result['imported']);

        $log = AttendanceLog::where('employee_id', $this->employee->id)
            ->where('log_date', $logDate)
            ->first();

        $this->assertNotNull($log);
        // worked = raw clock-on time: 17:00 - 08:00 = 540 minutes (break not deducted by the service)
        $this->assertEquals(540, $log->worked);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    // ----------------------------------------------------------------
    // bulk create
    // ----------------------------------------------------------------

    private function validBulkPayload(array $overrides = []): array
    {
        // Jalali 1404/11/12 = Gregorian 2026-02-01 (Sunday)
        // 29-day window covers Feb 1 2026 – Mar 1 2026 (Feb has 28 days in 2026)
        // Fridays: Feb 6, 13, 20, 27  |  Thursdays: Feb 5, 12, 19, 26  |  Mar 1 = Sunday
        return array_merge([
            'employee_ids' => [$this->employee->id],
            'start_date' => '1404/11/12',
            'duration' => 29,
        ], $overrides);
    }

    public function test_bulk_create_returns_200(): void
    {
        $response = $this->get(route('attendance.attendance-logs.bulk-create'));

        $response->assertStatus(200);
    }

    public function test_bulk_search_employee_returns_only_matching_active_employees(): void
    {
        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $active = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite->id,
            'first_name' => 'Zahra',
            'last_name' => 'Karimi',
            'is_active' => true,
        ]);
        $inactive = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite->id,
            'first_name' => 'Zahra',
            'last_name' => 'Inactive',
            'is_active' => false,
        ]);

        $response = $this->getJson(route('attendance.attendance-logs.search-employee', ['q' => 'Zahra']));

        $response->assertStatus(200);
        $ids = collect($response->json('0.options.0'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_bulk_store_creates_logs_for_each_selected_employee(): void
    {
        $workSite2 = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $employee2 = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite2->id,
        ]);

        $response = $this->post(
            route('attendance.attendance-logs.bulk-store'),
            $this->validBulkPayload(['employee_ids' => [$this->employee->id, $employee2->id]])
        );

        $response->assertRedirect(route('attendance.attendance-logs.index'));
        $response->assertSessionHas('success');

        // Monday 2026-02-02 is a regular workday — log must exist for both
        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $employee2->id,
            'log_date' => '2026-02-02',
        ]);
    }

    public function test_bulk_store_only_creates_for_selected_employees(): void
    {
        $workSite2 = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $unselected = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite2->id,
        ]);

        $this->post(
            route('attendance.attendance-logs.bulk-store'),
            $this->validBulkPayload(['employee_ids' => [$this->employee->id]])
        );

        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
        ]);
        $this->assertDatabaseMissing('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $unselected->id,
        ]);
    }

    public function test_bulk_store_overwrites_existing_log_with_shift_times_when_override_enabled(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '09:30:00',
            'exit_time' => '18:30:00',
        ]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload(['override' => '1']));

        // Existing log must be overwritten with shift times
        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
        ]);
        // Still only one row for that day
        $this->assertSame(
            1,
            AttendanceLog::where('employee_id', $this->employee->id)->where('log_date', '2026-02-02')->count()
        );
    }

    public function test_bulk_store_keeps_existing_log_when_override_disabled(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '09:30:00',
            'exit_time' => '18:30:00',
        ]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // Existing log must be left untouched (no override)
        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '09:30:00',
            'exit_time' => '18:30:00',
        ]);
        $this->assertSame(
            1,
            AttendanceLog::where('employee_id', $this->employee->id)->where('log_date', '2026-02-02')->count()
        );
    }

    public function test_bulk_create_lists_only_active_employees(): void
    {
        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $active = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite->id,
            'first_name' => 'Active',
            'last_name' => 'Person',
            'is_active' => true,
        ]);
        $inactive = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite->id,
            'first_name' => 'Inactive',
            'last_name' => 'Person',
            'is_active' => false,
        ]);

        $response = $this->get(route('attendance.attendance-logs.bulk-create'));

        $response->assertStatus(200);
        $response->assertSee('Active Person');
        $response->assertDontSee('Inactive Person');
    }

    public function test_bulk_store_rejects_inactive_employee_id(): void
    {
        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $inactive = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite->id,
            'is_active' => false,
        ]);

        $response = $this->post(
            route('attendance.attendance-logs.bulk-store'),
            $this->validBulkPayload(['employee_ids' => [$inactive->id]])
        );

        $response->assertSessionHasErrors(['employee_ids.0']);
        $this->assertDatabaseMissing('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $inactive->id,
        ]);
    }

    public function test_bulk_store_creates_one_log_per_workday_for_the_whole_window(): void
    {
        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // Feb 1 2026 – Mar 1 2026 has 4 Fridays (6, 13, 20, 27); no holidays; no shift => Thursdays worked.
        // 29 days − 4 Fridays = 25 worked days.
        $this->assertSame(
            25,
            AttendanceLog::where('employee_id', $this->employee->id)->count()
        );
        // Each created log carries the default shift times.
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
            'is_manual' => false,
        ]);
    }

    public function test_bulk_store_without_override_keeps_existing_and_fills_missing_days(): void
    {
        // Pre-existing manual log on Monday 2026-02-02
        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '09:30:00',
            'exit_time' => '18:30:00',
            'is_manual' => true,
        ]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // Existing day is untouched...
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '09:30:00',
            'exit_time' => '18:30:00',
            'is_manual' => true,
        ]);
        // ...while the remaining workdays are filled with shift times.
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-03',
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
            'is_manual' => false,
        ]);
        // Total still equals the 25 worked days (1 kept + 24 created).
        $this->assertSame(
            25,
            AttendanceLog::where('employee_id', $this->employee->id)->count()
        );
    }

    public function test_bulk_store_with_override_overwrites_existing_and_creates_missing_days(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '09:30:00',
            'exit_time' => '18:30:00',
            'is_manual' => true,
        ]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload(['override' => '1']));

        // Existing day overwritten with shift times and reset to non-manual.
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
            'is_manual' => false,
        ]);
        $this->assertSame(
            25,
            AttendanceLog::where('employee_id', $this->employee->id)->count()
        );
    }

    public function test_bulk_store_applies_independently_per_employee_with_override(): void
    {
        $workSite2 = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $employee2 = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite2->id,
        ]);

        // Only employee1 has a pre-existing log on 2026-02-02.
        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '09:30:00',
            'exit_time' => '18:30:00',
        ]);

        $this->post(
            route('attendance.attendance-logs.bulk-store'),
            $this->validBulkPayload(['employee_ids' => [$this->employee->id, $employee2->id], 'override' => '1'])
        );

        // Both employees end up with the same number of worked days.
        $this->assertSame(25, AttendanceLog::where('employee_id', $this->employee->id)->count());
        $this->assertSame(25, AttendanceLog::where('employee_id', $employee2->id)->count());
        // Employee1's existing day was overwritten.
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
        ]);
    }

    public function test_bulk_store_skips_fridays(): void
    {
        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // 2026-02-06 is a Friday — no log
        $this->assertDatabaseMissing('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-06',
        ]);
        // 2026-02-02 is a Monday — log exists
        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
        ]);
    }

    public function test_bulk_store_skips_public_holidays(): void
    {
        // Mark Tuesday 2026-02-03 as a public holiday
        PublicHoliday::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'date' => '2026-02-03',
        ]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        $this->assertDatabaseMissing('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-03',
        ]);
    }

    public function test_bulk_store_skips_thursday_when_shift_is_holiday(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'thursday_status' => ThursdayStatus::HOLIDAY,
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // 2026-02-05 is a Thursday — skipped because shift marks it as holiday
        $this->assertDatabaseMissing('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-05',
        ]);
    }

    public function test_bulk_store_creates_thursday_log_when_shift_is_full_day(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'thursday_status' => ThursdayStatus::FULL_DAY,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-05',
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
        ]);
    }

    public function test_bulk_store_uses_thursday_exit_time_for_half_day(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'thursday_status' => ThursdayStatus::HALF_DAY,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'thursday_exit_time' => '12:00:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // Thursday gets early exit per shift config
        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-05',
            'entry_time' => '08:00:00',
            'exit_time' => '12:00:00',
        ]);
    }

    public function test_bulk_store_uses_shift_times_for_entry_and_exit(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:30:00',
            'end_time' => '16:30:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        $this->assertDatabaseHas('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '08:30:00',
            'exit_time' => '16:30:00',
        ]);
    }

    // ----------------------------------------------------------------
    // bulk store — validation
    // ----------------------------------------------------------------

    public function test_bulk_store_validates_employee_ids_required(): void
    {
        $response = $this->post(
            route('attendance.attendance-logs.bulk-store'),
            ['start_date' => formatDate('2026-02-01'), 'duration' => 29]
        );

        $response->assertSessionHasErrors(['employee_ids']);
    }

    public function test_bulk_store_validates_start_date_required(): void
    {
        $response = $this->post(
            route('attendance.attendance-logs.bulk-store'),
            ['employee_ids' => [$this->employee->id], 'duration' => 29]
        );

        $response->assertSessionHasErrors(['start_date']);
    }

    public function test_bulk_store_validates_duration_range(): void
    {
        $response = $this->post(
            route('attendance.attendance-logs.bulk-store'),
            $this->validBulkPayload(['duration' => 10])
        );

        $response->assertSessionHasErrors(['duration']);
    }

    public function test_bulk_store_rejects_nonexistent_employee_id(): void
    {
        $response = $this->post(
            route('attendance.attendance-logs.bulk-store'),
            $this->validBulkPayload(['employee_ids' => [99999]])
        );

        $response->assertSessionHasErrors(['employee_ids.0']);
    }

    public function test_import_fills_missing_times_in_ignore_mode(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break' => 60,
            'float' => 0,
            'max_auto_overtime' => 0,
        ]);

        $deviceId = 'DEVICE-002';
        $this->employee->update([
            'device_id' => $deviceId,
            'work_shift_id' => $workShift->id,
        ]);

        $logDate = '2026-02-11';
        AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'log_date' => $logDate,
            'worked' => 999,
            'entry_time' => null,
            'exit_time' => null,
        ]);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '08:00', '17:00');

        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $result = app(AttendanceLogImportService::class)->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'log_date' => $logDate,
            'worked' => 540,
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
        ]);
    }

    public function test_import_fills_times_for_log_created_by_approved_hourly_leave(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 30,
            'float' => 60,
            'max_auto_overtime' => 60,
        ]);

        $deviceId = 'DEVICE-002';
        $this->employee->update(['device_id' => $deviceId, 'work_shift_id' => $workShift->id]);

        $logDate = '2026-02-11';
        $personnelRequest = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::PENDING,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 11:00:00',
            'end_date' => $logDate.' 13:00:00',
        ]);

        $personnelRequest->update([
            'status' => PersonnelRequestStatus::APPROVED,
            'approved_by' => auth()->user()->id,
        ]);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->syncPersonnelRequestLogs($personnelRequest);

        $log = AttendanceLog::query()->first();
        $this->assertNotNull($log);
        $this->assertNull($log->entry_time);
        $this->assertNull($log->exit_time);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '08:00', '16:00');

        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'log_date' => $logDate,
            'entry_time' => '08:00:00',
            'exit_time' => '16:00:00',
        ]);
    }

    public function test_import_calculates_times_correctly_with_late_entry_overtime_and_hourly_leave_after_shift(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 30,
            'float' => 60,
            'max_auto_overtime' => 60,
        ]);

        $deviceId = 'DEVICE-002';
        $this->employee->update(['device_id' => $deviceId, 'work_shift_id' => $workShift->id]);

        $logDate = '2026-02-11';
        $personnelRequest = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::PENDING,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 16:45:00',
            'end_date' => $logDate.' 17:00:00',
        ]);

        $personnelRequest->update([
            'status' => PersonnelRequestStatus::APPROVED,
            'approved_by' => auth()->user()->id,
        ]);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->syncPersonnelRequestLogs($personnelRequest);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '08:45', '16:45');

        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);

        $log = AttendanceLog::query()->where('employee_id', $this->employee->id)->where('log_date', $logDate)->first();

        $this->assertNotNull($log);
        $this->assertEquals('08:45:00', $log->entry_time);
        $this->assertEquals('16:45:00', $log->exit_time);
        $this->assertEquals(15, $log->delay);
        $this->assertEquals(30, $log->overtime + $log->auto_overtime);
        $this->assertEquals(15, $log->paid_leave);

        // ----------------------------------------------------

        $logDate2 = '2026-02-12';
        $personnelRequest_2 = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::PENDING,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate2.' 16:30:00',
            'end_date' => $logDate2.' 17:00:00',
        ]);

        $personnelRequest_2->update([
            'status' => PersonnelRequestStatus::APPROVED,
            'approved_by' => auth()->user()->id,
        ]);

        $attendanceService->syncPersonnelRequestLogs($personnelRequest_2);
        $tsv = $this->makeTsvContent($deviceId, $logDate2, '08:52', '16:30');

        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);

        $log2 = AttendanceLog::query()->where('employee_id', $this->employee->id)->where('log_date', $logDate2)->first();

        $this->assertNotNull($log2);
        $this->assertEquals('08:52:00', $log2->entry_time);
        $this->assertEquals('16:30:00', $log2->exit_time);
        $this->assertEquals(22, $log2->delay);
        $this->assertEquals(30, $log2->overtime + $log2->auto_overtime);
        $this->assertEquals(30, $log2->paid_leave);

        // ----------------------------------------------------

        $logDate3 = '2026-02-14';
        $personnelRequest_3 = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::PENDING,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate3.' 16:32:00',
            'end_date' => $logDate3.' 17:00:00',
        ]);

        $personnelRequest_3->update([
            'status' => PersonnelRequestStatus::APPROVED,
            'approved_by' => auth()->user()->id,
        ]);

        $attendanceService->syncPersonnelRequestLogs($personnelRequest_3);
        $tsv = $this->makeTsvContent($deviceId, $logDate3, '08:58', '16:38');

        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);

        $log3 = AttendanceLog::query()->where('employee_id', $this->employee->id)->where('log_date', $logDate3)->first();

        $this->assertNotNull($log3);
        $this->assertEquals('08:58:00', $log3->entry_time);
        $this->assertEquals('16:38:00', $log3->exit_time);
        $this->assertEquals(28, $log3->delay);
        $this->assertEquals(30, $log3->overtime + $log3->auto_overtime);
        $this->assertEquals(28, $log3->paid_leave);
    }

    public function test_recalculation_applies_hourly_leave_at_shift_start_without_delay_or_early_leave(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 30,
            'float' => 60,
            'max_auto_overtime' => 60,
        ]);

        $deviceId = 'DEVICE-002';
        $this->employee->update(['device_id' => $deviceId, 'work_shift_id' => $workShift->id]);

        $logDate = '2026-02-11';
        $personnelRequest = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::PENDING,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 07:30:00',
            'end_date' => $logDate.' 10:00:00',
        ]);

        $personnelRequest->update(['status' => PersonnelRequestStatus::APPROVED, 'approved_by' => auth()->user()->id]);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->syncPersonnelRequestLogs($personnelRequest);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '09:58', '16:19');
        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);

        $log = AttendanceLog::query()->where('employee_id', $this->employee->id)->where('log_date', $logDate)->firstOrFail();

        $this->assertEquals('09:58:00', $log->entry_time);
        $this->assertEquals('16:19:00', $log->exit_time);
        $this->assertEquals(51, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_recalculation_applies_hourly_leave_until_entry_and_calculates_overtime(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 30,
            'float' => 60,
            'max_auto_overtime' => 60,
        ]);

        $deviceId = 'DEVICE-002';
        $this->employee->update(['device_id' => $deviceId, 'work_shift_id' => $workShift->id]);

        $logDate = '2026-02-11';
        $personnelRequest = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::PENDING,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 08:00:00',
            'end_date' => $logDate.' 10:02:00',
        ]);

        $personnelRequest->update([
            'status' => PersonnelRequestStatus::APPROVED,
            'approved_by' => auth()->user()->id,
        ]);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->syncPersonnelRequestLogs($personnelRequest);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '10:02', '16:28');
        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);

        $log = AttendanceLog::query()->where('employee_id', $this->employee->id)->where('log_date', $logDate)->firstOrFail();

        $this->assertEquals('10:02:00', $log->entry_time);
        $this->assertEquals('16:28:00', $log->exit_time);
        $this->assertEquals(28, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_hourly_leave_before_entry_uses_remaining_float_without_early_leave(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 30,
            'float' => 60,
            'max_auto_overtime' => 60,
        ]);

        $deviceId = 'DEVICE-002';
        $this->employee->update(['device_id' => $deviceId, 'work_shift_id' => $workShift->id]);

        $logDate = '2026-07-20'; // 1405/04/29
        $personnelRequest = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 07:30:00',
            'end_date' => $logDate.' 11:19:00',
            'approved_by' => auth()->user()->id,
        ]);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->syncPersonnelRequestLogs($personnelRequest);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '11:52', '16:03');
        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);

        $log = AttendanceLog::query()->where('employee_id', $this->employee->id)->where('log_date', $logDate)->firstOrFail();

        $this->assertEquals(229, $log->paid_leave);
        $this->assertEquals(27, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_hourly_leave_within_shift_with_float_calculates_paid_leave_and_no_delay(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 30,
            'float' => 60,
            'max_auto_overtime' => 60,
        ]);

        $deviceId = 'DEVICE-002';
        $this->employee->update(['device_id' => $deviceId, 'work_shift_id' => $workShift->id]);
        $attendanceService = app(AttendanceService::class);

        $logDate = '2026-08-19'; // 1405/05/28
        $personnelRequest_1 = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 08:15:00',
            'end_date' => $logDate.' 08:47:00',
            'approved_by' => auth()->user()->id,
        ]);
        $attendanceService->syncPersonnelRequestLogs($personnelRequest_1);

        $personnelRequest_2 = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 10:20:00',
            'end_date' => $logDate.' 11:00:00',
            'approved_by' => auth()->user()->id,
        ]);

        $attendanceService->syncPersonnelRequestLogs($personnelRequest_2);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '08:47', '16:15');
        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');

        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);

        $log = AttendanceLog::query()->where('employee_id', $this->employee->id)->where('log_date', $logDate)->firstOrFail();

        $this->assertEquals(72, $log->paid_leave);
        $this->assertEquals(0, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_hourly_leave_at_shift_start_with_overtime_calculation(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 30,
            'float' => 60,
            'max_auto_overtime' => 120,
        ]);

        $deviceId = 'DEVICE-002';
        $this->employee->update(['device_id' => $deviceId, 'work_shift_id' => $workShift->id]);

        $logDate = '2026-07-25'; // 1405/05/03
        $personnelRequest = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 07:30:00',
            'end_date' => $logDate.' 10:35:00',
            'approved_by' => auth()->user()->id,
        ]);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->syncPersonnelRequestLogs($personnelRequest);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '10:35', '16:38');
        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');
        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);

        $log = AttendanceLog::query()->where('employee_id', $this->employee->id)->where('log_date', $logDate)->firstOrFail();

        $this->assertEquals(185, $log->paid_leave);
        $this->assertEquals(68, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_hourly_leave_with_float_covers_delay_without_overtime(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 30,
            'float' => 60,
            'max_auto_overtime' => 120,
        ]);

        $deviceId = 'DEVICE-002';
        $this->employee->update(['device_id' => $deviceId, 'work_shift_id' => $workShift->id]);

        $logDate = '2026-08-05'; // 1405/05/14
        $personnelRequest = PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 08:13:00',
            'end_date' => $logDate.' 09:00:00',
            'approved_by' => auth()->user()->id,
        ]);

        $attendanceService = app(AttendanceService::class);
        $attendanceService->syncPersonnelRequestLogs($personnelRequest);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '09:00', '16:13');
        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        $attendanceLogImport = app(AttendanceLogImportService::class);
        $result = $attendanceLogImport->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->fiscalYearId, null, null, 'ignore');
        $this->assertEquals(1, $result['imported']);
        $this->assertEquals(0, $result['skipped']);

        $log = AttendanceLog::query()->where('employee_id', $this->employee->id)->where('log_date', $logDate)->firstOrFail();

        $this->assertEquals(47, $log->paid_leave);
        $this->assertEquals(0, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_recalculate_hourly_leave_before_entry_uses_remaining_float_without_early_leave(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00', 'end_time' => '15:30:00',
            'break' => 30, 'float' => 60, 'max_auto_overtime' => 60,
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);
        $logDate = '2026-07-20';
        PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId, 'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED, 'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 07:30:00', 'end_date' => $logDate.' 11:19:00',
            'approved_by' => auth()->id(),
        ]);
        $log = $this->makeAttendanceLog([
            'log_date' => $logDate, 'entry_time' => '11:52', 'exit_time' => '16:03', 'paid_leave' => 0,
        ]);

        $response = $this->post(route('attendance.attendance-logs.recalculate', $log));
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $log->refresh();

        $this->assertEquals(229, $log->paid_leave);
        $this->assertEquals(27, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_recalculate_hourly_leave_within_shift_calculates_paid_leave_and_no_delay(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00', 'end_time' => '15:30:00',
            'break' => 30, 'float' => 60, 'max_auto_overtime' => 60,
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);
        $logDate = '2026-08-19';
        foreach ([['08:15:00', '08:47:00'], ['10:20:00', '11:00:00']] as [$start, $end]) {
            PersonnelRequest::create([
                'fiscal_year_id' => $this->fiscalYearId, 'employee_id' => $this->employee->id,
                'status' => PersonnelRequestStatus::APPROVED, 'request_type' => PersonnelRequestType::LEAVE_HOURLY,
                'start_date' => $logDate.' '.$start, 'end_date' => $logDate.' '.$end,
                'approved_by' => auth()->id(),
            ]);
        }
        $log = $this->makeAttendanceLog([
            'log_date' => $logDate, 'entry_time' => '08:47', 'exit_time' => '16:15', 'paid_leave' => 0,
        ]);

        $response = $this->post(route('attendance.attendance-logs.recalculate', $log));
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $log->refresh();

        $this->assertEquals(72, $log->paid_leave);
        $this->assertEquals(0, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_recalculate_hourly_leave_at_shift_start_calculates_overtime(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00', 'end_time' => '15:30:00',
            'break' => 30, 'float' => 60, 'max_auto_overtime' => 120,
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);
        $logDate = '2026-07-25';
        PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId, 'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED, 'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 07:30:00', 'end_date' => $logDate.' 10:35:00',
            'approved_by' => auth()->id(),
        ]);
        $log = $this->makeAttendanceLog([
            'log_date' => $logDate, 'entry_time' => '10:35', 'exit_time' => '16:38', 'paid_leave' => 0,
        ]);

        $response = $this->post(route('attendance.attendance-logs.recalculate', $log));
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $log->refresh();

        $this->assertEquals(185, $log->paid_leave);
        $this->assertEquals(68, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_recalculate_hourly_leave_with_float_covers_delay_without_overtime(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00', 'end_time' => '15:30:00',
            'break' => 30, 'float' => 60, 'max_auto_overtime' => 120,
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);
        $logDate = '2026-08-05';
        PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId, 'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED, 'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 08:13:00', 'end_date' => $logDate.' 09:00:00',
            'approved_by' => auth()->id(),
        ]);
        $log = $this->makeAttendanceLog([
            'log_date' => $logDate, 'entry_time' => '09:00', 'exit_time' => '16:13', 'paid_leave' => 0,
        ]);

        $response = $this->post(route('attendance.attendance-logs.recalculate', $log));
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $log->refresh();

        $this->assertEquals(47, $log->paid_leave);
        $this->assertEquals(0, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_recalculate_hourly_leave_after_shift_start_calculates_overtime(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00', 'end_time' => '15:30:00',
            'break' => 30, 'float' => 60, 'max_auto_overtime' => 120,
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);
        $logDate = '2026-08-16';
        PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId, 'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED, 'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 08:14:00', 'end_date' => $logDate.' 09:50:00',
            'approved_by' => auth()->id(),
        ]);
        $log = $this->makeAttendanceLog([
            'log_date' => $logDate, 'entry_time' => '09:50', 'exit_time' => '16:14',
        ]);

        $response = $this->post(route('attendance.attendance-logs.recalculate', $log));
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $log->refresh();

        $this->assertEquals(96, $log->paid_leave);
        $this->assertEquals(0, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_recalculate_hourly_leave_from_shift_start_calculates_overtime(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00', 'end_time' => '15:30:00',
            'break' => 30, 'float' => 60, 'max_auto_overtime' => 120,
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);
        $logDate = '2026-08-19';
        PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId, 'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED, 'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 07:30:00', 'end_date' => $logDate.' 10:53:00',
            'approved_by' => auth()->id(),
        ]);
        $log = $this->makeAttendanceLog([
            'log_date' => $logDate, 'entry_time' => '10:53', 'exit_time' => '16:34',
        ]);

        $response = $this->post(route('attendance.attendance-logs.recalculate', $log));
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $log->refresh();

        $this->assertEquals(203, $log->paid_leave);
        $this->assertEquals(64, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_attendance_service_recalculates_hourly_leave_from_shift_start_with_overtime(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '07:30:00',
            'end_time' => '15:30:00',
            'break' => 30,
            'float' => 60,
            'max_auto_overtime' => 120,
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        $logDate = '2026-08-19';
        PersonnelRequest::create([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'status' => PersonnelRequestStatus::APPROVED,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $logDate.' 07:30:00',
            'end_date' => $logDate.' 10:53:00',
            'approved_by' => auth()->id(),
        ]);
        $log = $this->makeAttendanceLog([
            'log_date' => $logDate,
            'entry_time' => '10:53',
            'exit_time' => '16:34',
        ]);

        $log = app(AttendanceService::class)->recalculateLog($log);

        $this->assertEquals(203, $log->paid_leave);
        $this->assertEquals(64, $log->auto_overtime + $log->overtime);
        $this->assertEquals(0, $log->delay);
        $this->assertEquals(0, $log->early_leave);
    }

    public function test_import_uses_file_order_for_multiple_punches_and_monthly_calculation_reads_stored_log(): void
    {
        $workShift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'float' => 60,
            'max_auto_overtime' => 120,
            'thursday_status' => ThursdayStatus::FULL_DAY,
        ]);
        $this->employee->update(['device_id' => 'DEVICE-MULTI', 'work_shift_id' => $workShift->id]);
        $date = '2026-02-07';
        $content = implode("\n", [
            "DEVICE-MULTI\t{$date} 08:00:00\t0\t0\t0\t0",
            "DEVICE-MULTI\t{$date} 16:00:00\t0\t1\t0\t0",
            "DEVICE-MULTI\t{$date} 12:00:00\t0\t0\t0\t0",
            "DEVICE-MULTI\t{$date} 13:00:00\t0\t1\t0\t0",
        ]);

        $result = app(AttendanceLogImportService::class)->import(
            UploadedFile::fake()->createWithContent('attendance.tsv', $content),
            AttendanceImportType::DeviceTsv,
            $this->fiscalYearId,
        );

        $this->assertSame(1, $result['imported']);
        $log = AttendanceLog::where('employee_id', $this->employee->id)->where('log_date', $date)->firstOrFail();
        $this->assertSame('12:00:00', $log->entry_time);
        $this->assertSame('13:00:00', $log->exit_time);

        $monthly = app(AttendanceService::class)->calculateAndStore($this->employee->id, Carbon::parse($date), 28, 1404, 11);
        $this->assertSame(1, $monthly->present_days);
        $this->assertSame(23, $monthly->absent_days);
        $this->assertSame($monthly->id, $log->fresh()->monthly_attendance_id);
    }

    public function test_tsv_to_monthly_with_on_time_without_deducting_break_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 0, 480, [], [480, 0, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_early_arrival_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, -30, 480, [], [510, 0, 0, 30]);
        }
    }

    public function test_tsv_to_monthly_with_within_float_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 30, 510, [], [480, 0, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_at_float_boundary_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 60, 540, [], [480, 0, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_after_float_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 90, 540, [], [450, 30, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_early_departure_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 30, 480, [], [450, 0, 30, 0]);
        }
    }

    public function test_tsv_to_monthly_with_late_departure_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 30, 555, [], [525, 0, 0, 45]);
        }
    }

    public function test_tsv_to_monthly_with_automatic_overtime_cap_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, -60, 660, [], [720, 0, 0, 120]);
        }
    }

    public function test_tsv_to_monthly_with_paid_leave_inside_attendance_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 0, 480, [['paid', 180, 240]], [420, 0, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_unpaid_leave_inside_attendance_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 0, 480, [['unpaid', 180, 240]], [420, 0, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_paid_leave_before_entry_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 120, 480, [['paid', 0, 120]], [360, 0, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_unpaid_leave_before_entry_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 120, 480, [['unpaid', 0, 120]], [360, 0, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_paid_leave_after_exit_through_float_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 60, 480, [['paid', 480, 540]], [420, 0, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_unpaid_leave_after_exit_through_float_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 60, 480, [['unpaid', 480, 540]], [420, 0, 0, 0]);
        }
    }

    public function test_tsv_to_monthly_with_separate_paid_and_unpaid_leave_for_both_shifts(): void
    {
        foreach (['07:30:00', '08:00:00'] as $start) {
            $this->assertImportedMonthlyScenario($start, 0, 480, [['paid', 180, 240], ['unpaid', 300, 330]], [390, 0, 0, 0]);
        }
    }

    private function assertImportedMonthlyScenario(string $start, int $entry, int $exit, array $leaves, array $expected): void
    {
        $shift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => $start,
            'end_time' => Carbon::parse($start)->addMinutes(480)->format('H:i:s'),
            'float' => 60, 'break' => 60, 'max_auto_overtime' => 120,
            'thursday_status' => ThursdayStatus::FULL_DAY,
        ]);
        $employee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_shift_id' => $shift->id,
            'device_id' => 'MONTHLY-'.$shift->id,
        ]);
        $date = '2026-02-07';
        $clock = fn (int $offset) => Carbon::parse($date.' '.$start)->addMinutes($offset);
        $paid = $unpaid = 0;

        foreach ($leaves as [$kind, $from, $to]) {
            $type = $kind === 'paid' ? PersonnelRequestType::LEAVE_HOURLY : PersonnelRequestType::LEAVE_WITHOUT_PAY_HOURLY;
            app(AttendanceService::class)->validateHourlyLeaveWithinShift($employee, $type, $date, $clock($from)->format('H:i'), $clock($to)->format('H:i'));
            PersonnelRequest::factory()->approved()->create([
                'fiscal_year_id' => $this->fiscalYearId, 'employee_id' => $employee->id,
                'request_type' => $type, 'start_date' => $clock($from), 'end_date' => $clock($to),
            ]);
            if ($kind === 'paid') {
                $paid += $to - $from;
            } else {
                $unpaid += $to - $from;
            }
        }

        PersonnelRequest::factory()->pending()->create([
            'fiscal_year_id' => $this->fiscalYearId, 'employee_id' => $employee->id,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => $clock(250), 'end_date' => $clock(280),
        ]);

        $monthly = app(AttendanceService::class)->calculateAndStore($employee->id, Carbon::parse($date), 28, 1404, 11);
        $this->assertSame(0, $monthly->present_days);
        $this->assertSame(24, $monthly->absent_days);
        // Last rows of each type determine the interval under the existing importer contract.
        $rows = [[$entry + 30, 0], [$exit - 30, 1], [$exit, 1], [$entry, 0]];
        $content = implode("\n", array_map(fn ($row) => implode("\t", [
            $employee->device_id, $clock($row[0])->format('Y-m-d H:i:s'), 0, $row[1], 0, 0,
        ]), $rows));

        $result = app(AttendanceLogImportService::class)->import(
            UploadedFile::fake()->createWithContent('attendance.tsv', $content),
            AttendanceImportType::DeviceTsv, $this->fiscalYearId,
        );

        $this->assertSame(['imported' => 1, 'skipped' => 0, 'unknown_devices' => []], $result);
        $log = AttendanceLog::where('employee_id', $employee->id)->sole();
        $this->assertSame($clock($entry)->format('H:i:s'), $log->entry_time);
        $this->assertSame($clock($exit)->format('H:i:s'), $log->exit_time);
        [$worked, $delay, $earlyLeave, $autoOvertime] = $expected;

        foreach (['worked' => $worked, 'delay' => $delay, 'early_leave' => $earlyLeave,
            'auto_overtime' => $autoOvertime, 'overtime' => 0, 'paid_leave' => $paid, 'unpaid_leave' => $unpaid] as $field => $value) {
            $this->assertSame($value, $log->$field, $field);
        }

        $this->assertFalse($log->is_manual);
        $this->assertSame(0, $monthly->fresh()->present_days);
        $this->assertNull($log->monthly_attendance_id);
        $monthly = app(AttendanceService::class)->calculateAndStore($employee->id, Carbon::parse($date), 28, 1404, 11)->fresh();

        foreach (['present_days' => 1, 'absent_days' => 23, 'work_days' => 28,
            'paid_leave' => $paid, 'unpaid_leave' => $unpaid, 'unpaid_leave_days' => 0,
            'overtime' => 0, 'auto_overtime' => $autoOvertime, 'undertime' => $delay + $earlyLeave] as $field => $value) {
            $this->assertSame($value, $monthly->$field, $field);
        }

        $this->assertSame($monthly->id, $log->fresh()->monthly_attendance_id);
        $this->assertEquals($worked, $monthly->logs()->sum('worked'));
        $this->assertEquals($delay, $monthly->logs()->sum('delay'));
        $this->assertEquals($earlyLeave, $monthly->logs()->sum('early_leave'));
    }

    public function test_multi_punch_import_ignore_keeps_complete_record(): void
    {
        $this->assertMultiPunchDuplicateMode('ignore', '09:00:00', '15:00:00', '09:00:00', '15:00:00', 0);
    }

    public function test_multi_punch_import_ignore_fills_missing_entry(): void
    {
        $this->assertMultiPunchDuplicateMode('ignore', null, '15:00:00', '12:00:00', '15:00:00', 1);
    }

    public function test_multi_punch_import_ignore_fills_missing_exit(): void
    {
        $this->assertMultiPunchDuplicateMode('ignore', '09:00:00', null, '09:00:00', '13:00:00', 1);
    }

    public function test_multi_punch_import_replace_uses_last_rows(): void
    {
        $this->assertMultiPunchDuplicateMode('replace', '09:00:00', '15:00:00', '12:00:00', '13:00:00', 1);
    }

    private function assertMultiPunchDuplicateMode(string $mode, ?string $entry, ?string $exit, string $expectedEntry, string $expectedExit, int $imported): void
    {
        $this->employee->update(['device_id' => 'DEVICE-MULTI']);
        $log = $this->makeAttendanceLog([
            'log_date' => '2026-02-07', 'entry_time' => $entry, 'exit_time' => $exit, 'is_manual' => true,
        ]);
        $content = "DEVICE-MULTI\t2026-02-07 08:00:00\t0\t0\n"
            ."DEVICE-MULTI\t2026-02-07 16:00:00\t0\t1\n"
            ."DEVICE-MULTI\t2026-02-07 12:00:00\t0\t0\n"
            ."DEVICE-MULTI\t2026-02-07 13:00:00\t0\t1";
        $result = app(AttendanceLogImportService::class)->import(
            UploadedFile::fake()->createWithContent('attendance.tsv', $content),
            AttendanceImportType::DeviceTsv, $this->fiscalYearId, duplicateMode: $mode,
        );
        $this->assertSame($imported, $result['imported']);
        $this->assertSame(1 - $imported, $result['skipped']);
        $log->refresh();
        $this->assertSame($expectedEntry, $log->entry_time);
        $this->assertSame($expectedExit, $log->exit_time);
        $this->assertTrue($log->is_manual);
        $this->assertSame(1, AttendanceLog::count());
    }

    public function test_changed_shift_and_approved_request_require_daily_then_monthly_recalculation(): void
    {
        $shift = WorkShift::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'start_time' => '08:00', 'end_time' => '16:00', 'float' => 60,
            'max_auto_overtime' => 120, 'thursday_status' => ThursdayStatus::FULL_DAY,
        ]);
        $this->employee->update(['work_shift_id' => $shift->id, 'device_id' => 'DEVICE-MULTI']);
        $request = PersonnelRequest::factory()->pending()->create([
            'fiscal_year_id' => $this->fiscalYearId, 'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY,
            'start_date' => '2026-02-07 11:00:00', 'end_date' => '2026-02-07 12:00:00',
        ]);
        app(AttendanceLogImportService::class)->import(
            UploadedFile::fake()->createWithContent('attendance.tsv',
                "DEVICE-MULTI\t2026-02-07 08:00:00\t0\t0\nDEVICE-MULTI\t2026-02-07 18:00:00\t0\t1"),
            AttendanceImportType::DeviceTsv, $this->fiscalYearId,
        );

        $monthly = app(AttendanceService::class)->calculateAndStore($this->employee->id, Carbon::parse('2026-02-07'), 28, 1404, 11);
        $this->assertSame(120, $monthly->auto_overtime);
        $this->assertSame(0, $monthly->paid_leave);
        $shift->update(['max_auto_overtime' => 30]);
        $request->update(['status' => PersonnelRequestStatus::APPROVED]);
        $monthly = app(AttendanceService::class)->calculateAndStore($this->employee->id, Carbon::parse('2026-02-07'), 28, 1404, 11);
        $this->assertSame(120, $monthly->auto_overtime);
        $this->assertSame(0, $monthly->paid_leave);

        foreach ($monthly->logs as $log) {
            app(AttendanceService::class)->recalculateLog($log);
        }

        $this->assertSame(120, $monthly->fresh()->auto_overtime);
        $log = $monthly->logs()->sole();
        $this->assertSame(540, $log->worked);
        $this->assertSame(30, $log->auto_overtime);
        $this->assertSame(60, $log->paid_leave);
        $monthly = app(AttendanceService::class)->calculateAndStore($this->employee->id, Carbon::parse('2026-02-07'), 28, 1404, 11);
        $this->assertSame(30, $monthly->auto_overtime);
        $this->assertSame(60, $monthly->paid_leave);
    }
}
