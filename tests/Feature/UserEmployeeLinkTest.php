<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserEmployeeLinkTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected WorkSite $workSite;

    protected WorkShift $workShift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'users.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->company->id]);

        $this->workSite = WorkSite::factory()->create(['company_id' => $this->company->id]);
        $this->workShift = WorkShift::factory()->create(['company_id' => $this->company->id]);
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
            'company_id' => $this->company->id,
            'work_site_id' => $this->workSite->id,
            'work_shift_id' => $this->workShift->id,
            'user_id' => $this->user->id,
            'code' => 'EMP-1001',
        ]);

        $response = $this->get(route('users.index'));

        $response->assertStatus(200);
        $response->assertSee(__('View Employee'));
        $response->assertSee(route('employees.show', $employee));
    }

    public function test_create_employee_from_user(): void
    {
        $response = $this->post(route('users.create-employee', $this->user));

        $employee = Employee::withoutGlobalScopes()
            ->where('user_id', $this->user->id)
            ->first();

        $response->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employees', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
        ]);
    }
}
