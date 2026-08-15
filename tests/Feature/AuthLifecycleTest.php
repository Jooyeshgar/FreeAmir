<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Config;
use App\Models\Scopes\FiscalYearScope;
use App\Models\User;
use App\Notifications\UserVerificationNotification;
use App\Services\GlobalConfigService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        config([
            'app.registration' => true,
            'app.email_verification' => true,
            'active-company-id' => null,
        ]);
    }

    public function test_admin_is_limited_to_its_companies_and_maintainer_pages_are_forbidden(): void
    {
        $permissions = collect([
            'companies.index',
            'companies.edit',
            'users.index',
            'users.create',
            'users.store',
            'users.edit',
        ])->mapWithKeys(fn (string $name) => [$name => Permission::firstOrCreate(['name' => $name])]);

        $adminRole = Role::create(['name' => __('Admin')]);
        $adminRole->syncPermissions($permissions);
        Role::create(['name' => 'Super-Admin']);

        $ownCompany = $this->company('Admin Company');
        $otherCompany = $this->company('Other Company');
        $admin = User::factory()->create();
        $admin->companies()->attach($ownCompany);
        $admin->assignRole($adminRole);
        config(['active-company-id' => $ownCompany->id]);

        $ownUser = User::factory()->create(['name' => 'Own Company User']);
        $ownUser->companies()->attach($ownCompany);
        $otherUser = User::factory()->create(['name' => 'Other Company User']);
        $otherUser->companies()->attach($otherCompany);

        $this->actingAs($admin)->get(route('roles.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('permissions.index'))->assertForbidden();
        $this->actingAs($admin)->put(route('update-global-configs'), [
            'app_debug' => 'true',
        ])->assertForbidden();

        $about = $this->actingAs($admin)->get(route('about'));
        $about->assertOk();
        $about->assertDontSee(route('update-global-configs'), false);
        $about->assertDontSee(route('roles.index'), false);
        $about->assertDontSee(route('permissions.index'), false);

        $companies = $this->actingAs($admin)->get(route('companies.index'));
        $companies->assertOk();
        $companies->assertSee('Admin Company');
        $companies->assertDontSee('Other Company');
        $companies->assertDontSee(__('Companies and fiscal years'));
        $this->actingAs($admin)->get(route('companies.edit', $otherCompany))->assertForbidden();

        $users = $this->actingAs($admin)->get(route('users.index'));
        $users->assertOk();
        $users->assertSee('Own Company User');
        $users->assertDontSee('Other Company User');
        $users->assertDontSee(__('Platform users'));
        $this->actingAs($admin)->get(route('users.edit', $otherUser))->assertForbidden();

        $this->actingAs($admin)->get(route('users.create'))
            ->assertOk()
            ->assertDontSee('Super-Admin');
        $this->actingAs($admin)->get(route('users.edit', $ownUser))
            ->assertOk()
            ->assertDontSee('Super-Admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Escalated User',
            'email' => 'escalated@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => ['Super-Admin'],
            'company' => [$otherCompany->id],
        ])->assertSessionHasErrors(['role', 'company']);
        $this->assertDatabaseMissing('users', ['email' => 'escalated@example.com']);
    }

    public function test_role_and_permission_pages_are_not_available_in_the_workspace(): void
    {
        $roleIndex = Permission::firstOrCreate(['name' => 'roles.index']);
        $permissionIndex = Permission::firstOrCreate(['name' => 'permissions.index']);
        $viewerRole = Role::create(['name' => 'management-viewer']);
        $viewerRole->syncPermissions([$roleIndex, $permissionIndex]);

        $company = $this->company('Viewer Company');
        $viewer = User::factory()->create();
        $viewer->companies()->attach($company);
        $viewer->assignRole($viewerRole);
        config(['active-company-id' => $company->id]);

        $workspace = $this->actingAs($viewer)->get(route('about'));
        $workspace->assertOk();
        $workspace->assertDontSee(route('roles.index'), false);
        $workspace->assertDontSee(route('permissions.index'), false);

        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('permissions.index'))->assertForbidden();
    }

    public function test_platform_management_routes_use_the_management_prefix(): void
    {
        foreach ([
            'management.dashboard',
            'management.settings',
            'update-global-configs',
            'companies.index',
            'users.index',
            'roles.index',
            'permissions.index',
            'configs.index',
        ] as $routeName) {
            $this->assertStringStartsWith('/management', parse_url(route($routeName), PHP_URL_PATH));
        }
    }

    public function test_platform_admin_can_change_language_from_the_management_header(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));

        $management = $this->actingAs($user)->get(route('management.dashboard'));

        $management->assertOk()
            ->assertSee('id="management-locale-fa-form"', false)
            ->assertSee('id="management-locale-en-form"', false)
            ->assertSee('action="'.route('locale').'"', false);

        $this->post(route('locale'), ['locale' => 'en'])->assertRedirect(route('management.dashboard'))->assertSessionHas('locale', 'en');

        $this->get(route('management.dashboard'))->assertOk()->assertSee('<html lang="en"', false);
    }

    public function test_platform_admin_can_switch_between_management_and_current_workspace(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'access-super-admin-panel']),
            Permission::firstOrCreate(['name' => 'home']),
            Permission::firstOrCreate(['name' => 'customers.index']),
            Permission::firstOrCreate(['name' => 'companies.index']),
            Permission::firstOrCreate(['name' => 'users.index']),
            Permission::firstOrCreate(['name' => 'roles.index']),
            Permission::firstOrCreate(['name' => 'permissions.index']),
        );

        $company = Company::create([
            'name' => 'Current Workspace',
            'fiscal_year' => (int) toEnglish(jdate('Y')),
            'currency' => 'Rial',
        ]);
        $user->companies()->attach($company);

        $workspace = $this->actingAs($user)
            ->withCookie('active-company-id', (string) $company->id)
            ->get(route('home'));

        $workspace->assertOk();
        $workspace->assertSee(route('management.dashboard'), false);
        $workspace->assertDontSee(route('roles.index'), false);
        $workspace->assertDontSee(route('permissions.index'), false);
        $this->assertSame($company->id, config('active-company-id'));

        $about = $this->get(route('about'));
        $about->assertOk();
        $about->assertDontSee(route('update-global-configs'), false);
        $about->assertDontSee('gc_modal_', false);

        $sharedIndexRoutes = [
            'companies.index' => __('Companies and fiscal years'),
            'users.index' => __('Platform users'),
        ];

        foreach ($sharedIndexRoutes as $routeName => $managementHeading) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertDontSee($managementHeading);
        }

        foreach ([
            'roles.index' => __('Roles and access policies'),
            'permissions.index' => __('Permission catalog'),
        ] as $routeName => $managementHeading) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee($managementHeading);
        }

        $management = $this->get(route('management.dashboard'));
        $management->assertOk();
        $management->assertSee(route('home'), false);

        foreach ($sharedIndexRoutes as $routeName => $managementHeading) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee($managementHeading);
        }

        $company->update(['fiscal_year' => (int) toEnglish(jdate('Y')) - 1]);

        $this->get(route('home'))->assertRedirect(route('management.dashboard'));
    }

    public function test_super_admin_is_hidden_from_workspace_users_but_visible_in_management_users(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(
            Permission::firstOrCreate(['name' => 'access-super-admin-panel']),
            Permission::firstOrCreate(['name' => 'users.index']),
        );

        $superAdmin = User::factory()->create(['email' => 'hidden-super-admin@example.com']);
        $superAdmin->assignRole(Role::create(['name' => 'Super-Admin']));

        $this->actingAs($actor)->withSession(['interface_mode' => 'workspace'])->get(route('users.index'))->assertOk()
            ->assertDontSee('hidden-super-admin@example.com');

        $this->withSession(['interface_mode' => 'management'])->get(route('users.index'))->assertOk()
            ->assertSee('hidden-super-admin@example.com');
    }

    public function test_super_admin_dashboard_links_users_and_uses_edit_actions(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(Role::create(['name' => 'Super-Admin']));
        $company = $this->company('Recent Fiscal Company');
        $target = User::factory()->unverified()->create(['name' => 'Recent Unverified User']);
        $target->companies()->attach($company);
        $companylessTarget = User::factory()->create(['name' => 'Recent Companyless User']);

        $dashboard = $this->actingAs($actor)->get(route('management.dashboard'))->assertOk();

        $dashboard->assertSee('Recent Unverified User')
            ->assertSee(route('users.show', $target), false)
            ->assertSee(route('users.verify', $target), false)
            ->assertSee(route('users.impersonate', $target), false)
            ->assertSee(route('users.edit', $target), false)
            ->assertSee(route('companies.edit', $company), false)
            ->assertSee('Recent Companyless User')
            ->assertSee(__('User has no company'))
            ->assertDontSee(route('users.impersonate', $companylessTarget), false)
            ->assertSee(__('Edit'))
            ->assertDontSee('>'.__('Manage').'</a>', false);

        $this->get(route('users.index'))->assertOk()
            ->assertSee(route('users.show', $target), false)
            ->assertSee(route('users.verify', $target), false)
            ->assertSee('Recent Companyless User')
            ->assertSee(__('User has no company'))
            ->assertDontSee(route('users.impersonate', $companylessTarget), false);
    }

    public function test_user_show_displays_profile_roles_company_and_account_timestamps(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(Role::create(['name' => 'Super-Admin']));
        $company = Company::create([
            'name' => 'Profile Company',
            'fiscal_year' => 1405,
            'currency' => 'Rial',
            'address' => 'Profile Street',
            'phone_number' => '02112345678',
            'national_code' => '1234567890',
            'economical_code' => '987654321',
            'postal_code' => '1111111111',
        ]);
        $createdAt = now()->subDays(5)->startOfMinute();
        $updatedAt = now()->subDays(2)->startOfMinute();
        $verifiedAt = now()->subDay()->startOfMinute();
        $target = User::factory()->create([
            'name' => 'Profile User',
            'email' => 'profile@example.com',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
            'email_verified_at' => $verifiedAt,
        ]);
        $target->assignRole(Role::create(['name' => 'Profile Auditor']));
        $target->companies()->attach($company);

        $this->actingAs($actor)->get(route('users.show', $target))->assertOk()
            ->assertSee('<table', false)
            ->assertSee('Profile User')
            ->assertSee('profile@example.com')
            ->assertSee('bg-indigo-100', false)
            ->assertSee('text-indigo-700', false)
            ->assertSee('text-slate-600 dark:text-slate-100/80', false)
            ->assertSee('Profile Auditor')
            ->assertSee('Profile Company')
            ->assertSee('Profile Street')
            ->assertSee('1234567890')
            ->assertSee('987654321')
            ->assertSee('1111111111')
            ->assertSee('02112345678')
            ->assertSee(route('users.destroy', $target), false)
            ->assertSee(localizeNumber(1405))
            ->assertSee(formatDateTime($createdAt))
            ->assertSee(formatDateTime($updatedAt))
            ->assertSee(formatDateTime($verifiedAt))
            ->assertSee(__('Verified'));
    }

    public function test_user_show_requires_super_admin_panel_access(): void
    {
        $target = User::factory()->create();
        $workspaceViewer = User::factory()->create();
        $workspaceViewer->givePermissionTo(Permission::firstOrCreate(['name' => 'users.show']));

        $this->actingAs($workspaceViewer)->get(route('users.show', $target))->assertForbidden();

        $platformViewer = User::factory()->create();
        $platformViewer->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));

        $this->actingAs($platformViewer)->get(route('users.show', $target))->assertOk();
    }

    public function test_user_show_displays_pending_and_empty_assignment_states(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(Role::create(['name' => 'Super-Admin']));
        $target = User::factory()->unverified()->create(['name' => 'Pending Profile User']);

        $this->actingAs($actor)->get(route('users.show', $target))->assertOk()
            ->assertSee('Pending Profile User')
            ->assertSee(__('Pending'))
            ->assertSee(__('Never'))
            ->assertSee(__('No companies assigned'))
            ->assertSee(__('No roles assigned'));
    }

    public function test_super_admin_can_verify_an_unverified_user_from_another_company(): void
    {
        Event::fake([Verified::class]);

        $actorCompany = $this->company('Actor Company');
        $otherCompany = $this->company('Other Company');
        $actor = User::factory()->create();
        $actor->companies()->attach($actorCompany);
        $actor->assignRole(Role::create(['name' => 'Super-Admin']));
        $target = User::factory()->unverified()->create();
        $target->companies()->attach($otherCompany);

        $this->actingAs($actor)
            ->from(route('management.dashboard'))
            ->post(route('users.verify', $target))
            ->assertRedirect(route('management.dashboard'))
            ->assertSessionHas('success', __('User verified successfully.'));

        $this->assertTrue($target->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class, fn (Verified $event) => $event->user->is($target));
    }

    public function test_non_super_admin_cannot_verify_another_user(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->unverified()->create();

        $this->actingAs($actor)->post(route('users.verify', $target))->assertForbidden();

        $this->assertFalse($target->fresh()->hasVerifiedEmail());
    }

    public function test_super_admin_can_assign_super_admin_and_inherited_admin_roles(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $actor->assignRole(Role::create(['name' => 'Super-Admin']));
        Role::create(['name' => __('Admin')]);
        $company = $this->company('Managed Company');

        $this->actingAs($actor)->get(route('users.create'))->assertOk()->assertSee('Super-Admin');

        $this->post(route('users.store'), [
            'name' => 'New Super Admin',
            'email' => 'new-super-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => ['Super-Admin'],
            'company' => [$company->id],
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'new-super-admin@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('Super-Admin'));
        $this->assertTrue($user->hasRole(__('Admin')));
    }

    public function test_platform_permission_without_super_admin_role_cannot_view_or_assign_super_admin(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $actor->givePermissionTo(
            Permission::firstOrCreate(['name' => 'access-super-admin-panel']),
            Permission::firstOrCreate(['name' => 'users.create']),
            Permission::firstOrCreate(['name' => 'users.store']),
            Permission::firstOrCreate(['name' => 'users.edit']),
            Permission::firstOrCreate(['name' => 'users.update']),
        );
        Role::create(['name' => 'Super-Admin']);
        $adminRole = Role::create(['name' => __('Admin')]);
        $company = $this->company('Platform Company');
        $target = User::factory()->create();
        $target->assignRole($adminRole);
        $target->companies()->attach($company);
        $superAdminTarget = User::factory()->create();
        $superAdminTarget->assignRole('Super-Admin');
        $superAdminTarget->companies()->attach($company);

        $this->actingAs($actor)->get(route('users.create'))->assertOk()->assertDontSee('Super-Admin');
        $this->get(route('users.edit', $target))->assertOk()->assertDontSee('Super-Admin');
        $this->get(route('users.edit', $superAdminTarget))->assertForbidden();

        $this->post(route('users.store'), [
            'name' => 'Forbidden Super Admin',
            'email' => 'forbidden-super-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => ['Super-Admin'],
            'company' => [$company->id],
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'forbidden-super-admin@example.com']);

        $this->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => null,
            'password_confirmation' => null,
            'employee_id' => null,
            'role' => ['Super-Admin'],
            'company' => [$company->id],
        ])->assertSessionHasErrors('role');
        $this->assertFalse($target->fresh()->hasRole('Super-Admin'));

        $this->put(route('users.update', $superAdminTarget), [
            'name' => $superAdminTarget->name,
            'email' => $superAdminTarget->email,
            'password' => null,
            'password_confirmation' => null,
            'employee_id' => null,
            'role' => [$adminRole->name],
            'company' => [$company->id],
        ])->assertForbidden();
        $this->assertTrue($superAdminTarget->fresh()->hasRole('Super-Admin'));
    }

    private function company(string $name): Company
    {
        return Company::create([
            'name' => $name,
            'fiscal_year' => 1405,
            'currency' => 'Rial',
        ]);
    }

    public function test_verified_user_with_a_company_can_login_with_a_normalized_email(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com']);
        $company = Company::create([
            'name' => 'Login Company',
            'fiscal_year' => 1405,
            'currency' => 'Rial',
        ]);
        $user->companies()->attach($company);

        $response = $this->post(route('login'), [
            'email' => ' LOGIN@EXAMPLE.COM ',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_verified_user_without_a_company_is_redirected_to_company_creation_after_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('registered-user.company.create'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_user_is_redirected_to_verification_after_login(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('error');
        $this->assertAuthenticatedAs($user);
    }

    public function test_global_setting_can_disable_verification_for_login(): void
    {
        Config::withoutGlobalScope(FiscalYearScope::class)->create([
            'key' => 'app_email_verification',
            'value' => 'false',
            'type' => 3,
            'category' => 1,
            'company_id' => null,
        ]);
        $user = User::factory()->unverified()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('registered-user.company.create'));
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->actingAs($user)->get(route('registered-user.company.create'))->assertOk();
    }

    public function test_verification_notice_remains_available_when_verification_is_disabled(): void
    {
        config(['app.email_verification' => false]);
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
        $response->assertViewIs('auth.verify-user');
    }

    public function test_user_can_resend_verification_when_verification_is_disabled(): void
    {
        config(['app.email_verification' => false]);
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertSessionHas('success');
        Notification::assertSentTo($user, UserVerificationNotification::class);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_can_register_and_receives_a_verification_email(): void
    {
        Notification::fake();

        $response = $this->post(route('register.email'), [
            'name' => 'New User',
            'email' => ' NEW.USER@EXAMPLE.COM ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'new.user@example.com')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHas('success');
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password123', $user->password));
        Notification::assertSentTo($user, UserVerificationNotification::class, fn (UserVerificationNotification $notification, array $channels): bool => in_array('mail', $channels, true));
    }

    public function test_disabling_registration_blocks_signup_and_disables_email_verification(): void
    {
        app(GlobalConfigService::class)->update(['app_registration' => 'false']);
        config([
            'app.registration' => true,
            'app.email_verification' => true,
        ]);

        $response = $this->get(route('register'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', __('Registration is currently disabled.'));
        $this->assertFalse(config('app.registration'));
        $this->assertFalse(config('app.email_verification'));
        $this->assertDatabaseHas('configs', [
            'key' => 'app_registration',
            'value' => 'false',
            'company_id' => null,
        ]);
        $this->assertDatabaseHas('configs', [
            'key' => 'app_email_verification',
            'value' => 'false',
            'company_id' => null,
        ]);

        app(GlobalConfigService::class)->update(['app_email_verification' => 'true']);
        $this->assertDatabaseHas('configs', [
            'key' => 'app_email_verification',
            'value' => 'false',
            'company_id' => null,
        ]);

        $this->post(route('register.email'), [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
        $this->get(route('login'))->assertDontSee(route('register'), false);
    }

    public function test_registration_sends_optional_verification_email_when_verification_is_disabled(): void
    {
        config(['app.email_verification' => false]);
        Notification::fake();
        $verificationUrl = null;

        $response = $this->post(route('register.email'), [
            'name' => 'New User',
            'email' => 'new.user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'new.user@example.com')->firstOrFail();

        $response->assertRedirect(route('registered-user.company.create'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, UserVerificationNotification::class, function (UserVerificationNotification $notification, array $channels) use ($user, &$verificationUrl): bool {
            $verificationUrl = $notification->toMail($user)->actionUrl;

            return in_array('mail', $channels, true);
        });

        $this->assertNotNull($verificationUrl);
        $this->get($verificationUrl)->assertRedirect(route('registered-user.company.create'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_is_not_treated_as_verified_when_verification_is_enabled(): void
    {
        config(['app.email_verification' => true]);

        $user = User::factory()->unverified()->create();

        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_user_model_sends_verification_notification_when_enforcement_is_disabled(): void
    {
        config(['app.email_verification' => false]);
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        Notification::assertSentTo($user, UserVerificationNotification::class);
    }

    public function test_registration_rejects_duplicate_email_and_unconfirmed_password(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('register.email'), [
            'name' => 'Duplicate User',
            'email' => ' EXISTING@EXAMPLE.COM ',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
        Notification::assertNothingSent();
    }

    public function test_verified_new_user_can_create_a_company_with_default_data_and_admin_permissions(): void
    {
        $admin = Role::firstOrCreate(['name' => __('Admin')]);
        $admin->syncPermissions(Permission::firstOrCreate(['name' => 'users.index']));

        $previousCompany = Company::create([
            'name' => 'Existing Company',
            'fiscal_year' => 1404,
            'currency' => 'Rial',
        ]);
        $user = User::factory()->create();
        config(['active-company-id' => $previousCompany->id]);

        $response = $this->actingAs($user)->post(route('registered-user.company.store'), [
            'name' => 'New Company',
            'fiscal_year' => 1405,
            'currency' => 'Rial',
            'phone_number' => '09121234567',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('companies', ['name' => 'New Company']);

        $company = Company::where('name', 'New Company')->firstOrFail();

        $this->assertSame($previousCompany->id, config('active-company-id'));
        $this->assertTrue($company->users()->whereKey($user->id)->exists());
        $this->assertTrue($user->fresh()->hasRole(__('Admin')));
        $this->assertFalse($user->fresh()->hasRole('Super-Admin'));
        $this->assertTrue($user->fresh()->can('users.index'));
        $this->assertFalse($user->fresh()->can('roles.index'));
        $this->assertFalse($user->fresh()->can('permissions.index'));
        $this->assertFalse($user->fresh()->can('update-global-configs'));
        $this->assertDatabaseHas('subjects', [
            'company_id' => $company->id,
            'code' => '050003',
        ]);
        $this->assertDatabaseHas('configs', [
            'company_id' => $company->id,
            'key' => 'sales_revenue',
        ]);
        $this->assertDatabaseHas('banks', [
            'company_id' => $company->id,
            'name' => 'بانک پارسیان',
        ]);
    }

    public function test_user_can_request_a_password_reset_email_and_reset_their_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $resetToken = null;

        $response = $this->post(route('password.email'), [
            'email' => ' RESET@EXAMPLE.COM ',
        ]);

        $response->assertSessionHas('success', __(Password::RESET_LINK_SENT));
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification, array $channels) use (&$resetToken): bool {
            $resetToken = $notification->token;

            return in_array('mail', $channels, true);
        });
        $this->assertNotNull($resetToken);

        $response = $this->post(route('password.update'), [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success', __(Password::PASSWORD_RESET));
        $user->refresh();
        $this->assertTrue(Hash::check('new-password123', $user->password));
        $this->assertFalse(Password::tokenExists($user, $resetToken));
    }

    public function test_password_reset_request_rejects_an_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'missing@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    }

    public function test_invalid_password_reset_token_does_not_change_password(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame($originalPassword, $user->fresh()->password);
    }

    public function test_verification_email_contains_a_working_signed_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $verificationUrl = null;

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertSessionHas('success');
        Notification::assertSentTo($user, UserVerificationNotification::class, function (UserVerificationNotification $notification, array $channels) use ($user, &$verificationUrl): bool {
            $verificationUrl = $notification->toMail($user)->actionUrl;

            return in_array('mail', $channels, true);
        });
        $this->assertNotNull($verificationUrl);

        $response = $this->get($verificationUrl);

        $response->assertRedirect(route('registered-user.company.create'));
        $response->assertSessionHas('success');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_email_contains_a_working_six_digit_otp(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $otp = null;

        $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('success');

        Notification::assertSentTo($user, UserVerificationNotification::class, function (UserVerificationNotification $notification) use ($user, &$otp): bool {
            $otp = $notification->toMail($user)->viewData['otp'];

            return preg_match('/^\d{6}$/', $otp) === 1;
        });

        $response = $this->actingAs($user)->post(route('verification.otp'), ['otp' => $otp]);

        $response->assertRedirect(route('registered-user.company.create'));
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->email_verification_otp);
        $this->assertNull($user->email_verification_otp_expires_at);
    }

    public function test_verification_otp_uses_the_configured_expiration_time(): void
    {
        Notification::fake();
        config(['app.verification_expire' => 5]);
        $this->freezeTime(function (): void {
            $user = User::factory()->unverified()->create();

            $user->sendEmailVerificationNotification();

            $user->refresh();
            $this->assertNotNull($user->email_verification_otp);
            $this->assertSame(now()->addMinutes(5)->timestamp, $user->email_verification_otp_expires_at->timestamp);
            Notification::assertSentTo($user, UserVerificationNotification::class);

            $notification = Notification::sent($user, UserVerificationNotification::class)->first();
            $mail = $notification->toMail($user);
            $this->assertSame(5, $mail->viewData['expiresInMinutes']);

            parse_str((string) parse_url($mail->actionUrl, PHP_URL_QUERY), $query);
            $this->assertSame(now()->addMinutes(5)->timestamp, (int) $query['expires']);
        });
    }

    public function test_incorrect_unexpired_verification_otp_is_rejected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->unverified()->create([
            'email_verification_otp' => Hash::make('123456'),
            'email_verification_otp_expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->from(route('verification.notice'))->post(route('verification.otp'), ['otp' => '654321']);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors('otp');
        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertNotNull($user->email_verification_otp);
        $this->assertNotNull($user->email_verification_otp_expires_at);
    }

    public function test_verification_otp_accepts_persian_digits(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->unverified()->create([
            'email_verification_otp' => Hash::make('123456'),
            'email_verification_otp_expires_at' => now()->addDay(),
        ]);

        $this->assertSame('123456', toEnglish('۱۲۳۴۵۶'));
        $this->assertTrue(Hash::check(toEnglish('۱۲۳۴۵۶'), $user->email_verification_otp));

        $response = $this->actingAs($user)->post(route('verification.otp'), ['otp' => '۱۲۳۴۵۶']);

        $response->assertRedirect(route('registered-user.company.create'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_otp_must_contain_exactly_six_digits(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->unverified()->create([
            'email_verification_otp' => Hash::make('123456'),
            'email_verification_otp_expires_at' => now()->addDay(),
        ]);

        foreach (['', '12345', '1234567', 'abcdef'] as $otp) {
            $response = $this->actingAs($user)->from(route('verification.notice'))->post(route('verification.otp'), ['otp' => $otp]);

            $response->assertRedirect(route('verification.notice'));
            $response->assertSessionHasErrors('otp');
            $this->assertFalse($user->fresh()->hasVerifiedEmail());
        }
    }

    public function test_verification_otp_rejects_non_string_input_without_an_error(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->unverified()->create([
            'email_verification_otp' => Hash::make('123456'),
            'email_verification_otp_expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)->from(route('verification.notice'))->post(route('verification.otp'), ['otp' => ['123456']]);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors('otp');
        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_otp_is_rejected_when_no_code_has_been_issued(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->unverified()->create([
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
        ]);

        $response = $this->actingAs($user)->from(route('verification.notice'))->post(route('verification.otp'), ['otp' => '123456']);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_otp_is_rejected_when_hash_is_missing_but_expiration_is_in_the_future(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->unverified()->create([
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.otp'), ['otp' => '123456']);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_invalid_or_expired_verification_otp_is_rejected(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $user = User::factory()->unverified()->create([
            'email_verification_otp' => Hash::make('123456'),
            'email_verification_otp_expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->from(route('verification.notice'))->post(route('verification.otp'), ['otp' => '123456']);

        $response->assertRedirect(route('verification.notice'));
        $response->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_rejects_a_validly_signed_link_with_the_wrong_hash(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(5),
            [
                'id' => $user->id,
                'hash' => sha1('wrong-verification-address@example.com'),
            ],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
