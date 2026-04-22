<?php

namespace Tests\Feature;

use App\Enums\PersonnelRequestType;
use App\Models\Company;
use App\Models\Employee;
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
            Permission::firstOrCreate(['name' => 'employee-portal.dashboard'])
        );

        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->companyId]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
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
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::REMOTE_WORK->value,
            'status' => 'pending',
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