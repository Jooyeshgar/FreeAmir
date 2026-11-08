<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Config;
use App\Models\FiscalYear;
use App\Models\Scopes\FiscalYearScope;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\UserVerificationNotification;
use App\Services\GlobalConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
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
            'active-fiscal-year-id' => null,
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

        $ownFiscalYear = $this->fiscalYear(1405, 'Admin Company');
        $otherFiscalYear = $this->fiscalYear(1403, 'Other Company');
        $admin = User::factory()->create();
        $admin->fiscalYears()->attach($ownFiscalYear);
        $admin->assignRole($adminRole);
        config(['active-fiscal-year-id' => $ownFiscalYear->id]);

        $ownUser = User::factory()->create(['name' => 'Own Company User']);
        $ownUser->fiscalYears()->attach($ownFiscalYear);
        $otherUser = User::factory()->create(['name' => 'Other Company User']);
        $otherUser->fiscalYears()->attach($otherFiscalYear);

        $this->actingAs($admin)->get(url('/management/roles'))->assertNotFound();
        $this->actingAs($admin)->get(url('/management/permissions'))->assertNotFound();
        $this->actingAs($admin)->put(url('/management/settings'), [
            'app_debug' => 'true',
        ])->assertNotFound();

        $about = $this->actingAs($admin)->get(route('about'));
        $about->assertOk();
        $about->assertDontSee(url('/management/settings'), false);
        $about->assertDontSee(url('/management/roles'), false);
        $about->assertDontSee(url('/management/permissions'), false);

        $companies = $this->actingAs($admin)->get(route('companies.index'));
        $companies->assertOk();
        $companies->assertSee('Admin Company');
        $companies->assertDontSee('Other Company');
        $companies->assertDontSee(__('Companies and fiscal years'));
        $this->actingAs($admin)->get(route('companies.edit', $otherFiscalYear->company))->assertForbidden();

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
            'fiscal_year' => [$otherFiscalYear->id],
        ])->assertSessionHasErrors(['role', 'fiscal_year']);
        $this->assertDatabaseMissing('users', ['email' => 'escalated@example.com']);
    }

    public function test_role_and_permission_pages_are_available_to_authorized_workspace_users(): void
    {
        $roleIndex = Permission::firstOrCreate(['name' => 'roles.index']);
        $permissionIndex = Permission::firstOrCreate(['name' => 'permissions.index']);
        $viewerRole = Role::create(['name' => 'management-viewer']);
        $viewerRole->syncPermissions([$roleIndex, $permissionIndex]);

        $fiscalYear = $this->fiscalYear(1405);
        $viewer = User::factory()->create();
        $viewer->fiscalYears()->attach($fiscalYear);
        $viewer->assignRole($viewerRole);
        config(['active-fiscal-year-id' => $fiscalYear->id]);

        $workspace = $this->actingAs($viewer)->get(route('about'));
        $workspace->assertOk();
        $workspace->assertSee(route('roles.index'), false);
        $workspace->assertSee(route('permissions.index'), false);
        $this->get(route('roles.index'))->assertOk();
        $this->get(route('permissions.index'))->assertOk();

        $this->get(url('/management/roles'))->assertNotFound();
        $this->get(url('/management/permissions'))->assertNotFound();
    }

    public function test_super_admin_can_assign_super_admin_and_inherited_admin_roles(): void
    {
        Notification::fake();

        $actor = User::factory()->create();
        $actor->assignRole(Role::create(['name' => 'Super-Admin']));
        Role::create(['name' => __('Admin')]);
        $fiscalYear = $this->fiscalYear(1405);
        $actor->fiscalYears()->attach($fiscalYear);

        $this->actingAs($actor)->get(route('users.create'))->assertOk()->assertSee('Super-Admin');

        $this->post(route('users.store'), [
            'name' => 'New Super Admin',
            'email' => 'new-super-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => ['Super-Admin'],
            'fiscal_year' => [$fiscalYear->id],
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
        $fiscalYear = $this->fiscalYear(1405);
        $actor->fiscalYears()->attach($fiscalYear);
        config(['active-fiscal-year-id' => $fiscalYear->id]);
        $target = User::factory()->create();
        $target->assignRole($adminRole);
        $target->fiscalYears()->attach($fiscalYear);
        $superAdminTarget = User::factory()->create();
        $superAdminTarget->assignRole('Super-Admin');
        $superAdminTarget->fiscalYears()->attach($fiscalYear);

        $this->actingAs($actor)->get(route('users.create'))->assertOk()->assertDontSee('Super-Admin');
        $this->get(route('users.edit', $target))->assertOk()->assertDontSee('Super-Admin');
        $this->get(route('users.edit', $superAdminTarget))->assertForbidden();

        $this->post(route('users.store'), [
            'name' => 'Forbidden Super Admin',
            'email' => 'forbidden-super-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => ['Super-Admin'],
            'fiscal_year' => [$fiscalYear->id],
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'forbidden-super-admin@example.com']);

        $this->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => null,
            'password_confirmation' => null,
            'employee_id' => null,
            'role' => ['Super-Admin'],
            'fiscal_year' => [$fiscalYear->id],
        ])->assertSessionHasErrors('role');
        $this->assertFalse($target->fresh()->hasRole('Super-Admin'));

        $this->put(route('users.update', $superAdminTarget), [
            'name' => $superAdminTarget->name,
            'email' => $superAdminTarget->email,
            'password' => null,
            'password_confirmation' => null,
            'employee_id' => null,
            'role' => [$adminRole->name],
            'fiscal_year' => [$fiscalYear->id],
        ])->assertForbidden();
        $this->assertTrue($superAdminTarget->fresh()->hasRole('Super-Admin'));
    }

    private function fiscalYear(int $year, ?string $name = null): FiscalYear
    {
        $company = Company::factory()->create(['name' => $name ?? "Company {$year}"]);

        return FiscalYear::create(['year' => $year, 'company_id' => $company->id]);
    }

    public function test_verified_user_with_a_company_can_login_with_a_normalized_email(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com']);
        $fiscalYear = $this->fiscalYear(1405);
        $user->fiscalYears()->attach($fiscalYear);

        $response = $this->post(route('login'), [
            'email' => ' LOGIN@EXAMPLE.COM ',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_form_displays_a_remember_me_checkbox(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('type="email"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee('type="password"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertSee('name="remember"', false)
            ->assertSee('id="remember"', false)
            ->assertSee('type="hidden" name="remember" value="0"', false)
            ->assertSee('flex-col gap-2 sm:flex-row', false)
            ->assertSee('btn btn-sm w-full', false)
            ->assertSee('sm:w-auto sm:shrink-0', false)
            ->assertSee('sm:min-w-0 sm:flex-1', false)
            ->assertSee('md:btn-md', false)
            ->assertSee(__('Remember Me'));
    }

    public function test_user_can_be_remembered_after_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

        $response->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->getRememberToken());
    }

    public function test_user_is_not_remembered_when_the_option_is_not_selected(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '0',
        ]);

        $response->assertCookieMissing(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
    }

    public function test_remember_cookie_restores_authentication_without_the_original_session(): void
    {
        $user = User::factory()->create();
        $fiscalYear = $this->fiscalYear(1405);
        $user->fiscalYears()->attach($fiscalYear);

        $loginResponse = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

        $guard = Auth::guard();
        $recallerName = $guard->getRecallerName();
        $recallerValue = $loginResponse->getCookie($recallerName)->getValue();

        $this->flushSession();
        Auth::forgetGuards();

        $user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'home']),
            Permission::firstOrCreate(['name' => 'customers.index']),
        );
        $this->withCookie($recallerName, $recallerValue)->get(route('home'))->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::guard()->viaRemember());
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
            'fiscal_year_id' => null,
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
            'fiscal_year_id' => null,
        ]);
        $this->assertDatabaseHas('configs', [
            'key' => 'app_email_verification',
            'value' => 'false',
            'fiscal_year_id' => null,
        ]);

        app(GlobalConfigService::class)->update(['app_email_verification' => 'true']);
        $this->assertDatabaseHas('configs', [
            'key' => 'app_email_verification',
            'value' => 'false',
            'fiscal_year_id' => null,
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

        $previousFiscalYear = $this->fiscalYear(1404);
        $user = User::factory()->create();
        config(['active-fiscal-year-id' => $previousFiscalYear->id]);

        $response = $this->actingAs($user)->post(route('registered-user.company.store'), [
            'name' => 'New Company',
            'fiscal_year' => 1405,
            'currency' => 'Rial',
            'phone_number' => '09121234567',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('companies', ['name' => 'New Company']);

        $fiscalYear = FiscalYear::where('year', 1405)->firstOrFail();

        $this->assertSame($previousFiscalYear->id, config('active-fiscal-year-id'));
        $this->assertTrue($fiscalYear->users()->whereKey($user->id)->exists());
        $this->assertTrue($user->fresh()->hasRole(__('Admin')));
        $this->assertFalse($user->fresh()->hasRole('Super-Admin'));
        $this->assertTrue($user->fresh()->can('users.index'));
        $this->assertFalse($user->fresh()->can('roles.index'));
        $this->assertFalse($user->fresh()->can('permissions.index'));
        $this->assertFalse($user->fresh()->can('update-global-configs'));
        $this->assertDatabaseHas('subjects', [
            'fiscal_year_id' => $fiscalYear->id,
            'code' => '050003',
        ]);
        $this->assertDatabaseHas('configs', [
            'fiscal_year_id' => $fiscalYear->id,
            'key' => 'sales_revenue',
        ]);
        $this->assertDatabaseHas('banks', [
            'fiscal_year_id' => $fiscalYear->id,
            'name' => 'بانک پارسیان',
        ]);
    }

    public function test_company_registration_bootstraps_missing_admin_permissions(): void
    {
        $this->fiscalYear(1404);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('registered-user.company.store'), [
            'name' => 'Registered Company',
            'fiscal_year' => 1405,
            'currency' => 'Rial',
            'phone_number' => '09121234567',
        ]);

        $response->assertRedirect(route('home'));

        $fiscalYear = FiscalYear::where('year', 1405)->firstOrFail();

        $this->assertTrue($user->fresh()->can('home'));
        $this->assertTrue($user->fresh()->can('documents.show'));
        $this->withCookie('active-fiscal-year-id', (string) $fiscalYear->id)->get(route('home'))->assertOk();
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
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification, array $channels) use (&$resetToken): bool {
            $resetToken = $notification->token;

            return in_array('mail', $channels, true);
        });
        $this->assertNotNull($resetToken);

        $notification = Notification::sent($user, ResetPasswordNotification::class)->first();
        $mail = $notification->toMail($user);
        $this->assertSame('auth.reset-password-email', $mail->view);
        $this->assertStringContainsString($resetToken, $mail->viewData['actionUrl']);
        $this->assertStringContainsString(urlencode($user->email), $mail->viewData['actionUrl']);
        $this->assertStringContainsString(__('Reset Password'), $mail->render());

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
