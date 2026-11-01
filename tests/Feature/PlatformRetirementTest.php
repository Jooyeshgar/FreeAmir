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
use Tests\TestCase;

class PlatformRetirementTest extends TestCase
{
    use RefreshDatabase;

    public static function legacyAccess(): array
    {
        return [['direct'], ['inherited'], ['super-admin']];
    }

    private function legacyUser(string $access): User
    {
        $user = User::factory()->create();
        $permission = Permission::findOrCreate('access-super-admin-panel');
        if ($access === 'direct') {
            $user->givePermissionTo($permission);
        } else {
            $role = Role::findOrCreate($access === 'super-admin' ? 'Super-Admin' : 'Legacy platform role');
            $role->givePermissionTo($permission);
            $user->assignRole($role);
        }

        foreach (['home', 'customers.index', 'companies.index', 'companies.edit', 'companies.update',
            'companies.destroy', 'fiscal-years.index', 'fiscal-years.create', 'fiscal-years.store',
            'fiscal-years.show', 'fiscal-years.edit', 'fiscal-years.update', 'fiscal-years.destroy',
            'users.index', 'users.show', 'users.create', 'users.store', 'users.edit', 'users.update', 'users.destroy',
            'users.impersonate'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name));
        }

        return $user;
    }

    private function year(string $name): FiscalYear
    {
        return FiscalYear::create(['company_id' => Company::factory()->create(['name' => $name])->id, 'year' => 1405]);
    }

    #[DataProvider('legacyAccess')]
    public function test_platform_routes_are_unavailable(string $access): void
    {
        $user = $this->legacyUser($access);
        $this->actingAs($user);
        $this->assertFalse($user->can('access-super-admin-panel'));

        foreach (['/management', '/management/settings', '/management/activity-logs', '/management/roles',
            '/management/permissions', '/management/companies/1',
            '/management/activity-logs/1/details', '/roles', '/permissions'] as $path) {
            $this->assertContains($this->get($path)->getStatusCode(), [404, 405]);
        }
        $this->put('/management/settings', ['app_debug' => 'true'])->assertNotFound();
        $this->assertContains($this->post('/management/users/1/verify')->getStatusCode(), [404, 405]);
        $this->get('/about')->assertOk()->assertDontSee('/management/settings', false);
    }

    #[DataProvider('legacyAccess')]
    public function test_legacy_platform_permission_does_not_change_login_flow(string $access): void
    {
        $user = $this->legacyUser($access);
        $year = $this->year('Assigned workspace');
        $user->fiscalYears()->attach($year);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/');
        $this->withCookie('active-fiscal-year-id', (string) $year->id)->withSession(['interface_mode' => 'management'])
            ->get('/')->assertOk()->assertDontSee('management-drawer', false)->assertDontSee('href="'.url('/management').'"', false);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[DataProvider('legacyAccess')]
    public function test_company_and_year_access_requires_assignments_despite_legacy_grants(string $access): void
    {
        $user = $this->legacyUser($access);
        $own = $this->year('Assigned Business');
        $other = $this->year('Private Business');
        $user->fiscalYears()->attach($own);
        config(['active-fiscal-year-id' => $own->id]);
        $this->actingAs($user)->withCookie('active-fiscal-year-id', (string) $own->id)
            ->withSession(['interface_mode' => 'management']);

        $this->get(route('companies.index'))->assertOk()->assertViewIs('companies.workspace-index')
            ->assertSee('Assigned Business')->assertDontSee('Private Business');
        $this->get(route('fiscal-years.index'))->assertOk()->assertViewIs('fiscal-years.workspace-index')
            ->assertSee('Assigned Business')->assertDontSee('Private Business');
        $this->get(route('companies.edit', $own->company))->assertOk();
        $this->get(route('fiscal-years.show', $own))->assertOk();
        $this->get(route('companies.edit', $other->company))->assertForbidden();
        $this->put(route('companies.update', $other->company), [])->assertForbidden();
        $this->delete(route('companies.destroy', $other))->assertForbidden();
        $this->get(route('fiscal-years.show', $other))->assertForbidden();
        $this->get(route('fiscal-years.edit', $other))->assertForbidden();
        $this->put(route('fiscal-years.update', $other), ['year' => 1406])->assertForbidden();
        $this->delete(route('fiscal-years.destroy', $other))->assertForbidden();
        $this->post(route('fiscal-years.store'), ['company_id' => $other->company_id, 'year' => 1406])->assertNotFound();
        $this->assertDatabaseHas('fiscal_years', ['id' => $other->id, 'year' => 1405]);
    }

    #[DataProvider('legacyAccess')]
    public function test_workspace_user_administration_cannot_reach_other_years(string $access): void
    {
        $actor = $this->legacyUser($access);
        $own = $this->year('Assigned users');
        $other = $this->year('Private users');
        $actor->fiscalYears()->attach($own);
        $ownUser = User::factory()->create();
        $ownUser->fiscalYears()->attach($own);
        $otherUser = User::factory()->create();
        $otherUser->fiscalYears()->attach($other);
        config(['active-fiscal-year-id' => $own->id]);
        $this->actingAs($actor)->withCookie('active-fiscal-year-id', (string) $own->id)
            ->withSession(['interface_mode' => 'management']);

        $this->get(route('users.index'))->assertOk()->assertViewIs('users.workspace-index')
            ->assertSee($ownUser->email)->assertDontSee($otherUser->email);
        $this->get(route('users.edit', $ownUser))->assertOk()->assertSee('Assigned users')->assertDontSee('Private users');
        $this->get(route('users.show', $ownUser))->assertOk()->assertViewIs('users.show')->assertDontSee('/verify', false);
        $this->get(route('users.show', $otherUser))->assertForbidden();
        $this->get(route('users.edit', $otherUser))->assertForbidden();
        $this->put(route('users.update', $otherUser), [])->assertForbidden();
        $this->delete(route('users.destroy', $otherUser))->assertForbidden();
        $this->post(route('users.impersonate', $otherUser))->assertForbidden();
        $this->assertAuthenticatedAs($actor);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id]);
    }

    public function test_retirement_migration_preserves_users_roles_and_workspace_grants(): void
    {
        $user = $this->legacyUser('inherited');
        $year = $this->year('Retained assignment');
        $user->fiscalYears()->attach($year);
        $roleIds = Role::pluck('id')->all();
        $migration = require database_path('migrations/2026_11_01_000001_retire_platform_access_permission.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseMissing('permissions', ['name' => 'access-super-admin-panel']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame($roleIds, Role::pluck('id')->all());
        $this->assertTrue($user->fresh()->can('companies.index'));
        $this->assertTrue($user->fresh()->fiscalYears()->whereKey($year->id)->exists());
        app(RolesAndPermissionsSeeder::class)->seedPermissionsAndRoles();
        $this->assertDatabaseMissing('permissions', ['name' => 'access-super-admin-panel']);
    }
}
