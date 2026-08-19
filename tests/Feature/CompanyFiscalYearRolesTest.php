<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyFiscalYearRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['app.email_verification' => false]);
    }

    public function test_user_form_saves_different_roles_for_each_company(): void
    {
        $firstCompany = Company::factory()->create(['name' => 'First Company', 'fiscal_year' => 1404]);
        $secondCompany = Company::factory()->create(['name' => 'Second Company', 'fiscal_year' => 1405]);

        $actor = User::factory()->create();
        $actor->companies()->sync([$firstCompany->id, $secondCompany->id]);
        $platformRole = Role::create(['name' => 'platform-admin', 'company_id' => null]);
        $platformRole->givePermissionTo([
            Permission::firstOrCreate(['name' => 'access-super-admin-panel']),
            Permission::firstOrCreate(['name' => 'users.store']),
        ]);
        foreach ([$firstCompany, $secondCompany] as $company) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
            $actor->assignRole($platformRole);
        }

        Role::create(['name' => 'seller', 'company_id' => null]);
        Role::create(['name' => 'warehousekeeper', 'company_id' => null]);

        $this->actingAs($actor)->post(route('users.store'), [
            'name' => 'Scoped User',
            'email' => 'scoped@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_roles' => [
                $firstCompany->id => ['seller'],
                $secondCompany->id => ['warehousekeeper'],
            ],
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'scoped@example.com')->firstOrFail();

        app(PermissionRegistrar::class)->setPermissionsTeamId($firstCompany->id);
        $user->unsetRelation('roles');
        $this->assertSame(['seller'], $user->roles->pluck('name')->all());

        app(PermissionRegistrar::class)->setPermissionsTeamId($secondCompany->id);
        $user->unsetRelation('roles');
        $this->assertSame(['warehousekeeper'], $user->roles->pluck('name')->all());
    }

    public function test_active_company_controls_effective_role_permissions(): void
    {
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->sync([$firstCompany->id, $secondCompany->id]);

        $permission = Permission::firstOrCreate(['name' => 'invoices.index']);
        $seller = Role::create(['name' => 'seller', 'company_id' => null]);
        $seller->givePermissionTo($permission);

        app(PermissionRegistrar::class)->setPermissionsTeamId($firstCompany->id);
        $user->assignRole($seller);

        $this->assertTrue($user->can('invoices.index'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($secondCompany->id);
        app(PermissionRegistrar::class)->forgetWildcardPermissionIndex($user);
        $user->unsetRelation('roles');

        $this->assertFalse($user->can('invoices.index'));
    }

    public function test_global_super_admin_bypasses_permissions_in_an_active_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->sync([$company->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId(0);
        $user->assignRole(Role::create(['name' => 'Super-Admin', 'company_id' => null]));

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user->unsetRelation('roles');

        $this->assertTrue($user->can('permission-that-does-not-exist'));
    }

    public function test_roles_seeder_assigns_global_super_admin_and_company_roles(): void
    {
        $company = Company::factory()->create();
        config(['active-company-id' => $company->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $this->seed(RolesAndPermissionsSeeder::class);

        $superAdmin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue($superAdmin->companies()->whereKey($company->id)->exists());

        app(PermissionRegistrar::class)->setPermissionsTeamId(0);
        $superAdmin->unsetRelation('roles');
        $this->assertTrue($superAdmin->hasRole('Super-Admin'));

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $superAdmin->unsetRelation('roles');
        $this->assertTrue($superAdmin->hasRole(__('Admin')));
        $this->assertTrue($superAdmin->hasRole(__('Employee')));
    }
}
