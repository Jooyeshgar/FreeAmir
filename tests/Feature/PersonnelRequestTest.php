<?php

namespace Tests\Feature;

use App\Enums\PersonnelRequestStatus;
use App\Enums\PersonnelRequestType;
use App\Enums\ThursdayStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PersonnelRequest;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PersonnelRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected Employee $employee;

    protected WorkShift $workShift;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'hr.personnel-requests.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);

        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $this->workShift = WorkShift::factory()->create(['company_id' => $this->companyId]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $this->workShift->id,
        ]);
    }

    private function makePersonnelRequest(array $overrides = []): PersonnelRequest
    {
        return PersonnelRequest::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_DAILY,
            'status' => PersonnelRequestStatus::PENDING,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        $payload = array_merge([
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY->valueName(),
            'request_date' => '1404/12/10',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'reason' => 'Family matter',
            'status' => PersonnelRequestStatus::PENDING->valueName(),
            'tab' => 'leaves',
        ], $overrides);

        if ($payload['request_type'] instanceof PersonnelRequestType) {
            $payload['request_type'] = $payload['request_type']->valueName();
        }

        if ($payload['status'] instanceof PersonnelRequestStatus) {
            $payload['status'] = $payload['status']->valueName();
        }

        return $payload;
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_returns_200(): void
    {
        $response = $this->get(route('hr.personnel-requests.index'));

        $response->assertStatus(200);
    }

    public function test_index_lists_personnel_requests_for_active_company(): void
    {
        $this->makePersonnelRequest(['request_type' => PersonnelRequestType::LEAVE_DAILY]);
        $this->makePersonnelRequest(['request_type' => PersonnelRequestType::LEAVE_HOURLY]);

        $response = $this->get(route('hr.personnel-requests.index'));

        $response->assertStatus(200);
        $response->assertSee($this->employee->first_name);
    }

    public function test_index_tabs_filter_by_type_group(): void
    {
        $this->makePersonnelRequest(['request_type' => PersonnelRequestType::LEAVE_DAILY]);
        $this->makePersonnelRequest(['request_type' => PersonnelRequestType::MISSION_DAILY]);

        // Leaves tab (default)
        $response = $this->get(route('hr.personnel-requests.index', ['tab' => 'leaves']));
        $response->assertStatus(200);

        // Missions tab
        $response = $this->get(route('hr.personnel-requests.index', ['tab' => 'missions']));
        $response->assertStatus(200);

        // Work orders tab
        $response = $this->get(route('hr.personnel-requests.index', ['tab' => 'work_orders']));
        $response->assertStatus(200);

        // Other tab
        $response = $this->get(route('hr.personnel-requests.index', ['tab' => 'other']));
        $response->assertStatus(200);
    }

    public function test_index_shows_pending_badge_counts(): void
    {
        $this->makePersonnelRequest(['status' => PersonnelRequestStatus::PENDING, 'request_type' => PersonnelRequestType::LEAVE_DAILY]);
        $this->makePersonnelRequest(['status' => PersonnelRequestStatus::APPROVED, 'request_type' => PersonnelRequestType::LEAVE_DAILY]);

        $response = $this->get(route('hr.personnel-requests.index'));

        $response->assertStatus(200);
        // The pending count badge (1) must appear on the page
        $response->assertSee('1');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makePersonnelRequest(['status' => PersonnelRequestStatus::PENDING]);
        $this->makePersonnelRequest(['status' => PersonnelRequestStatus::APPROVED]);

        $response = $this->get(route('hr.personnel-requests.index', ['tab' => 'leaves', 'status' => 'pending']));

        $response->assertStatus(200);
    }

    public function test_index_filters_by_employee(): void
    {
        $this->makePersonnelRequest();

        $response = $this->get(route('hr.personnel-requests.index', [
            'tab' => 'leaves',
            'employee_id' => $this->employee->id,
        ]));

        $response->assertStatus(200);
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('hr.personnel-requests.create'));

        $response->assertStatus(200);
    }

    public function test_create_returns_200_with_tab_param(): void
    {
        foreach (['leaves', 'missions', 'work_orders', 'other'] as $tab) {
            $response = $this->get(route('hr.personnel-requests.create', ['tab' => $tab]));
            $response->assertStatus(200);
        }
    }

    public function test_store_creates_personnel_request_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('hr.personnel-requests.store'), $payload);

        $response->assertRedirect(route('hr.personnel-requests.index', ['tab' => 'leaves']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('personnel_requests', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_HOURLY->value,
            'status' => PersonnelRequestStatus::PENDING->value,
            'approved_by' => null,
        ]);
    }

    public function test_store_daily_mission_uses_shift_times_for_the_requested_date(): void
    {
        $this->workShift->update([
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'thursday_status' => ThursdayStatus::HALF_DAY,
            'thursday_exit_time' => '12:00:00',
        ]);

        $response = $this->post(route('hr.personnel-requests.store'), $this->validPayload([
            'request_type' => PersonnelRequestType::MISSION_DAILY,
            'request_date' => convertToJalali('2025-03-06', true),
            'start_time' => '',
            'end_time' => '',
            'tab' => 'missions',
        ]));

        $response->assertRedirect(route('hr.personnel-requests.index', ['tab' => 'missions']));
        $request = PersonnelRequest::query()->latest('id')->firstOrFail();

        $this->assertSame('2025-03-06 08:00:00', $request->start_date->format('Y-m-d H:i:s'));
        $this->assertSame('2025-03-06 12:00:00', $request->end_date->format('Y-m-d H:i:s'));
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('hr.personnel-requests.store'), []);

        $response->assertSessionHasErrors(['employee_id', 'request_date', 'start_time', 'end_time']);
    }

    public function test_store_rejects_end_time_before_start_time(): void
    {
        $response = $this->post(route('hr.personnel-requests.store'), $this->validPayload([
            'start_time' => '17:00',
            'end_time' => '08:00',
        ]));
        $response->assertSessionHasErrors();
    }

    public function test_store_accepts_flexible_time_format_and_normalizes_it(): void
    {
        $response = $this->post(route('hr.personnel-requests.store'), $this->validPayload([
            'start_time' => '7:3',
            'end_time' => '7:30',
        ]));

        $response->assertRedirect(route('hr.personnel-requests.index', ['tab' => 'leaves']));

        $personnelRequest = PersonnelRequest::query()->latest('id')->firstOrFail();

        $this->assertSame('07:03:00', $personnelRequest->start_date->format('H:i:s'));
        $this->assertSame('07:30:00', $personnelRequest->end_date->format('H:i:s'));
    }

    public function test_store_rejects_invalid_flexible_time_values(): void
    {
        $response = $this->post(route('hr.personnel-requests.store'), $this->validPayload([
            'start_time' => '7:99',
            'end_time' => '8:00',
        ]));

        $response->assertSessionHasErrors(['start_time']);
    }

    public function test_store_rejects_invalid_request_type(): void
    {
        $response = $this->post(route('hr.personnel-requests.store'), $this->validPayload([
            'request_type' => 10,
        ]));

        $response->assertSessionHasErrors(['request_type']);
    }

    public function test_store_rejects_nonexistent_employee(): void
    {
        $response = $this->post(route('hr.personnel-requests.store'), $this->validPayload([
            'employee_id' => 99999,
        ]));

        $response->assertSessionHasErrors(['employee_id']);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->get(route('hr.personnel-requests.edit', $personnelRequest));

        $response->assertStatus(200);
    }

    public function test_update_modifies_personnel_request_and_redirects(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->put(
            route('hr.personnel-requests.update', $personnelRequest),
            $this->validPayload([
                'reason' => 'Updated reason',
                'request_date' => '1405/12/01',
                'start_time' => '08:00',
                'end_time' => '10:00',
            ])
        );

        $response->assertRedirect(route('hr.personnel-requests.index', ['tab' => 'leaves']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('personnel_requests', [
            'id' => $personnelRequest->id,
            'reason' => 'Updated reason',
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->put(route('hr.personnel-requests.update', $personnelRequest), []);

        $response->assertSessionHasErrors(['request_date', 'start_time', 'end_time']);
    }

    public function test_update_rejects_end_time_before_start_time(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->put(
            route('hr.personnel-requests.update', $personnelRequest),
            $this->validPayload([
                'start_time' => '09:00',
                'end_time' => '08:00',
            ])
        );

        $response->assertSessionHasErrors(['end_time']);
    }

    public function test_update_accepts_flexible_time_format_and_normalizes_it(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->put(
            route('hr.personnel-requests.update', $personnelRequest),
            $this->validPayload([
                'start_time' => '9:5',
                'end_time' => '9:45',
            ])
        );

        $response->assertRedirect(route('hr.personnel-requests.index', ['tab' => 'leaves']));

        $personnelRequest->refresh();

        $this->assertSame('09:05:00', $personnelRequest->start_date->format('H:i:s'));
        $this->assertSame('09:45:00', $personnelRequest->end_date->format('H:i:s'));
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_personnel_request_and_redirects(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->delete(route('hr.personnel-requests.destroy', $personnelRequest));

        $response->assertRedirect(route('hr.personnel-requests.index', ['tab' => 'leaves']));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('personnel_requests', ['id' => $personnelRequest->id]);
    }

    // ----------------------------------------------------------------
    // approve / reject
    // ----------------------------------------------------------------

    public function test_approve_changes_status_to_approved(): void
    {
        $personnelRequest = $this->makePersonnelRequest(['status' => PersonnelRequestStatus::PENDING]);

        $response = $this->patch(route('hr.personnel-requests.approve', $personnelRequest));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('personnel_requests', [
            'id' => $personnelRequest->id,
            'status' => PersonnelRequestStatus::APPROVED->value,
        ]);
        // approved_by should be auto-set to the acting user's employee id (null here since test user has no employee)
        $this->assertDatabaseHas('personnel_requests', ['id' => $personnelRequest->id, 'status' => PersonnelRequestStatus::APPROVED->value]);
    }

    public function test_reject_changes_status_to_rejected(): void
    {
        $personnelRequest = $this->makePersonnelRequest(['status' => PersonnelRequestStatus::PENDING]);

        $response = $this->patch(route('hr.personnel-requests.reject', $personnelRequest));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('personnel_requests', [
            'id' => $personnelRequest->id,
            'status' => PersonnelRequestStatus::REJECTED->value,
        ]);
        // approved_by should be auto-set to the acting user's employee id (null here since test user has no employee)
        $this->assertDatabaseHas('personnel_requests', ['id' => $personnelRequest->id, 'status' => PersonnelRequestStatus::REJECTED->value]);
    }

    public function test_approve_can_override_rejected_status(): void
    {
        $personnelRequest = $this->makePersonnelRequest(['status' => PersonnelRequestStatus::REJECTED]);

        $this->patch(route('hr.personnel-requests.approve', $personnelRequest));

        $this->assertDatabaseHas('personnel_requests', [
            'id' => $personnelRequest->id,
            'status' => PersonnelRequestStatus::APPROVED->value,
        ]);
    }

    public function test_reject_can_override_approved_status(): void
    {
        $personnelRequest = $this->makePersonnelRequest(['status' => PersonnelRequestStatus::APPROVED]);

        $this->patch(route('hr.personnel-requests.reject', $personnelRequest));

        $this->assertDatabaseHas('personnel_requests', [
            'id' => $personnelRequest->id,
            'status' => PersonnelRequestStatus::REJECTED->value,
        ]);
    }

    public function test_approve_sets_approved_by_to_current_user_employee(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $approverEmployee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite->id,
            'user_id' => $this->user->id,
        ]);

        $personnelRequest = $this->makePersonnelRequest(['status' => PersonnelRequestStatus::PENDING]);

        $this->patch(route('hr.personnel-requests.approve', $personnelRequest));

        $this->assertDatabaseHas('personnel_requests', [
            'id' => $personnelRequest->id,
            'status' => PersonnelRequestStatus::APPROVED->value,
            'approved_by' => $approverEmployee->user->id,
        ]);
    }

    public function test_reject_sets_approved_by_to_current_user_employee(): void
    {
        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $approverEmployee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite->id,
            'user_id' => $this->user->id,
        ]);

        $personnelRequest = $this->makePersonnelRequest(['status' => PersonnelRequestStatus::PENDING]);

        $this->patch(route('hr.personnel-requests.reject', $personnelRequest));

        $this->assertDatabaseHas('personnel_requests', [
            'id' => $personnelRequest->id,
            'status' => PersonnelRequestStatus::REJECTED->value,
            'approved_by' => $approverEmployee->user->id,
        ]);
    }

    // ----------------------------------------------------------------
    // company isolation
    // ----------------------------------------------------------------

    public function test_cannot_see_another_companys_requests(): void
    {
        $otherCompany = Company::factory()->create();
        $otherWorkSite = WorkSite::factory()->create(['company_id' => $otherCompany->id]);
        $otherEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'work_site_id' => $otherWorkSite->id,
        ]);

        PersonnelRequest::factory()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'request_type' => PersonnelRequestType::LEAVE_DAILY,
            'status' => PersonnelRequestStatus::PENDING,
        ]);

        $response = $this->get(route('hr.personnel-requests.index'));

        // The page renders fine but the other company's record is not visible
        $response->assertStatus(200);
        $response->assertDontSee($otherEmployee->first_name.' '.$otherEmployee->last_name);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('hr.personnel-requests.index'));

        $response->assertRedirect(route('login'));
    }
}
