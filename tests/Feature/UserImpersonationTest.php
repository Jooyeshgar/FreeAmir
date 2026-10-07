<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config(['app.email_verification' => false]);
    }

    private function userWithRole(string $roleName, ?FiscalYear $fiscalYear = null, bool $canImpersonate = false, bool $canViewUsers = false, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $role = $this->role($roleName);

        if ($canImpersonate) {
            $role->givePermissionTo($this->impersonationPermission());
        }

        if ($canViewUsers) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => 'users.index']));
        }

        $user->assignRole($role);

        if ($fiscalYear) {
            $user->fiscalYears()->attach($fiscalYear);
        }

        return $user;
    }

    private function role(string $roleName): Role
    {
        return Role::firstOrCreate([
            'name' => $roleName === 'Super-Admin' ? $roleName : __($roleName),
        ]);
    }

    private function impersonationPermission(): Permission
    {
        return Permission::firstOrCreate(['name' => 'users.impersonate']);
    }

    private function setActiveCompany(FiscalYear $fiscalYear): void
    {
        config(['active-fiscal-year-id' => $fiscalYear->id]);
    }

    private function assertImpersonationSessionIsClear(): void
    {
        $this->assertFalse(session()->exists('impersonated_by'));
        $this->assertFalse(session()->exists('impersonator_guard'));
        $this->assertFalse(session()->exists('impersonator_guard_using'));
    }

    private function fiscalYear(int $year): FiscalYear
    {
        return FiscalYear::factory()->create([
            'year' => $year,
        ]);
    }

    public function test_authorized_admin_can_impersonate_a_user_and_return(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear, canImpersonate: true);
        $target = $this->userWithRole('Employee', $fiscalYear, attributes: ['name' => 'Target User']);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($admin)->post(route('users.impersonate', $target))->assertRedirect(route('about'))->assertSessionHas('success');

        $this->assertAuthenticatedAs($target);
        $this->assertSame($admin->id, session('impersonated_by'));
        $this->assertTrue(session()->exists('impersonator_guard'));
        $this->assertTrue(session()->exists('impersonator_guard_using'));

        $this->get(route('about'))->assertOk()->assertSee(__('You are impersonating :name.', ['name' => $target->name]))
            ->assertSee(route('impersonation.leave'), false);

        $this->post(route('impersonation.leave'))->assertRedirect(route('users.index'))->assertSessionHas('success');

        $this->assertAuthenticatedAs($admin);
        $this->assertImpersonationSessionIsClear();
    }

    public function test_guest_cannot_start_impersonation(): void
    {
        $target = User::factory()->create();

        $this->post(route('users.impersonate', $target))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertImpersonationSessionIsClear();
    }

    public function test_guest_cannot_leave_impersonation(): void
    {
        $this->post(route('impersonation.leave'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_stale_impersonation_session_does_not_render_banner_for_guest(): void
    {
        $this->withSession(['impersonated_by' => 999999, 'impersonator_guard' => 'web', 'impersonator_guard_using' => null])
            ->get(route('login'))->assertOk()->assertDontSee(__('Return to administrator'))->assertDontSee(route('impersonation.leave'), false);

        $this->assertGuest();
    }

    public function test_impersonation_start_route_only_accepts_post_requests(): void
    {
        $target = User::factory()->create();

        $this->get(route('users.impersonate', $target))->assertStatus(405);
    }

    public function test_impersonation_leave_route_only_accepts_post_requests(): void
    {
        $this->get(route('impersonation.leave'))->assertStatus(405);
    }

    public function test_unknown_target_returns_not_found_without_changing_identity(): void
    {
        $admin = $this->userWithRole('Admin', canImpersonate: true);

        $this->actingAs($admin)->post(route('users.impersonate', ['user' => 999999]))->assertNotFound();

        $this->assertAuthenticatedAs($admin);
        $this->assertImpersonationSessionIsClear();
    }

    public function test_user_cannot_impersonate_itself(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear, canImpersonate: true);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($admin)->post(route('users.impersonate', $admin))->assertForbidden();

        $this->assertAuthenticatedAs($admin);
        $this->assertImpersonationSessionIsClear();
    }

    public function test_user_without_permission_cannot_impersonate(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear);
        $target = $this->userWithRole('Employee', $fiscalYear);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($admin)->post(route('users.impersonate', $target))->assertForbidden();

        $this->assertAuthenticatedAs($admin);
        $this->assertImpersonationSessionIsClear();
    }

    public function test_roleless_user_with_direct_permission_can_impersonate(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $actor = User::factory()->create();
        $actor->fiscalYears()->attach($fiscalYear);
        $actor->givePermissionTo($this->impersonationPermission());
        $target = $this->userWithRole('Employee', $fiscalYear);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($actor)->post(route('users.impersonate', $target))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_nested_impersonation_is_forbidden_and_original_session_is_preserved(): void
    {
        $superAdmin = $this->userWithRole('Super-Admin', canImpersonate: true);
        $admin = $this->userWithRole('Admin', $this->fiscalYear(1405), canImpersonate: true);
        $employee = $this->userWithRole('Employee');

        $this->actingAs($superAdmin)->post(route('users.impersonate', $admin))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($admin);
        $this->assertSame($superAdmin->id, session('impersonated_by'));

        $this->post(route('users.impersonate', $employee))->assertForbidden();

        $this->assertAuthenticatedAs($admin);
        $this->assertSame($superAdmin->id, session('impersonated_by'));
    }

    public function test_leave_route_is_forbidden_when_not_impersonating(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('impersonation.leave'))->assertForbidden();

        $this->assertAuthenticatedAs($user);
    }

    public function test_take_and_leave_events_contain_the_correct_users(): void
    {
        Event::fake([TakeImpersonation::class, LeaveImpersonation::class]);

        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear, canImpersonate: true);
        $target = $this->userWithRole('Employee', $fiscalYear);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($admin)->post(route('users.impersonate', $target));

        Event::assertDispatched(TakeImpersonation::class, fn (TakeImpersonation $event) => $event->impersonator->is($admin) && $event->impersonated->is($target));

        $this->post(route('impersonation.leave'));

        Event::assertDispatched(LeaveImpersonation::class, fn (LeaveImpersonation $event) => $event->impersonator->is($admin) && $event->impersonated->is($target));
    }

    public function test_failed_impersonation_does_not_dispatch_take_event(): void
    {
        Event::fake([TakeImpersonation::class]);

        $fiscalYear = $this->fiscalYear(1405);
        $actor = $this->userWithRole('Admin', $fiscalYear, canImpersonate: true);
        $target = $this->userWithRole('Super-Admin', $fiscalYear);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($actor)->post(route('users.impersonate', $target))->assertForbidden();

        Event::assertNotDispatched(TakeImpersonation::class);
        $this->assertAuthenticatedAs($actor);
        $this->assertImpersonationSessionIsClear();
    }

    public function test_banner_is_not_rendered_during_a_normal_authenticated_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('about'))->assertOk()->assertDontSee(__('Return to administrator'))
            ->assertDontSee(route('impersonation.leave'), false);
    }

    public function test_banner_remains_visible_across_impersonated_requests(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear, canImpersonate: true);
        $target = $this->userWithRole('Employee', $fiscalYear, attributes: ['name' => 'Persistent Target']);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($admin)->post(route('users.impersonate', $target));

        foreach (range(1, 2) as $requestNumber) {
            $this->get(route('about'))->assertOk()->assertSee('Persistent Target')->assertSee(__('Return to administrator'));
        }
    }

    public function test_unverified_users_with_a_company_can_be_impersonated_with_any_eligible_role(): void
    {
        $superAdmin = $this->userWithRole('Super-Admin', canImpersonate: true);
        $fiscalYear = $this->fiscalYear(1405);
        $targets = [];

        foreach (['Admin', 'Accountant', 'Warehousekeeper', 'Seller', 'Employee', null] as $targetRole) {
            $target = $targetRole === null ? User::factory()->create(['email_verified_at' => null]) : $this->userWithRole($targetRole, attributes: ['email_verified_at' => null]);
            $target->fiscalYears()->attach($fiscalYear);
            $targets[] = $target;

            $this->actingAs($superAdmin)->post(route('users.impersonate', $target))->assertRedirect(route('about'));
            $this->assertTrue($target->canBeImpersonated());
            $this->assertAuthenticatedAs($target);

            $this->post(route('impersonation.leave'))->assertRedirect(route('users.index'));
            $this->assertAuthenticatedAs($superAdmin);
            $this->assertImpersonationSessionIsClear();
        }

        $response = $this->withSession(['interface_mode' => 'management'])->get(route('users.index'))->assertOk();

        foreach ($targets as $target) {
            $response->assertSee(route('users.impersonate', $target), false);
        }
    }

    public function test_users_without_a_company_cannot_be_impersonated(): void
    {
        $superAdmin = $this->userWithRole('Super-Admin', canImpersonate: true);
        $targets = [
            User::factory()->create(),
            User::factory()->create(['email_verified_at' => null]),
        ];

        foreach ($targets as $target) {
            $this->actingAs($superAdmin)->post(route('users.impersonate', $target))->assertForbidden();

            $this->assertFalse($target->canBeImpersonated());
            $this->assertAuthenticatedAs($superAdmin);
            $this->assertImpersonationSessionIsClear();
        }

        $response = $this->withSession(['interface_mode' => 'management'])->get(route('users.index'))->assertOk();
        $response->assertSee(__('User has no company'));

        foreach ($targets as $target) {
            $response->assertDontSee(route('users.impersonate', $target), false);
        }
    }

    public function test_logout_while_impersonating_ends_the_entire_authenticated_session(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear, canImpersonate: true);
        $target = $this->userWithRole('Employee', $fiscalYear);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($admin)->post(route('users.impersonate', $target));
        $this->get(route('logout'))->assertRedirect('/login');

        $this->assertGuest();
        $this->assertImpersonationSessionIsClear();
    }

    public function test_a_new_impersonation_can_start_after_leaving_the_previous_one(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear, canImpersonate: true);
        $firstTarget = $this->userWithRole('Employee', $fiscalYear);
        $secondTarget = $this->userWithRole('Seller', $fiscalYear);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($admin)->post(route('users.impersonate', $firstTarget));
        $this->post(route('impersonation.leave'));
        $this->post(route('users.impersonate', $secondTarget))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($secondTarget);
        $this->assertSame($admin->id, session('impersonated_by'));
    }

    public function test_super_admin_can_impersonate_admin_without_shared_company(): void
    {
        $superAdmin = $this->userWithRole('Super-Admin', canImpersonate: true);
        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear);

        $this->actingAs($superAdmin)->post(route('users.impersonate', $admin))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_super_admin_with_inherited_admin_role_keeps_super_admin_impersonation_access(): void
    {
        $superAdmin = $this->userWithRole('Super-Admin', canImpersonate: true);
        $superAdmin->assignRole($this->role('Admin'));
        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear);

        $this->actingAs($superAdmin)->post(route('users.impersonate', $admin))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_no_user_can_impersonate_a_super_admin(): void
    {
        $actor = $this->userWithRole('Super-Admin', canImpersonate: true);
        $fiscalYear = $this->fiscalYear(1405);
        $target = $this->userWithRole('Super-Admin', $fiscalYear);

        $this->actingAs($actor)->post(route('users.impersonate', $target))->assertForbidden();

        $this->assertFalse($target->canBeImpersonated());
        $this->assertAuthenticatedAs($actor);
    }

    public function test_user_with_direct_platform_access_permission_cannot_be_impersonated(): void
    {
        $actor = $this->userWithRole('Super-Admin', canImpersonate: true);
        $fiscalYear = $this->fiscalYear(1405);
        $target = $this->userWithRole('Employee', $fiscalYear);
        $target->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));

        $this->actingAs($actor)->post(route('users.impersonate', $target))->assertForbidden();

        $this->assertAuthenticatedAs($actor);
    }

    public function test_user_with_platform_access_inherited_from_a_role_cannot_be_impersonated(): void
    {
        $actor = $this->userWithRole('Super-Admin', canImpersonate: true);
        $fiscalYear = $this->fiscalYear(1405);
        $target = $this->userWithRole('Employee', $fiscalYear);
        $platformRole = Role::create(['name' => 'Platform Auditor']);
        $platformRole->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $target->assignRole($platformRole);

        $this->actingAs($actor)->post(route('users.impersonate', $target))->assertForbidden();

        $this->assertAuthenticatedAs($actor);
    }

    public function test_admin_can_impersonate_every_non_super_admin_role_and_roleless_users(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $fiscalYear, canImpersonate: true);
        $this->setActiveCompany($fiscalYear);
        $this->actingAs($admin);

        foreach (['Admin', 'Accountant', 'Warehousekeeper', 'Seller', 'Employee', null] as $targetRole) {
            $target = $targetRole === null ? User::factory()->create() : $this->userWithRole($targetRole);
            $target->fiscalYears()->attach($fiscalYear);

            $this->post(route('users.impersonate', $target))->assertRedirect(route('about'));
            $this->assertAuthenticatedAs($target);

            $this->post(route('impersonation.leave'))->assertRedirect(route('users.index'));
            $this->assertAuthenticatedAs($admin);
        }
    }

    public function test_company_admin_can_impersonate_another_admin(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $actor = $this->userWithRole('Admin', $fiscalYear, canImpersonate: true);
        $target = $this->userWithRole('Admin', $fiscalYear);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($actor)->post(route('users.impersonate', $target))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_accountant_can_impersonate_every_non_super_admin_role_and_roleless_users(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $accountant = $this->userWithRole('Accountant', $fiscalYear, canImpersonate: true);
        $this->setActiveCompany($fiscalYear);
        $this->actingAs($accountant);

        foreach (['Admin', 'Accountant', 'Warehousekeeper', 'Seller', 'Employee', null] as $targetRole) {
            $target = $targetRole === null ? User::factory()->create() : $this->userWithRole($targetRole);
            $target->fiscalYears()->attach($fiscalYear);

            $this->post(route('users.impersonate', $target))->assertRedirect(route('about'));
            $this->assertAuthenticatedAs($target);

            $this->post(route('impersonation.leave'))->assertRedirect(route('users.index'));
            $this->assertAuthenticatedAs($accountant);
        }
    }

    public function test_accountant_cannot_impersonate_a_super_admin(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $accountant = $this->userWithRole('Accountant', $fiscalYear, canImpersonate: true);
        $this->setActiveCompany($fiscalYear);
        $target = $this->userWithRole('Super-Admin', $fiscalYear);

        $this->actingAs($accountant)->post(route('users.impersonate', $target))->assertForbidden();

        $this->assertAuthenticatedAs($accountant);
        $this->assertImpersonationSessionIsClear();
    }

    public function test_accountant_can_impersonate_user_with_accountant_and_lower_roles(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $accountant = $this->userWithRole('Accountant', $fiscalYear, canImpersonate: true);
        $target = User::factory()->create();
        $target->fiscalYears()->attach($fiscalYear);
        $target->assignRole([
            $this->role('Accountant'),
            $this->role('Seller'),
            $this->role('Employee'),
        ]);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($accountant)->post(route('users.impersonate', $target))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_accountant_can_impersonate_user_with_multiple_lower_roles(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $accountant = $this->userWithRole('Accountant', $fiscalYear, canImpersonate: true);
        $target = User::factory()->create();
        $target->fiscalYears()->attach($fiscalYear);
        $target->assignRole([
            $this->role('Seller'),
            $this->role('Warehousekeeper'),
            $this->role('Employee'),
        ]);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($accountant)->post(route('users.impersonate', $target))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_lower_roles_can_impersonate_with_direct_permission(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $target = $this->userWithRole('Employee', $fiscalYear);
        $this->setActiveCompany($fiscalYear);

        foreach (['Warehousekeeper', 'Seller', 'Employee'] as $actorRole) {
            $actor = $this->userWithRole($actorRole, $fiscalYear);
            $actor->givePermissionTo($this->impersonationPermission());

            $this->actingAs($actor)->post(route('users.impersonate', $target))->assertRedirect(route('about'));
            $this->assertAuthenticatedAs($target);

            $this->post(route('impersonation.leave'))->assertRedirect(route('users.index'));
            $this->assertAuthenticatedAs($actor);
            $this->assertImpersonationSessionIsClear();
        }
    }

    public function test_company_admin_cannot_impersonate_user_outside_all_its_companies(): void
    {
        $ownCompany = $this->fiscalYear(1405);
        $otherCompany = $this->fiscalYear(1406);
        $admin = $this->userWithRole('Admin', $ownCompany, canImpersonate: true);
        $target = $this->userWithRole('Employee', $otherCompany);
        $this->setActiveCompany($ownCompany);

        $this->actingAs($admin)->post(route('users.impersonate', $target))->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_company_admin_cannot_impersonate_user_who_is_not_in_active_company(): void
    {
        $activeCompany = $this->fiscalYear(1405);
        $secondCompany = $this->fiscalYear(1406);
        $admin = $this->userWithRole('Admin', $activeCompany, canImpersonate: true);
        $admin->fiscalYears()->attach($secondCompany);
        $target = $this->userWithRole('Employee', $secondCompany);
        $this->setActiveCompany($activeCompany);

        $this->actingAs($admin)->withCookie('active-fiscal-year-id', (string) $activeCompany->id)
            ->post(route('users.impersonate', $target))->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_company_admin_cannot_impersonate_user_with_an_inaccessible_extra_company(): void
    {
        $ownCompany = $this->fiscalYear(1405);
        $inaccessibleCompany = $this->fiscalYear(1406);
        $admin = $this->userWithRole('Admin', $ownCompany, canImpersonate: true);
        $target = $this->userWithRole('Employee', $ownCompany);
        $target->fiscalYears()->attach($inaccessibleCompany);
        $this->setActiveCompany($ownCompany);

        $this->actingAs($admin)->post(route('users.impersonate', $target))->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_company_admin_can_impersonate_user_when_all_target_companies_are_accessible(): void
    {
        $activeCompany = $this->fiscalYear(1405);
        $secondCompany = $this->fiscalYear(1406);
        $admin = $this->userWithRole('Admin', $activeCompany, canImpersonate: true);
        $admin->fiscalYears()->attach($secondCompany);
        $target = $this->userWithRole('Employee', $activeCompany);
        $target->fiscalYears()->attach($secondCompany);
        $this->setActiveCompany($activeCompany);

        $this->actingAs($admin)->withCookie('active-fiscal-year-id', (string) $activeCompany->id)
            ->post(route('users.impersonate', $target))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_platform_super_admin_bypasses_company_scope_for_eligible_target(): void
    {
        $superAdmin = $this->userWithRole('Super-Admin', canImpersonate: true);
        $target = $this->userWithRole('Employee', $this->fiscalYear(1407));

        $this->actingAs($superAdmin)->post(route('users.impersonate', $target))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_workspace_admin_list_shows_buttons_for_every_eligible_user(): void
    {
        $company = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $company, canImpersonate: true, canViewUsers: true);
        $employee = $this->userWithRole('Employee', $company);
        $unverifiedEmployee = $this->userWithRole('Employee', $company, attributes: ['email_verified_at' => null]);
        $otherAdmin = $this->userWithRole('Admin', $company);
        $superAdmin = $this->userWithRole('Super-Admin', $company);
        $this->setActiveCompany($company);

        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertDontSee($superAdmin->email)
            ->assertSee(route('users.impersonate', $employee), false)
            ->assertSee(route('users.impersonate', $otherAdmin), false)
            ->assertSee(route('users.impersonate', $unverifiedEmployee), false)
            ->assertDontSee(route('users.impersonate', $superAdmin), false)
            ->assertDontSee(route('users.impersonate', $admin), false);
    }

    public function test_accountant_list_shows_buttons_for_every_eligible_user(): void
    {
        $fiscalYear = $this->fiscalYear(1405);
        $accountant = $this->userWithRole('Accountant', $fiscalYear, canImpersonate: true, canViewUsers: true);
        $employee = $this->userWithRole('Employee', $fiscalYear);
        $otherAccountant = $this->userWithRole('Accountant', $fiscalYear);
        $this->setActiveCompany($fiscalYear);

        $this->actingAs($accountant)->get(route('users.index'))->assertOk()
            ->assertSee(route('users.impersonate', $employee), false)
            ->assertSee(route('users.impersonate', $otherAccountant), false)
            ->assertDontSee(route('users.impersonate', $accountant), false);
    }

    public function test_management_list_shows_admin_action_but_never_super_admin_action(): void
    {
        $actor = $this->userWithRole('Super-Admin', canImpersonate: true, canViewUsers: true);
        $admin = $this->userWithRole('Admin', $this->fiscalYear(1405));
        $otherSuperAdmin = $this->userWithRole('Super-Admin');

        $this->actingAs($actor)->withSession(['interface_mode' => 'management'])->get(route('users.index'))->assertOk()
            ->assertSee($admin->email)->assertSee($otherSuperAdmin->email)
            ->assertSee(__('Are you sure you want to impersonate this user?'))
            ->assertSee(__('Are you sure you want to delete this user?'))
            ->assertSee(route('users.impersonate', $admin), false)
            ->assertDontSee(route('users.impersonate', $otherSuperAdmin), false)
            ->assertDontSee(route('users.impersonate', $actor), false);
    }

    public function test_wildcard_users_permission_allows_an_eligible_admin_to_impersonate(): void
    {
        $company = $this->fiscalYear(1405);
        $admin = $this->userWithRole('Admin', $company);
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'users.*']));
        $target = $this->userWithRole('Employee', $company);
        $this->setActiveCompany($company);

        $this->actingAs($admin)->post(route('users.impersonate', $target))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($target);
    }

    public function test_localized_roles_can_impersonate_based_on_permission_after_locale_changes(): void
    {
        $company = $this->fiscalYear(1405);
        $adminRole = Role::create(['name' => trans('Admin', [], 'fa')]);
        $adminRole->givePermissionTo($this->impersonationPermission());
        $employeeRole = Role::create(['name' => trans('Employee', [], 'fa')]);

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $admin->fiscalYears()->attach($company);
        $target = User::factory()->create();
        $target->assignRole($employeeRole);
        $target->fiscalYears()->attach($company);
        app()->setLocale('en');
        $this->setActiveCompany($company);

        $this->actingAs($admin)->post(route('users.impersonate', $target))->assertRedirect(route('about'));

        $this->assertAuthenticatedAs($target);
    }
}
