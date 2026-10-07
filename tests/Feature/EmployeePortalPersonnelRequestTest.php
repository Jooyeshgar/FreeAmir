<?php

namespace Tests\Feature;

use App\Enums\PersonnelRequestStatus;
use App\Enums\PersonnelRequestType;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\PersonnelRequest;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeePortalPersonnelRequestTest extends TestCase
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
            Permission::firstOrCreate(['name' => 'employee-portal.dashboard'])
        );
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'home'])
        );

        $fiscalYear = FiscalYear::factory()->create();
        $this->fiscalYearId = $fiscalYear->id;

        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $workShift = WorkShift::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);

        $this->employee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => $this->fiscalYearId]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'request_type' => PersonnelRequestType::REMOTE_WORK->valueName(),
            'request_date' => '1404/12/10',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'reason' => 'Portal request',
            'tab' => 'other',
        ], $overrides);
    }

    private function makePersonnelRequest(array $overrides = []): PersonnelRequest
    {
        return PersonnelRequest::factory()->create(array_merge([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
            'status' => PersonnelRequestStatus::PENDING,
        ], $overrides));
    }

    public function test_store_accepts_flexible_time_format_and_normalizes_it(): void
    {
        $response = $this->post(route('employee-portal.personnel-requests.store'), $this->validPayload([
            'start_time' => '7:3',
            'end_time' => '7:30',
        ]));

        $response->assertRedirect(route('employee-portal.personnel-requests.index', ['tab' => 'other']));
        $response->assertSessionHas('success');

        $request = PersonnelRequest::query()->latest('id')->first();

        $this->assertNotNull($request);
        $this->assertStringEndsWith('07:03:00', (string) $request->start_date);
        $this->assertStringEndsWith('07:30:00', (string) $request->end_date);
    }

    public function test_update_accepts_flexible_time_format_and_normalizes_it(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->put(route('employee-portal.personnel-requests.update', $personnelRequest), $this->validPayload([
            'start_time' => '9:5',
            'end_time' => '9:45',
            'reason' => 'Updated portal request',
        ]));

        $response->assertRedirect(route('employee-portal.personnel-requests.index', ['tab' => 'other']));
        $response->assertSessionHas('success');

        $personnelRequest->refresh();

        $this->assertStringEndsWith('09:05:00', (string) $personnelRequest->start_date);
        $this->assertStringEndsWith('09:45:00', (string) $personnelRequest->end_date);
        $this->assertSame('Updated portal request', $personnelRequest->reason);
    }

    public function test_store_rejects_invalid_flexible_time_values(): void
    {
        $response = $this->from(route('employee-portal.personnel-requests.create', ['tab' => 'other']))
            ->post(route('employee-portal.personnel-requests.store'), $this->validPayload([
                'start_time' => '7:99',
                'end_time' => '8:00',
            ]));

        $response->assertRedirect(route('employee-portal.personnel-requests.create', ['tab' => 'other']));
        $response->assertSessionHasErrors(['start_time']);
    }
}
