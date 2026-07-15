<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Config;
use App\Models\Scopes\FiscalYearScope;
use App\Models\User;
use App\Notifications\UserVerificationNotification;
use App\Services\GlobalConfigService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ])->mapWithKeys(fn (string $name) => [$name => Permission::create(['name' => $name])]);

        $adminRole = Role::create(['name' => 'admin']);
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
        $this->actingAs($admin)->get(route('companies.edit', $otherCompany))->assertForbidden();

        $users = $this->actingAs($admin)->get(route('users.index'));
        $users->assertOk();
        $users->assertSee('Own Company User');
        $users->assertDontSee('Other Company User');
        $this->actingAs($admin)->get(route('users.edit', $otherUser))->assertForbidden();

        $this->actingAs($admin)->get(route('users.create'))
            ->assertOk()
            ->assertDontSee('Super-Admin');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Escalated User',
            'email' => 'escalated@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => ['Super-Admin'],
            'company' => [$otherCompany->id],
        ])->assertSessionHasErrors('company');
        $this->assertDatabaseMissing('users', ['email' => 'escalated@example.com']);
    }

    public function test_role_and_permission_index_buttons_follow_action_permissions(): void
    {
        $roleIndex = Permission::create(['name' => 'roles.index']);
        $permissionIndex = Permission::create(['name' => 'permissions.index']);
        $viewerRole = Role::create(['name' => 'management-viewer']);
        $viewerRole->syncPermissions([$roleIndex, $permissionIndex]);

        $company = $this->company('Viewer Company');
        $viewer = User::factory()->create();
        $viewer->companies()->attach($company);
        $viewer->assignRole($viewerRole);
        config(['active-company-id' => $company->id]);

        $roles = $this->actingAs($viewer)->get(route('roles.index'));
        $roles->assertOk();
        $roles->assertDontSee(route('roles.create'), false);
        $roles->assertDontSee(route('roles.edit', $viewerRole), false);
        $roles->assertDontSee(route('roles.destroy', $viewerRole), false);

        $permissions = $this->actingAs($viewer)->get(route('permissions.index'));
        $permissions->assertOk();
        $permissions->assertDontSee(route('permissions.create'), false);
        $permissions->assertDontSee(route('permissions.edit', $roleIndex), false);
        $permissions->assertDontSee(route('permissions.destroy', $roleIndex), false);
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

    public function test_verified_new_user_can_create_a_company_while_database_seeder_runs_in_that_company_context(): void
    {
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
        $this->assertTrue($user->fresh()->hasRole('admin'));
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
