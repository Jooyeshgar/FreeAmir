<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\User;
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
            Permission::firstOrCreate(['name' => 'monthly-attendances.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);

        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite->id,
        ]);
    }

    private function makeMonthlyAttendance(array $overrides = []): MonthlyAttendance
    {
        return MonthlyAttendance::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
        ], $overrides));
    }

    private function validCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'start_date' => '2025-10-23',  // 1404/08/01 in Jalali (Aban 1404)
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
            'mission_days' => 1,
            'paid_leave_days' => 1,
            'unpaid_leave_days' => 0,
            'friday' => 0,
            'holiday' => 0,
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_returns_200(): void
    {
        $response = $this->get(route('monthly-attendances.index'));

        $response->assertStatus(200);
    }

    public function test_index_lists_records_for_active_company(): void
    {
        $this->makeMonthlyAttendance(['year' => 1403, 'month' => 7]);
        $this->makeMonthlyAttendance(['year' => 1403, 'month' => 8]);

        $response = $this->get(route('monthly-attendances.index'));

        $response->assertStatus(200);
        $response->assertSee('مهر');
        $response->assertSee('آبان');
    }

    public function test_index_filters_by_employee(): void
    {
        $workSite2 = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $other = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite2->id,
        ]);

        $this->makeMonthlyAttendance(['employee_id' => $this->employee->id, 'year' => 1403, 'month' => 7]);
        $this->makeMonthlyAttendance(['employee_id' => $other->id, 'year' => 1403, 'month' => 8]);

        $response = $this->get(route('monthly-attendances.index', ['employee_id' => $this->employee->id]));

        $response->assertStatus(200);
        $response->assertSee('مهر');
        $response->assertDontSee('آبان');
    }

    public function test_index_filters_by_year(): void
    {
        $this->makeMonthlyAttendance(['year' => 1402, 'month' => 1]);
        $this->makeMonthlyAttendance(['year' => 1403, 'month' => 1]);

        $response = $this->get(route('monthly-attendances.index', ['year' => 1402]));

        $response->assertStatus(200);
        $response->assertSee('1402');
        $response->assertDontSee('1403');
    }

    public function test_index_does_not_show_other_company_records(): void
    {
        $otherCompany = Company::factory()->create();
        $otherWorkSite = WorkSite::factory()->create(['company_id' => $otherCompany->id]);
        $otherEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'work_site_id' => $otherWorkSite->id,
        ]);
        MonthlyAttendance::factory()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'year' => 1403,
            'month' => 7,
        ]);

        $response = $this->get(route('monthly-attendances.index'));

        $response->assertStatus(200);
        $response->assertDontSee($otherEmployee->first_name);
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('monthly-attendances.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_record_and_redirects(): void
    {
        $payload = $this->validCreatePayload();

        $response = $this->post(route('monthly-attendances.store'), $payload);

        $response->assertRedirect(route('monthly-attendances.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('monthly_attendances', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('monthly-attendances.store'), []);

        $response->assertSessionHasErrors(['employee_id', 'start_date', 'duration']);
    }

    public function test_store_validates_duration_range(): void
    {
        $payload = $this->validCreatePayload(['duration' => 10]);

        $response = $this->post(route('monthly-attendances.store'), $payload);

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
                'company_id' => $this->companyId,
                'employee_id' => $this->employee->id,
                'log_date' => $day->toDateString(),
                'entry_time' => '08:00:00',
                'exit_time' => '17:00:00',
                'worked' => 480,
            ]);
        }

        $this->post(route('monthly-attendances.store'), $this->validCreatePayload());

        $record = MonthlyAttendance::where('company_id', $this->companyId)
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

        $response = $this->get(route('monthly-attendances.show', $attendance));

        $response->assertStatus(200);
        $response->assertSee('مهر');
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $attendance = $this->makeMonthlyAttendance(['year' => 1403, 'month' => 1]);

        $response = $this->get(route('monthly-attendances.edit', $attendance));

        $response->assertStatus(200);
    }

    public function test_update_saves_changes_and_redirects(): void
    {
        $attendance = $this->makeMonthlyAttendance(['year' => 1403, 'month' => 1]);
        $payload = $this->validUpdatePayload(['present_days' => 18, 'absent_days' => 4]);

        $response = $this->put(route('monthly-attendances.update', $attendance), $payload);

        $response->assertRedirect(route('monthly-attendances.show', $attendance));
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

        $response = $this->put(route('monthly-attendances.update', $attendance), []);

        $response->assertSessionHasErrors(['work_days', 'present_days', 'absent_days']);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_and_redirects(): void
    {
        $attendance = $this->makeMonthlyAttendance(['year' => 1403, 'month' => 1]);

        $response = $this->delete(route('monthly-attendances.destroy', $attendance));

        $response->assertRedirect(route('monthly-attendances.index'));
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
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'log_date' => '2024-10-22', // 1403/08/01
            'entry_time' => '08:00:00',
            'exit_time' => '17:00:00',
            'worked' => 480,
        ]);

        $response = $this->post(route('monthly-attendances.recalculate', $attendance), [
            'start_date' => '2024-10-22',
            'duration' => 30,
        ]);

        $response->assertRedirect(route('monthly-attendances.show', $attendance));
        $response->assertSessionHas('success');
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
            $log->log_date = $day;
            $logs->push($log);
        }

        $totals = $service->computeTotals($startDate, 5, $logs, []);

        $this->assertEquals(3, $totals['present_days']);
    }

    public function test_attendance_service_excludes_fridays_from_work_days(): void
    {
        $service = new AttendanceService;

        // Find a Friday
        $friday = Carbon::now()->next(Carbon::FRIDAY);

        $totals = $service->computeTotals($friday, 1, new Collection, []);

        $this->assertEquals(0, $totals['work_days']);
        $this->assertEquals(0, $totals['absent_days']);
    }

    public function test_attendance_service_excludes_public_holidays_from_work_days(): void
    {
        $service = new AttendanceService;

        $monday = Carbon::create(2025, 1, 6); // Known Monday
        $holiday = $monday->toDateString();

        $totals = $service->computeTotals($monday, 1, new Collection, [$holiday]);

        $this->assertEquals(0, $totals['work_days']);
        $this->assertEquals(0, $totals['absent_days']);
    }

    public function test_attendance_service_counts_overtime(): void
    {
        $service = new AttendanceService;
        $monday = Carbon::create(2025, 1, 6);

        // 600 worked minutes = 480 standard + 120 overtime
        $log = new AttendanceLog([
            'worked' => 600,
            'overtime' => 0,
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
}
