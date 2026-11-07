<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['app.email_verification' => false, 'active-fiscal-year-id' => null]);
    }

    public function test_seeder_uses_resource_grants_and_preserves_limited_roles(): void
    {
        $this->seedPermissions();

        foreach (['users.*', 'roles.*', 'permissions.*', 'activity-logs.*'] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
            $this->assertTrue(Role::findByName('Super-Admin', 'web')->permissions->contains('name', $permission));
        }

        foreach (['users.create', 'users.store', 'users.update', 'roles.index', 'permissions.update'] as $permission) {
            $this->assertDatabaseMissing('permissions', ['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = $this->userWithRole(__('Admin'));
        $this->assertTrue($admin->can('users.store'));
        $this->assertTrue($admin->can('activity-logs.details'));
        $this->assertFalse($admin->can('roles.update'));
        $this->assertFalse($admin->can('permissions.update'));
        $this->assertFalse($admin->can('access-super-admin-panel'));
        $this->assertFalse($admin->can('update-global-configs'));
        foreach (['users.index', 'users.impersonate', 'activity-logs.index', 'activity-logs.details'] as $legacy) {
            $this->assertFalse($admin->roles->first()->permissions->contains('name', $legacy));
        }

        $accountant = $this->userWithRole(__('Accountant'));
        $this->assertTrue($accountant->can('users.index'));
        $this->assertTrue($accountant->can('users.impersonate'));
        $this->assertTrue($accountant->can('activity-logs.details'));
        $this->assertFalse($accountant->can('users.store'));
        $this->assertFalse($accountant->can('roles.update'));
        $this->assertFalse($accountant->can('permissions.update'));
        $this->assertFalse($accountant->can('salary.payrolls.transition.pending-manager-approval-to-approved'));

        foreach ([__('Seller'), __('Warehousekeeper'), __('Employee')] as $role) {
            $user = $this->userWithRole($role);
            foreach (['users.index', 'roles.index', 'permissions.index', 'activity-logs.index', 'access-super-admin-panel'] as $permission) {
                $this->assertFalse($user->can($permission), "$role must not receive $permission");
            }
        }

        $seller = $this->userWithRole(__('Seller'));
        $this->assertTrue($seller->can('invoices.store'));
        $this->assertFalse($seller->can('invoices.approve'));
        $this->assertFalse($seller->can('invoices.payments.store'));
    }

    #[DataProvider('resources')]
    public function test_resource_wildcard_allows_only_matching_action_checks(string $resource, array $actions): void
    {
        $this->seedPermissions();
        $user = User::factory()->create();
        $user->givePermissionTo($resource.'.*');

        foreach ($actions as $action) {
            $this->assertTrue($user->can($resource.'.'.$action));
        }
        foreach (array_diff(['users', 'roles', 'permissions', 'activity-logs'], [$resource]) as $other) {
            $this->assertFalse($user->can($other.'.index'));
        }
        $this->assertFalse($user->can('access-super-admin-panel'));
        $this->assertFalse($user->can('update-global-configs'));
    }

    public static function resources(): array
    {
        $crud = ['index', 'create', 'store', 'edit', 'update', 'destroy'];

        return [
            'users' => ['users', $crud],
            'roles' => ['roles', $crud],
            'permissions' => ['permissions', $crud],
            'activity logs' => ['activity-logs', ['index', 'details']],
        ];
    }

    public function test_rerunning_seeder_replaces_legacy_role_grants_without_deleting_custom_assignments(): void
    {
        $this->seedPermissions();
        $legacy = Permission::create(['name' => 'users.update', 'guard_name' => 'web']);
        $unrelated = Permission::create(['name' => '*', 'guard_name' => 'web']);
        $custom = Role::create(['name' => 'Custom', 'guard_name' => 'web']);
        $custom->givePermissionTo($legacy);
        Role::findByName(__('Admin'), 'web')->givePermissionTo([$legacy, $unrelated]);
        Permission::create(['name' => 'users.*', 'guard_name' => 'api']);
        Permission::create(['name' => 'home', 'guard_name' => 'api']);
        $apiRole = Role::create(['name' => __('Admin'), 'guard_name' => 'api']);
        $apiRole->givePermissionTo('users.*');

        $this->seedPermissions();
        $permissionCount = Permission::count();
        $this->seedPermissions();

        $this->assertSame($permissionCount, Permission::count());
        $admin = Role::findByName(__('Admin'), 'web');
        $this->assertTrue($admin->permissions->contains('name', 'users.*'));
        $this->assertFalse($admin->permissions->contains('name', 'users.update'));
        $this->assertFalse($admin->permissions->contains('name', '*'));
        $this->assertTrue($custom->fresh()->hasPermissionTo($legacy));
        $this->assertSame(['users.*'], $apiRole->fresh()->permissions->pluck('name')->all());
        foreach (Role::where('guard_name', 'web')->get() as $role) {
            $this->assertFalse($role->permissions->contains('guard_name', 'api'));
        }
    }

    public function test_seeded_admin_can_manage_assigned_users_but_cannot_enter_platform_or_other_companies(): void
    {
        $this->seedPermissions();
        $admin = $this->userWithRole(__('Admin'));
        $ownYear = FiscalYear::create(['company_id' => Company::factory()->create()->id, 'year' => (int) toEnglish(jdate('Y'))]);
        $otherYear = FiscalYear::create(['company_id' => Company::factory()->create()->id, 'year' => (int) toEnglish(jdate('Y'))]);
        $admin->fiscalYears()->attach($ownYear);
        $ownUser = User::factory()->create();
        $ownUser->fiscalYears()->attach($ownYear);
        $otherUser = User::factory()->create();
        $otherUser->fiscalYears()->attach($otherYear);

        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertSee($ownUser->email)->assertDontSee($otherUser->email);
        $this->get(route('about'))->assertOk()->assertSee(route('users.index'), false);
        $this->get(route('users.create'))->assertOk();
        $this->get(route('users.edit', $ownUser))->assertOk();
        $this->get(route('users.edit', $otherUser))->assertForbidden();
        $this->get(route('users.show', $ownUser))->assertForbidden();
        $this->get(route('fiscal-years.show', $otherYear))->assertForbidden();
        foreach (['management.dashboard', 'management.activity-logs.index', 'roles.index', 'permissions.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->put(route('update-global-configs'), ['app_debug' => 'true'])->assertForbidden();

        $seller = $this->userWithRole(__('Seller'));
        $seller->fiscalYears()->attach($ownYear);
        $this->actingAs($seller)->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
        $this->get(route('about'))->assertOk()->assertDontSee(route('users.index'), false);
    }

    private function seedPermissions(): void
    {
        app(RolesAndPermissionsSeeder::class)->seedPermissionsAndRoles();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
