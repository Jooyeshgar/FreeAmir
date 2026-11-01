<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\ActivityLogService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImpersonationRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_and_leave_routes_are_unavailable_even_with_wildcard_permission(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(Permission::firstOrCreate(['name' => 'users.*']));
        $target = User::factory()->create();

        $this->assertFalse(Route::has('users.impersonate'));
        $this->assertFalse(Route::has('impersonation.leave'));
        $this->actingAs($actor)->post('/management/users/'.$target->id.'/impersonate')->assertNotFound();
        $this->post('/impersonation/leave')->assertNotFound();
        $this->assertAuthenticatedAs($actor);
    }

    public function test_old_impersonation_session_is_invalidated_before_a_write_can_run(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        Route::post('/test/retired-session', function () {
            Company::factory()->create(['name' => 'Must not be created']);

            return response()->noContent();
        })->middleware('web');

        $this->actingAs($target)->withSession([
            'impersonated_by' => $actor->id,
            'impersonator_guard' => 'web',
            'impersonator_guard_using' => null,
            'interface_mode' => 'workspace',
        ])->post('/test/retired-session')->assertRedirect(route('login'))
            ->assertSessionMissing('impersonated_by')
            ->assertSessionMissing('impersonator_guard')
            ->assertSessionMissing('impersonator_guard_using')
            ->assertSessionMissing('interface_mode');

        $this->assertGuest();
        $this->assertDatabaseMissing('companies', ['name' => 'Must not be created']);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_normal_workspace_and_user_detail_pages_have_no_impersonation_controls(): void
    {
        $year = FiscalYear::factory()->create();
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $year->users()->attach([$actor->id, $target->id]);
        $actor->givePermissionTo(Permission::firstOrCreate(['name' => 'users.*']));

        $this->actingAs($actor)->withCookie('active-fiscal-year-id', $year->id)
            ->get(route('users.index'))->assertOk()
            ->assertSee($target->email)->assertSee(route('users.edit', $target), false)
            ->assertDontSee('/impersonate', false)->assertDontSee('/impersonation/leave', false);

        $actor->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->get(route('users.show', $target))->assertOk()
            ->assertSee($target->email)->assertDontSee('/impersonate', false);
    }

    public function test_normal_user_administration_and_authorization_remain_available(): void
    {
        Notification::fake();
        $year = FiscalYear::factory()->create();
        $actor = User::factory()->create();
        $year->users()->attach($actor);
        $actor->givePermissionTo(Permission::firstOrCreate(['name' => 'users.*']));
        $role = Role::firstOrCreate(['name' => 'Workspace Operator']);
        $payload = [
            'name' => 'New Operator',
            'email' => 'operator@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => [$role->name],
            'fiscal_year' => [$year->id],
        ];

        $this->actingAs($actor)->withCookie('active-fiscal-year-id', $year->id)
            ->post(route('users.store'), $payload)->assertRedirect(route('users.index'))->assertSessionHasNoErrors();
        $target = User::query()->where('email', $payload['email'])->firstOrFail();
        $this->get(route('users.edit', $target))->assertOk();
        $this->put(route('users.update', $target), [...$payload, 'name' => 'Updated Operator'])
            ->assertRedirect(route('users.index'))->assertSessionHasNoErrors();
        $this->assertSame('Updated Operator', $target->fresh()->name);

        $outsiderYear = FiscalYear::factory()->create();
        $outsider = User::factory()->create();
        $outsiderYear->users()->attach($outsider);
        $this->delete(route('users.destroy', $outsider))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $outsider->id]);
        $this->delete(route('users.destroy', $target))->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_permission_migration_removes_old_grants_and_seeding_does_not_restore_them(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'users.impersonate']);
        $role = Role::firstOrCreate(['name' => 'Legacy Admin']);
        $actor = User::factory()->create();
        $role->givePermissionTo($permission);
        $actor->givePermissionTo($permission);

        $migration = require database_path('migrations/2026_11_01_090000_retire_user_impersonation_permission.php');
        $migration->up();
        $migration->up();
        app(RolesAndPermissionsSeeder::class)->seedPermissionsAndRoles();

        $this->assertDatabaseMissing('permissions', ['name' => 'users.impersonate']);
        $this->assertDatabaseMissing('role_has_permissions', ['permission_id' => $permission->id]);
        $this->assertDatabaseMissing('model_has_permissions', ['permission_id' => $permission->id]);
        $this->assertDatabaseHas('users', ['id' => $actor->id]);
        $this->assertDatabaseHas('permissions', ['name' => 'users.store']);
    }

    public function test_new_request_and_model_activity_use_the_authenticated_user_without_impersonation_metadata(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($actor);
        session()->put('impersonated_by', $other->id);

        $this->assertTrue(app(ActivityLogService::class)->resolveActor()->is($actor));
        $company = Company::factory()->create();
        $modelActivity = Activity::query()->where('model_id', $company->id)->where('model_type', Company::class)->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $modelActivity->user_id);
        $this->assertFalse($modelActivity->details->has('impersonated_user_id'));

        session()->forget('impersonated_by');
        $this->post(route('locale'), ['locale' => 'fa'])->assertRedirect();
        $requestActivity = Activity::query()->where('source', 'request')->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $requestActivity->user_id);
        $this->assertFalse($requestActivity->details->has('impersonated_user_id'));
    }

    public function test_historical_activity_keeps_its_actor_and_impersonated_account_readable(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['name' => 'Historical Target']);
        $actor->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $activity = activity('request')->causedBy($actor)->withProperties([
            'impersonated_user_id' => $target->id,
            'route' => 'users.update',
            'method' => 'PUT',
        ])->log('Historical impersonated update');

        $this->actingAs($actor)->get(route('management.activity-logs.index'))->assertOk()
            ->assertSee('Historical Target')->assertSee(__('Impersonated account'));
        $this->assertSame($actor->id, $activity->fresh()->user_id);
        $this->assertSame($target->id, $activity->fresh()->details->get('impersonated_user_id'));
    }
}
