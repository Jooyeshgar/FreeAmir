<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(array $grants): User
    {
        $year = FiscalYear::factory()->create();
        $user = User::factory()->create();
        $user->fiscalYears()->attach($year);
        config(['active-fiscal-year-id' => $year->id]);
        foreach ($grants as $grant) {
            $user->givePermissionTo(Permission::findOrCreate($grant, 'web'));
        }
        $this->actingAs($user);

        return $user;
    }

    public function test_authorized_users_can_manage_roles_and_their_permissions(): void
    {
        $this->signIn(['roles.*']);
        $permission = Permission::findOrCreate('documents.index', 'web');
        $this->get(route('roles.index'))->assertOk();
        $this->get(route('roles.create'))->assertOk();
        $this->post(route('roles.store'), ['name' => 'Bookkeeper', 'permissions' => [$permission->id]])
            ->assertRedirect(route('roles.index'));
        $role = Role::findByName('Bookkeeper');
        $this->assertTrue($role->hasPermissionTo($permission));
        $this->get(route('roles.edit', $role))->assertOk();
        $this->patch(route('roles.update', $role), ['name' => 'Accounts clerk'])
            ->assertRedirect(route('roles.index'));
        $this->assertSame('Accounts clerk', $role->fresh()->name);
        $this->assertCount(0, $role->fresh()->permissions);
        $this->delete(route('roles.destroy', $role))->assertRedirect(route('roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_authorized_users_can_manage_permissions_and_role_assignments(): void
    {
        $this->signIn(['permissions.*']);
        $first = Role::create(['name' => 'First']);
        $second = Role::create(['name' => 'Second']);
        $this->get(route('permissions.index'))->assertOk();
        $this->get(route('permissions.create'))->assertOk();
        $this->post(route('permissions.store'), ['name' => 'documents.custom', 'roles' => [$first->id]])
            ->assertRedirect(route('permissions.index'));
        $permission = Permission::findByName('documents.custom');
        $this->assertTrue($first->fresh()->hasPermissionTo($permission));
        $this->get(route('permissions.edit', $permission))->assertOk();
        $this->patch(route('permissions.update', $permission), ['name' => 'documents.custom-renamed', 'roles' => [$second->id]])
            ->assertRedirect(route('permissions.index'));
        $this->assertEquals([$second->id], $permission->fresh()->roles->modelKeys());
        $this->delete(route('permissions.destroy', $permission))->assertRedirect(route('permissions.index'));
        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_each_action_requires_its_own_permission(): void
    {
        $role = Role::create(['name' => 'Protected']);
        $permission = Permission::findOrCreate('documents.index', 'web');
        $this->signIn(['roles.index', 'permissions.index']);
        foreach (['roles' => $role, 'permissions' => $permission] as $resource => $model) {
            $this->get(route($resource.'.index'))->assertOk();
            $this->get(route($resource.'.create'))->assertForbidden();
            $this->get(route($resource.'.edit', $model))->assertForbidden();
            $this->post(route($resource.'.store'), ['name' => 'Forbidden'])->assertForbidden();
            $this->patch(route($resource.'.update', $model), ['name' => 'Forbidden'])->assertForbidden();
            $this->delete(route($resource.'.destroy', $model))->assertForbidden();
        }
    }

    public function test_navigation_links_follow_access_grants(): void
    {
        $user = $this->signIn(['users.index']);
        $menu = Blade::render('<x-menu />');
        $this->assertStringNotContainsString(route('roles.index'), $menu);
        $this->assertStringNotContainsString(route('permissions.index'), $menu);
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('permissions.index'))->assertForbidden();
        $user->givePermissionTo([Permission::findOrCreate('roles.index'), Permission::findOrCreate('permissions.index')]);
        $menu = Blade::render('<x-menu />');
        $this->assertStringContainsString('/management/system/permissions', $menu);
        $this->assertStringContainsString('/management/system/roles', $menu);
        $this->assertLessThan(strpos($menu, route('users.index')), strpos($menu, route('roles.index')));
        $this->assertLessThan(strpos($menu, route('users.index')), strpos($menu, route('permissions.index')));
    }

    public function test_invalid_permission_assignments_do_not_create_records(): void
    {
        $this->signIn(['permissions.*']);
        $this->post(route('permissions.store'), ['name' => 'invalid.test', 'roles' => [999999]])->assertSessionHasErrors('roles.0');
        $this->assertDatabaseMissing('permissions', ['name' => 'invalid.test']);
    }
}
