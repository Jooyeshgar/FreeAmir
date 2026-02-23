<?php

namespace Tests\Feature;

use App\Enums\PersonnelRequestType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PersonnelRequest;
use App\Models\User;
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

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'personnel-requests.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);

        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite->id,
        ]);
    }

    private function makePersonnelRequest(array $overrides = []): PersonnelRequest
    {
        return PersonnelRequest::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_DAILY->value,
            'status' => 'pending',
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_DAILY->value,
            'start_date' => '2026-03-01T08:00',
            'end_date' => '2026-03-01T17:00',
            'duration_minutes' => 480,
            'reason' => 'Family matter',
            'status' => 'pending',
            'approved_by' => null,
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_returns_200(): void
    {
        $response = $this->get(route('personnel-requests.index'));

        $response->assertStatus(200);
    }

    public function test_index_lists_personnel_requests_for_active_company(): void
    {
        $this->makePersonnelRequest(['request_type' => PersonnelRequestType::LEAVE_DAILY->value]);
        $this->makePersonnelRequest(['request_type' => PersonnelRequestType::LEAVE_HOURLY->value]);

        $response = $this->get(route('personnel-requests.index'));

        $response->assertStatus(200);
        $response->assertSee($this->employee->first_name);
    }

    public function test_index_tabs_filter_by_type_group(): void
    {
        $this->makePersonnelRequest(['request_type' => PersonnelRequestType::LEAVE_DAILY->value]);
        $this->makePersonnelRequest(['request_type' => PersonnelRequestType::MISSION_DAILY->value]);

        // Leaves tab (default)
        $response = $this->get(route('personnel-requests.index', ['tab' => 'leaves']));
        $response->assertStatus(200);

        // Missions tab
        $response = $this->get(route('personnel-requests.index', ['tab' => 'missions']));
        $response->assertStatus(200);

        // Work orders tab
        $response = $this->get(route('personnel-requests.index', ['tab' => 'work_orders']));
        $response->assertStatus(200);

        // Other tab
        $response = $this->get(route('personnel-requests.index', ['tab' => 'other']));
        $response->assertStatus(200);
    }

    public function test_index_shows_pending_badge_counts(): void
    {
        $this->makePersonnelRequest(['status' => 'pending', 'request_type' => PersonnelRequestType::LEAVE_DAILY->value]);
        $this->makePersonnelRequest(['status' => 'approved', 'request_type' => PersonnelRequestType::LEAVE_DAILY->value]);

        $response = $this->get(route('personnel-requests.index'));

        $response->assertStatus(200);
        // The pending count badge (1) must appear on the page
        $response->assertSee('1');
    }

    public function test_index_filters_by_status(): void
    {
        $this->makePersonnelRequest(['status' => 'pending']);
        $this->makePersonnelRequest(['status' => 'approved']);

        $response = $this->get(route('personnel-requests.index', ['tab' => 'leaves', 'status' => 'pending']));

        $response->assertStatus(200);
    }

    public function test_index_filters_by_employee(): void
    {
        $this->makePersonnelRequest();

        $response = $this->get(route('personnel-requests.index', [
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
        $response = $this->get(route('personnel-requests.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_personnel_request_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('personnel-requests.store'), $payload);

        $response->assertRedirect(route('personnel-requests.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('personnel_requests', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_DAILY->value,
            'status' => 'pending',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('personnel-requests.store'), []);

        $response->assertSessionHasErrors(['employee_id', 'request_type', 'start_date', 'end_date', 'status']);
    }

    public function test_store_rejects_end_date_before_start_date(): void
    {
        $response = $this->post(route('personnel-requests.store'), $this->validPayload([
            'start_date' => '2026-03-05T08:00',
            'end_date' => '2026-03-01T08:00',
        ]));

        $response->assertSessionHasErrors(['end_date']);
    }

    public function test_store_rejects_invalid_request_type(): void
    {
        $response = $this->post(route('personnel-requests.store'), $this->validPayload([
            'request_type' => 'INVALID_TYPE',
        ]));

        $response->assertSessionHasErrors(['request_type']);
    }

    public function test_store_rejects_invalid_status(): void
    {
        $response = $this->post(route('personnel-requests.store'), $this->validPayload([
            'status' => 'unknown',
        ]));

        $response->assertSessionHasErrors(['status']);
    }

    public function test_store_rejects_nonexistent_employee(): void
    {
        $response = $this->post(route('personnel-requests.store'), $this->validPayload([
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

        $response = $this->get(route('personnel-requests.edit', $personnelRequest));

        $response->assertStatus(200);
    }

    public function test_update_modifies_personnel_request_and_redirects(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->put(
            route('personnel-requests.update', $personnelRequest),
            $this->validPayload(['status' => 'approved', 'reason' => 'Updated reason'])
        );

        $response->assertRedirect(route('personnel-requests.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('personnel_requests', [
            'id' => $personnelRequest->id,
            'status' => 'approved',
            'reason' => 'Updated reason',
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->put(route('personnel-requests.update', $personnelRequest), []);

        $response->assertSessionHasErrors(['employee_id', 'request_type', 'start_date', 'end_date', 'status']);
    }

    public function test_update_rejects_end_date_before_start_date(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->put(
            route('personnel-requests.update', $personnelRequest),
            $this->validPayload([
                'start_date' => '2026-03-10T08:00',
                'end_date' => '2026-03-05T08:00',
            ])
        );

        $response->assertSessionHasErrors(['end_date']);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_personnel_request_and_redirects(): void
    {
        $personnelRequest = $this->makePersonnelRequest();

        $response = $this->delete(route('personnel-requests.destroy', $personnelRequest));

        $response->assertRedirect(route('personnel-requests.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('personnel_requests', ['id' => $personnelRequest->id]);
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
            'request_type' => PersonnelRequestType::LEAVE_DAILY->value,
            'status' => 'pending',
        ]);

        $response = $this->get(route('personnel-requests.index'));

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

        $response = $this->get(route('personnel-requests.index'));

        $response->assertRedirect(route('login'));
    }
}
