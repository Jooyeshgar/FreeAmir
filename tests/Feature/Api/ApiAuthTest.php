<?php

namespace Tests\Feature\Api;

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $fiscalYear;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The throttle middleware stores counters under md5('<limiter>:<key>').
        foreach ([
            'ip:127.0.0.1',
            'email:'.sha1('api-auth@example.com'),
            'email:'.sha1('no-access@example.com'),
        ] as $key) {
            RateLimiter::clear('api-login:'.$key);
            RateLimiter::clear(md5('api-login:'.$key));
        }

        $this->fiscalYear = FiscalYear::factory()->create();
        $this->user = User::factory()->create([
            'email' => 'api-auth@example.com',
            'password' => 'secret-password',
        ]);
        $this->fiscalYear->users()->attach($this->user);

        foreach (['api.access', 'customers.index', 'invoices.store'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }
    }

    public function test_login_returns_token_with_requested_permissions(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'api-auth@example.com',
            'password' => 'secret-password',
            'permissions' => ['api.access', 'customers.index'],
        ])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'api-auth@example.com')
            ->assertJsonPath('data.abilities', ['customers.index']);

        $token = $response->json('data.token');

        $this->getJson('/api/companies/'.$this->fiscalYear->id.'/customers', [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();
    }

    public function test_login_token_abilities_are_limited_to_the_user_permissions(): void
    {
        $this->postJson('/api/login', [
            'email' => 'api-auth@example.com',
            'password' => 'secret-password',
            'permissions' => ['api.access', 'invoices.store'],
        ])
            ->assertOk()
            ->assertJsonPath('data.abilities', ['invoices.store']);
    }

    public function test_login_defaults_to_all_available_permissions(): void
    {
        $this->postJson('/api/login', [
            'email' => 'api-auth@example.com',
            'password' => 'secret-password',
        ])
            ->assertOk()
            ->assertJsonPath('data.abilities', ['customers.index', 'invoices.store']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->postJson('/api/login', [
            'email' => 'api-auth@example.com',
            'password' => 'wrong-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_rejects_user_without_api_access_permission(): void
    {
        $other = User::factory()->create([
            'email' => 'no-access@example.com',
            'password' => 'secret-password',
        ]);
        $this->fiscalYear->users()->attach($other);
        $other->givePermissionTo(Permission::firstOrCreate(['name' => 'customers.index']));

        $this->postJson('/api/login', [
            'email' => 'no-access@example.com',
            'password' => 'secret-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_rejects_requested_permissions_outside_user_permissions(): void
    {
        $this->postJson('/api/login', [
            'email' => 'api-auth@example.com',
            'password' => 'secret-password',
            'permissions' => ['invoices.approve'],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['permissions']);
    }

    public function test_login_is_rate_limited_per_email(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'api-auth@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email' => 'api-auth@example.com',
            'password' => 'secret-password',
        ])->assertStatus(429);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        foreach (['api.access', 'companies.index'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $token = $this->postJson('/api/login', [
            'email' => 'api-auth@example.com',
            'password' => 'secret-password',
            'permissions' => ['api.access', 'companies.index'],
        ])->assertOk()->json('data.token');

        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/companies', $headers)->assertOk();

        $this->postJson('/api/logout', [], $headers)->assertOk();

        // Guard instances cache their resolved user for the lifetime of the app,
        // so force re-resolution to prove the deleted token is no longer accepted.
        $this->app->make('auth')->forgetGuards();

        $this->getJson('/api/companies', $headers)->assertUnauthorized();
    }
}
