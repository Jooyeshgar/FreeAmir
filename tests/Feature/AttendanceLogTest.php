<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSite;
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
            Permission::firstOrCreate(['name' => 'attendance-logs.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);

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
            'log_date' => '2026-02-10',
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
        $this->makeAttendanceLog(['log_date' => '2026-02-10']);
        $this->makeAttendanceLog(['log_date' => '2026-02-11']);

        $response = $this->get(route('attendance-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('2026-02-10');
        $response->assertSee('2026-02-11');
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

        $response = $this->get(route('attendance-logs.index', ['employee_id' => $this->employee->id]));

        $response->assertStatus(200);
        $response->assertSee('2026-02-10');
        $response->assertDontSee('2026-02-11');
    }

    public function test_index_filters_by_date_range(): void
    {
        $this->makeAttendanceLog(['log_date' => '2026-01-05']);
        $this->makeAttendanceLog(['log_date' => '2026-02-10']);
        $this->makeAttendanceLog(['log_date' => '2026-03-20']);

        $response = $this->get(route('attendance-logs.index', [
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
        ]));

        $response->assertStatus(200);
        $response->assertSee('2026-02-10');
        $response->assertDontSee('2026-01-05');
        $response->assertDontSee('2026-03-20');
    }

    public function test_index_filters_by_is_manual(): void
    {
        $this->makeAttendanceLog(['log_date' => '2026-02-10', 'is_manual' => true]);
        $this->makeAttendanceLog(['log_date' => '2026-02-11', 'is_manual' => false]);

        $response = $this->get(route('attendance-logs.index', ['is_manual' => '1']));

        $response->assertStatus(200);
        $response->assertSee('2026-02-10');
        $response->assertDontSee('2026-02-11');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('attendance-logs.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_an_attendance_log_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('attendance-logs.store'), $payload);

        $response->assertRedirect(route('attendance-logs.index'));
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
        $response = $this->post(route('attendance-logs.store'), []);

        $response->assertSessionHasErrors(['employee_id', 'log_date']);
    }

    public function test_store_rejects_invalid_date(): void
    {
        $response = $this->post(route('attendance-logs.store'), $this->validPayload([
            'log_date' => 'not-a-date',
        ]));

        $response->assertSessionHasErrors(['log_date']);
    }

    public function test_store_rejects_exit_time_before_entry_time(): void
    {
        $response = $this->post(route('attendance-logs.store'), $this->validPayload([
            'entry_time' => '17:00',
            'exit_time' => '08:00',
        ]));

        $response->assertSessionHasErrors(['exit_time']);
    }

    public function test_store_rejects_nonexistent_employee(): void
    {
        $response = $this->post(route('attendance-logs.store'), $this->validPayload([
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

        $response = $this->post(route('attendance-logs.store'), $payload);

        $response->assertRedirect(route('attendance-logs.index'));
        $response->assertSessionHas('success');
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $log = $this->makeAttendanceLog();

        $response = $this->get(route('attendance-logs.edit', $log));

        $response->assertStatus(200);
        $response->assertSee($log->log_date->format('Y-m-d'));
    }

    public function test_update_modifies_log_and_redirects(): void
    {
        $log = $this->makeAttendanceLog(['log_date' => '2026-02-10', 'entry_time' => '08:00']);

        $response = $this->put(route('attendance-logs.update', $log), $this->validPayload([
            'entry_time' => '09:00',
            'is_manual' => '1',
        ]));

        $response->assertRedirect(route('attendance-logs.index'));
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

        $response = $this->put(route('attendance-logs.update', $log), []);

        $response->assertSessionHasErrors(['employee_id', 'log_date']);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_log_and_redirects(): void
    {
        $log = $this->makeAttendanceLog();

        $response = $this->delete(route('attendance-logs.destroy', $log));

        $response->assertRedirect(route('attendance-logs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('attendance_logs', ['id' => $log->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('attendance-logs.index'));

        $response->assertRedirect(route('login'));
    }
}
