<?php

namespace Tests\Feature;

use App\Enums\AttendanceImportType;
use App\Enums\ThursdayStatus;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\AttendanceLogImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AttendanceLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'attendance.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
        config(['active-company-id' => $this->companyId]);

        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite->id,
        ]);
    }

    private function makeAttendanceLog(array $overrides = []): AttendanceLog
    {
        return AttendanceLog::factory()->create(array_merge([
            'company_id' => $this->companyId,
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
        $workSite2 = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $other = Employee::factory()->create([
            'company_id' => $this->companyId,
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
            'company_id' => $this->companyId,
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
            'company_id' => $this->companyId,
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
        $uploadedFile = new \Illuminate\Http\UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        /** @var AttendanceLogImportService $service */
        $service = app(AttendanceLogImportService::class);
        $result = $service->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->companyId);

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
        // 28-day window covers Feb 1–28 2026
        // Fridays: Feb 6, 13, 20, 27  |  Thursdays: Feb 5, 12, 19, 26
        return array_merge([
            'employee_ids' => [$this->employee->id],
            'start_date' => '1404/11/12',
            'duration' => 28,
        ], $overrides);
    }

    public function test_bulk_create_returns_200(): void
    {
        $response = $this->get(route('attendance.attendance-logs.bulk-create'));

        $response->assertStatus(200);
    }

    public function test_bulk_store_creates_logs_for_each_selected_employee(): void
    {
        $workSite2 = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $employee2 = Employee::factory()->create([
            'company_id' => $this->companyId,
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
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'company_id' => $this->companyId,
            'employee_id' => $employee2->id,
            'log_date' => '2026-02-02',
        ]);
    }

    public function test_bulk_store_only_creates_for_selected_employees(): void
    {
        $workSite2 = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $unselected = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite2->id,
        ]);

        $this->post(
            route('attendance.attendance-logs.bulk-store'),
            $this->validBulkPayload(['employee_ids' => [$this->employee->id]])
        );

        $this->assertDatabaseHas('attendance_logs', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
        ]);
        $this->assertDatabaseMissing('attendance_logs', [
            'company_id' => $this->companyId,
            'employee_id' => $unselected->id,
        ]);
    }

    public function test_bulk_store_overwrites_existing_log_with_shift_times(): void
    {
        $workShift = WorkShift::factory()->create([
            'company_id' => $this->companyId,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        AttendanceLog::factory()->create([
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
            'entry_time' => '09:30:00',
            'exit_time' => '18:30:00',
        ]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // Existing log must be overwritten with shift times
        $this->assertDatabaseHas('attendance_logs', [
            'company_id' => $this->companyId,
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

    public function test_bulk_store_skips_fridays(): void
    {
        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // 2026-02-06 is a Friday — no log
        $this->assertDatabaseMissing('attendance_logs', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-06',
        ]);
        // 2026-02-02 is a Monday — log exists
        $this->assertDatabaseHas('attendance_logs', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-02',
        ]);
    }

    public function test_bulk_store_skips_public_holidays(): void
    {
        // Mark Tuesday 2026-02-03 as a public holiday
        PublicHoliday::factory()->create([
            'company_id' => $this->companyId,
            'date' => '2026-02-03',
        ]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        $this->assertDatabaseMissing('attendance_logs', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-03',
        ]);
    }

    public function test_bulk_store_skips_thursday_when_shift_is_holiday(): void
    {
        $workShift = WorkShift::factory()->create([
            'company_id' => $this->companyId,
            'thursday_status' => ThursdayStatus::HOLIDAY,
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // 2026-02-05 is a Thursday — skipped because shift marks it as holiday
        $this->assertDatabaseMissing('attendance_logs', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-05',
        ]);
    }

    public function test_bulk_store_creates_thursday_log_when_shift_is_full_day(): void
    {
        $workShift = WorkShift::factory()->create([
            'company_id' => $this->companyId,
            'thursday_status' => ThursdayStatus::FULL_DAY,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        $this->assertDatabaseHas('attendance_logs', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-05',
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
        ]);
    }

    public function test_bulk_store_uses_thursday_exit_time_for_half_day(): void
    {
        $workShift = WorkShift::factory()->create([
            'company_id' => $this->companyId,
            'thursday_status' => ThursdayStatus::HALF_DAY,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'thursday_exit_time' => '12:00:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        // Thursday gets early exit per shift config
        $this->assertDatabaseHas('attendance_logs', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2026-02-05',
            'entry_time' => '08:00:00',
            'exit_time' => '12:00:00',
        ]);
    }

    public function test_bulk_store_uses_shift_times_for_entry_and_exit(): void
    {
        $workShift = WorkShift::factory()->create([
            'company_id' => $this->companyId,
            'start_time' => '08:30:00',
            'end_time' => '16:30:00',
        ]);
        $this->employee->update(['work_shift_id' => $workShift->id]);

        $this->post(route('attendance.attendance-logs.bulk-store'), $this->validBulkPayload());

        $this->assertDatabaseHas('attendance_logs', [
            'company_id' => $this->companyId,
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
            ['start_date' => formatDate('2026-02-01'), 'duration' => 28]
        );

        $response->assertSessionHasErrors(['employee_ids']);
    }

    public function test_bulk_store_validates_start_date_required(): void
    {
        $response = $this->post(
            route('attendance.attendance-logs.bulk-store'),
            ['employee_ids' => [$this->employee->id], 'duration' => 28]
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

    public function test_import_skips_recalculate_for_duplicate_ignored_rows(): void
    {
        $workShift = WorkShift::factory()->create([
            'company_id' => $this->companyId,
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
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => $logDate,
            'worked' => 999,
        ]);

        $tsv = $this->makeTsvContent($deviceId, $logDate, '08:00', '17:00');

        $tmpPath = tempnam(sys_get_temp_dir(), 'tsv_');
        file_put_contents($tmpPath, $tsv);
        $uploadedFile = new \Illuminate\Http\UploadedFile($tmpPath, 'attendance.tsv', 'text/plain', null, true);

        /** @var AttendanceLogImportService $service */
        $service = app(AttendanceLogImportService::class);
        $result = $service->import($uploadedFile, AttendanceImportType::DeviceTsv, $this->companyId, null, null, 'ignore');

        $this->assertEquals(0, $result['imported']);

        // existing row should be untouched
        $this->assertDatabaseHas('attendance_logs', [
            'employee_id' => $this->employee->id,
            'log_date' => $logDate,
            'worked' => 999,
        ]);
    }
}
