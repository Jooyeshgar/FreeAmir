<?php

namespace Tests\Feature;

use App\Enums\EmployeeEmploymentType;
use App\Enums\EmployeeGender;
use App\Enums\EmployeeNationality;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected WorkSite $workSite;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'employees.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);

        $this->workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);
    }

    private function makeEmployee(array $overrides = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'work_site_id' => $this->workSite->id,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'EMP-0001',
            'first_name' => 'Ali',
            'last_name' => 'Hosseini',
            'nationality' => EmployeeNationality::IRANIAN->value,
            'work_site_id' => $this->workSite->id,
            'is_active' => '1',
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_returns_200(): void
    {
        $response = $this->get(route('employees.index'));

        $response->assertStatus(200);
    }

    public function test_index_lists_employees_for_active_company(): void
    {
        $this->makeEmployee(['first_name' => 'Reza', 'last_name' => 'Ahmadi']);
        $this->makeEmployee(['first_name' => 'Sara', 'last_name' => 'Karimi']);

        $response = $this->get(route('employees.index'));

        $response->assertStatus(200);
        $response->assertSee('Ahmadi');
        $response->assertSee('Karimi');
    }

    public function test_index_does_not_show_employees_from_other_companies(): void
    {
        $otherCompany = Company::factory()->create();
        $otherSite = WorkSite::factory()->create(['company_id' => $otherCompany->id]);
        Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'work_site_id' => $otherSite->id,
            'first_name' => 'Foreign',
            'last_name' => 'Employee',
        ]);

        $response = $this->get(route('employees.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Foreign');
    }

    public function test_index_filters_by_search(): void
    {
        $this->makeEmployee(['first_name' => 'Reza', 'last_name' => 'Ahmadi', 'code' => 'EMP-001']);
        $this->makeEmployee(['first_name' => 'Sara', 'last_name' => 'Karimi', 'code' => 'EMP-002']);

        $response = $this->get(route('employees.index', ['search' => 'Ahmadi']));

        $response->assertStatus(200);
        $response->assertSee('Ahmadi');
        $response->assertDontSee('Karimi');
    }

    public function test_index_filters_by_is_active(): void
    {
        $this->makeEmployee(['first_name' => 'Active', 'last_name' => 'Worker', 'is_active' => true, 'code' => 'EMP-A01']);
        $this->makeEmployee(['first_name' => 'Inactive', 'last_name' => 'Worker', 'is_active' => false, 'code' => 'EMP-I01']);

        $response = $this->get(route('employees.index', ['is_active' => '1']));

        $response->assertStatus(200);
        $response->assertSee('Active');
        $response->assertDontSee('Inactive');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('employees.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_an_employee_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('employees.store'), $payload);

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'company_id' => $this->companyId,
            'code' => 'EMP-0001',
            'first_name' => 'Ali',
            'last_name' => 'Hosseini',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('employees.store'), []);

        $response->assertSessionHasErrors(['code', 'first_name', 'last_name', 'nationality', 'work_site_id']);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $this->makeEmployee(['code' => 'EMP-0001']);

        $response = $this->post(route('employees.store'), $this->validPayload(['code' => 'EMP-0001']));

        $response->assertSessionHasErrors(['code']);
    }

    public function test_store_rejects_duplicate_national_code(): void
    {
        $this->makeEmployee(['national_code' => '1234567890', 'code' => 'EMP-001']);

        $response = $this->post(route('employees.store'), $this->validPayload([
            'national_code' => '1234567890',
            'code' => 'EMP-002',
        ]));

        $response->assertSessionHasErrors(['national_code']);
    }

    public function test_store_validates_national_code_length(): void
    {
        $response = $this->post(route('employees.store'), $this->validPayload([
            'national_code' => '123',
        ]));

        $response->assertSessionHasErrors(['national_code']);
    }

    public function test_store_validates_invalid_nationality_enum(): void
    {
        $response = $this->post(route('employees.store'), $this->validPayload([
            'nationality' => 'alien',
        ]));

        $response->assertSessionHasErrors(['nationality']);
    }

    // ----------------------------------------------------------------
    // show
    // ----------------------------------------------------------------

    public function test_show_returns_200(): void
    {
        $employee = $this->makeEmployee();

        $response = $this->get(route('employees.show', $employee));

        $response->assertStatus(200);
        $response->assertSee($employee->first_name);
        $response->assertSee($employee->last_name);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $employee = $this->makeEmployee();

        $response = $this->get(route('employees.edit', $employee));

        $response->assertStatus(200);
        $response->assertSee($employee->first_name);
    }

    public function test_update_saves_changes_and_redirects(): void
    {
        $employee = $this->makeEmployee(['first_name' => 'OldName', 'code' => 'EMP-OLD']);

        $payload = $this->validPayload([
            'code' => 'EMP-OLD',
            'first_name' => 'NewName',
            'last_name' => 'Updated',
        ]);

        $response = $this->put(route('employees.update', $employee), $payload);

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'first_name' => 'NewName',
            'last_name' => 'Updated',
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $employee = $this->makeEmployee();

        $response = $this->put(route('employees.update', $employee), []);

        $response->assertSessionHasErrors(['code', 'first_name', 'last_name', 'nationality', 'work_site_id']);
    }

    public function test_update_allows_same_code_for_self(): void
    {
        $employee = $this->makeEmployee(['code' => 'EMP-SAME']);

        $response = $this->put(
            route('employees.update', $employee),
            $this->validPayload(['code' => 'EMP-SAME'])
        );

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('success');
    }

    public function test_update_rejects_duplicate_code_from_another_employee(): void
    {
        $this->makeEmployee(['code' => 'EMP-OTHER']);
        $employee = $this->makeEmployee(['code' => 'EMP-SELF']);

        $response = $this->put(
            route('employees.update', $employee),
            $this->validPayload(['code' => 'EMP-OTHER'])
        );

        $response->assertSessionHasErrors(['code']);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_employee_and_redirects(): void
    {
        $employee = $this->makeEmployee();

        $response = $this->delete(route('employees.destroy', $employee));

        $response->assertRedirect(route('employees.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }

    // ----------------------------------------------------------------
    // enum / optional fields
    // ----------------------------------------------------------------

    public function test_store_accepts_optional_enum_fields(): void
    {
        $payload = $this->validPayload([
            'gender' => EmployeeGender::MALE->value,
            'employment_type' => EmployeeEmploymentType::PERMANENT->value,
        ]);

        $response = $this->post(route('employees.store'), $payload);

        $response->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'code' => 'EMP-0001',
            'gender' => EmployeeGender::MALE->value,
            'employment_type' => EmployeeEmploymentType::PERMANENT->value,
        ]);
    }

    public function test_store_rejects_invalid_gender_enum(): void
    {
        $response = $this->post(route('employees.store'), $this->validPayload([
            'gender' => 'unknown',
        ]));

        $response->assertSessionHasErrors(['gender']);
    }

    public function test_store_rejects_contract_end_date_before_start_date(): void
    {
        $response = $this->post(route('employees.store'), $this->validPayload([
            'contract_start_date' => '2026-06-01',
            'contract_end_date' => '2026-01-01',
        ]));

        $response->assertSessionHasErrors(['contract_end_date']);
    }
}
