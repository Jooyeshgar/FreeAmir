<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FiscalYear;
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

    private int $fiscalYearId;

    protected function setUp(): void
    {
        parent::setUp();

        $fiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        $this->fiscalYearId = $fiscalYear->id;

        $this->user = User::factory()->create();
        $fiscalYear->users()->attach($this->user);
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'hr.organization-units.*']),
            Permission::firstOrCreate(['name' => 'hr.employees.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => $this->fiscalYearId]);
    }

    public function test_index_lists_units_for_active_company(): void
    {
        OrganizationUnit::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'name' => 'Finance',
        ]);
        OrganizationUnit::factory()->create([
            'fiscal_year_id' => FiscalYear::factory()->create(['year' => 1406])->id,
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
            'fiscal_year_id' => $this->fiscalYearId,
            'name' => 'Finance',
            'code' => 'FIN',
        ]);
    }

    public function test_store_rejects_parent_from_another_company(): void
    {
        $foreignParent = OrganizationUnit::factory()->create([
            'fiscal_year_id' => FiscalYear::factory()->create(['year' => 1406])->id,
        ]);

        $response = $this->post(route('hr.organization-units.store'), [
            'name' => 'Finance',
            'code' => 'FIN',
            'parent_id' => $foreignParent->id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('organization_units', [
            'fiscal_year_id' => $this->fiscalYearId,
            'name' => 'Finance',
            'parent_id' => $foreignParent->id,
        ]);
    }

    public function test_employee_can_be_assigned_to_organization_unit(): void
    {
        $unit = OrganizationUnit::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $workShift = WorkShift::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);

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

    public function test_employee_cannot_be_assigned_to_organization_unit_from_another_company(): void
    {
        $foreignUnit = OrganizationUnit::factory()->create([
            'fiscal_year_id' => FiscalYear::factory()->create(['year' => 1406])->id,
        ]);
        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $workShift = WorkShift::factory()->create(['fiscal_year_id' => $this->fiscalYearId]);

        $response = $this->post(route('hr.employees.store'), [
            'code' => 'EMP-UNIT-FOREIGN',
            'first_name' => 'Ali',
            'last_name' => 'Ahmadi',
            'nationality' => 'iranian',
            'organization_unit_id' => $foreignUnit->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors('organization_unit_id');
        $this->assertDatabaseMissing('employees', [
            'code' => 'EMP-UNIT-FOREIGN',
            'organization_unit_id' => $foreignUnit->id,
        ]);
    }

    public function test_show_lists_assigned_employees(): void
    {
        $unit = OrganizationUnit::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'name' => 'Finance',
        ]);
        Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
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
