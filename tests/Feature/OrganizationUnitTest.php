<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrganizationUnitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'hr.organization-units.*']),
            Permission::firstOrCreate(['name' => 'hr.employees.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
    }

    public function test_index_lists_units_for_active_company(): void
    {
        OrganizationUnit::factory()->create([
            'company_id' => $this->companyId,
            'name' => 'Finance',
        ]);
        OrganizationUnit::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'name' => 'Foreign Unit',
        ]);

        $response = $this->get(route('hr.organization-units.index'));

        $response->assertOk();
        $response->assertSee('Finance');
        $response->assertDontSee('Foreign Unit');
    }

    public function test_store_creates_organization_unit(): void
    {
        $response = $this->post(route('hr.organization-units.store'), [
            'name' => 'Finance',
            'code' => 'FIN',
            'description' => 'Money team',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('hr.organization-units.index'));
        $this->assertDatabaseHas('organization_units', [
            'company_id' => $this->companyId,
            'name' => 'Finance',
            'code' => 'FIN',
        ]);
    }

    public function test_employee_can_be_assigned_to_organization_unit(): void
    {
        $unit = OrganizationUnit::factory()->create(['company_id' => $this->companyId]);
        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $workShift = WorkShift::factory()->create(['company_id' => $this->companyId]);

        $response = $this->post(route('hr.employees.store'), [
            'code' => 'EMP-UNIT-1',
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'nationality' => 'iranian',
            'organization_unit_id' => $unit->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('hr.employees.index'));
        $this->assertDatabaseHas('employees', [
            'code' => 'EMP-UNIT-1',
            'organization_unit_id' => $unit->id,
        ]);
    }

    public function test_show_lists_assigned_employees(): void
    {
        $unit = OrganizationUnit::factory()->create([
            'company_id' => $this->companyId,
            'name' => 'Finance',
        ]);
        Employee::factory()->create([
            'company_id' => $this->companyId,
            'organization_unit_id' => $unit->id,
            'first_name' => 'Sara',
            'last_name' => 'Karimi',
        ]);

        $response = $this->get(route('hr.organization-units.show', $unit));

        $response->assertOk();
        $response->assertSee('Finance');
        $response->assertSee('Sara');
        $response->assertSee('Karimi');
    }
}
