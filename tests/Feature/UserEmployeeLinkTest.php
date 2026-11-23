<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserEmployeeLinkTest extends TestCase
{
    use RefreshDatabase;

    protected FiscalYear $fiscalYear;

    protected User $user;

    protected WorkSite $workSite;

    protected WorkShift $workShift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fiscalYear = FiscalYear::factory()->create();
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'users.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => $this->fiscalYear->id]);

        $this->workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        $this->workShift = WorkShift::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
    }

    public function test_index_shows_create_employee_button_for_users_without_employee(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertSee(__('Create Employee'));
        $response->assertSee(route('users.create-employee', $this->user));
    }

    public function test_index_shows_employee_link_when_employee_exists(): void
    {
        $employee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'work_site_id' => $this->workSite->id,
            'work_shift_id' => $this->workShift->id,
            'user_id' => $this->user->id,
            'code' => 'EMP-1001',
        ]);

        $response = $this->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertSee(__('View Employee'));
        $response->assertSee(route('hr.employees.show', $employee));
    }

    public function test_edit_defaults_to_an_empty_employee_option_and_can_save_without_linking(): void
    {
        $employee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'work_site_id' => $this->workSite->id,
            'work_shift_id' => $this->workShift->id,
            'user_id' => null,
        ]);
        $role = Role::create(['name' => 'Accountant']);
        $this->user->assignRole($role);

        $response = $this->get(route('users.edit', $this->user))->assertOk();
        $options = $this->employeeOptions($response->getContent());

        $this->assertSame('', $options->item(0)->getAttribute('value'));
        $this->assertSame(__('Choose Employee'), trim($options->item(0)->textContent));
        $this->assertTrue($options->item(0)->hasAttribute('selected'));
        $this->assertSame((string) $employee->id, $options->item(1)->getAttribute('value'));
        $this->assertFalse($options->item(1)->hasAttribute('selected'));

        $this->put(route('users.update', $this->user), [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'employee_id' => $options->item(0)->getAttribute('value'),
            'role' => [$role->name],
            'fiscal_year' => [$this->fiscalYear->id],
        ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

        $this->assertNull($employee->refresh()->user_id);
    }

    public function test_edit_keeps_the_linked_employee_selected(): void
    {
        $employee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'work_site_id' => $this->workSite->id,
            'work_shift_id' => $this->workShift->id,
            'user_id' => $this->user->id,
        ]);
        $unlinkedEmployee = $this->createEmployeeIn($this->fiscalYear);
        $role = Role::create(['name' => 'Accountant']);

        $response = $this->get(route('users.edit', $this->user))->assertOk();
        $options = $this->employeeOptions($response->getContent());

        $this->assertCount(2, $options);
        $this->assertSame('', $options->item(0)->getAttribute('value'));
        $this->assertFalse($options->item(0)->hasAttribute('selected'));
        $this->assertSame((string) $employee->id, $options->item(1)->getAttribute('value'));
        $this->assertTrue($options->item(1)->hasAttribute('selected'));

        $this->put(route('users.update', $this->user), $this->updatePayload($role, $employee))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $this->putJson(route('users.update', $this->user), $this->updatePayload($role, $unlinkedEmployee))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id' => __('The user is already linked to an employee.')]);

        $this->assertEquals($this->user->id, $employee->refresh()->user_id);
        $this->assertNull($unlinkedEmployee->refresh()->user_id);
    }

    public function test_edit_only_offers_employees_available_in_the_active_fiscal_year(): void
    {
        $employee = $this->createEmployeeIn($this->fiscalYear);
        $owner = User::factory()->create();
        $assignedEmployee = $this->createEmployeeIn($this->fiscalYear, $owner);
        $otherYear = FiscalYear::factory()->create([
            'company_id' => $this->fiscalYear->company_id,
            'year' => $this->fiscalYear->year - 1,
        ]);
        $this->user->fiscalYears()->syncWithoutDetaching([$otherYear->id]);
        $otherYearEmployee = $this->createEmployeeIn($otherYear);
        $otherCompanyEmployee = $this->createEmployeeIn(FiscalYear::factory()->create());
        $role = Role::create(['name' => 'Accountant']);

        $response = $this->get(route('users.edit', $this->user))->assertOk();
        $options = $this->employeeOptions($response->getContent());

        $this->assertCount(2, $options);
        $this->assertSame('', $options->item(0)->getAttribute('value'));
        $this->assertSame((string) $employee->id, $options->item(1)->getAttribute('value'));

        $this->putJson(route('users.update', $this->user), $this->updatePayload($role, $assignedEmployee))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id' => __('The selected employee is already assigned to another user.')]);

        foreach ([$otherYearEmployee, $otherCompanyEmployee] as $unavailableEmployee) {
            $this->putJson(route('users.update', $this->user), $this->updatePayload($role, $unavailableEmployee))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['employee_id' => __('The selected employee is unavailable.')]);

            $this->assertNull($unavailableEmployee->refresh()->user_id);
        }

        $this->assertEquals($owner->id, $assignedEmployee->refresh()->user_id);

        $this->put(route('users.update', $this->user), $this->updatePayload($role, $employee))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('users.index'));

        $this->assertEquals($this->user->id, $employee->refresh()->user_id);
    }

    public function test_employee_linking_requires_an_accessible_active_fiscal_year(): void
    {
        $employee = $this->createEmployeeIn($this->fiscalYear);
        $role = Role::create(['name' => 'Accountant']);
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->user->fiscalYears()->detach();

        $response = $this->get(route('users.edit', $this->user))->assertOk();
        $options = $this->employeeOptions($response->getContent());

        $this->assertCount(1, $options);
        $this->assertSame('', $options->item(0)->getAttribute('value'));

        $this->putJson(route('users.update', $this->user), $this->updatePayload($role, $employee))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id' => __('The selected employee is unavailable.')]);

        $this->assertNull($employee->refresh()->user_id);
    }

    public function test_create_employee_from_user(): void
    {
        $response = $this->post(route('users.create-employee', $this->user));

        $response->assertSessionHasNoErrors();

        $employee = Employee::withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($employee);
        $response->assertRedirect(route('hr.employees.show', $employee));

        $this->assertDatabaseHas('employees', [
            'user_id' => $this->user->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
        ]);
    }

    public function test_cannot_create_employee_from_user_in_closed_fiscal_year(): void
    {
        $this->fiscalYear->update(['closed_at' => now()]);

        $this->postJson(route('users.create-employee', $this->user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fiscal_year');

        $this->assertDatabaseMissing('employees', ['user_id' => $this->user->id]);
    }

    private function createEmployeeIn(FiscalYear $fiscalYear, ?User $owner = null): Employee
    {
        return Employee::factory()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'work_site_id' => WorkSite::factory()->create(['fiscal_year_id' => $fiscalYear->id])->id,
            'work_shift_id' => WorkShift::factory()->create(['fiscal_year_id' => $fiscalYear->id])->id,
            'user_id' => $owner?->id,
        ]);
    }

    private function updatePayload(Role $role, Employee $employee): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'employee_id' => $employee->id,
            'role' => [$role->name],
            'fiscal_year' => [$this->fiscalYear->id],
        ];
    }

    private function employeeOptions(string $html): \DOMNodeList
    {
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);

        return (new \DOMXPath($document))->query('//select[@id="employee_id"]/option');
    }
}
