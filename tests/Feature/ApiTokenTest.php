<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_user_can_open_create_token_page(): void
    {
        $user = User::factory()->create();
        foreach (['api-tokens.*', 'api.access', 'documents.show'] as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $this->actingAs($user)
            ->get(route('api-tokens.create'))
            ->assertOk()
            ->assertSee(__('Create token'))
            ->assertSee('documents.show');
    }

    public function test_user_can_create_token_only_from_own_permissions(): void
    {
        $user = User::factory()->create();
        foreach (['api-tokens.*', 'api.access', 'documents.show', 'configs.index'] as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $this->actingAs($user)
            ->get(route('api-tokens.create'))
            ->assertOk()
            ->assertSee('documents.show')
            ->assertDontSee('configs.index');

        $this->actingAs($user)
            ->post(route('api-tokens.store'), [
                'name' => 'reader',
                'permissions' => ['documents.show'],
            ])
            ->assertRedirect(route('api-tokens.index'))
            ->assertSessionHas('plainTextToken');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'reader',
        ]);

        $this->actingAs($user)
            ->post(route('api-tokens.store'), [
                'name' => 'web-only',
                'permissions' => ['configs.index'],
            ])
            ->assertSessionHasErrors('permissions.0');

        $this->actingAs($user)
            ->post(route('api-tokens.store'), [
                'name' => 'too-powerful',
                'permissions' => ['documents.store'],
            ])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_user_can_create_token_from_api_permissions_granted_by_wildcard(): void
    {
        $user = User::factory()->create();
        foreach (['api-tokens.*', 'api.access', 'documents.*'] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $user->givePermissionTo('api-tokens.*', 'api.access', 'documents.*');

        $this->actingAs($user)
            ->get(route('api-tokens.create'))
            ->assertOk()
            ->assertSee('documents.show')
            ->assertSee('documents.store');
    }

    public function test_super_admin_can_create_token_from_available_api_permissions_without_synced_permissions(): void
    {
        $user = User::factory()->create();
        foreach (['documents.show', 'documents.store'] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        Role::firstOrCreate(['name' => 'Super-Admin']);
        $user->assignRole('Super-Admin');

        $this->actingAs($user)
            ->get(route('api-tokens.create'))
            ->assertOk()
            ->assertSee('documents.show')
            ->assertSee('documents.store');

        $this->actingAs($user)
            ->post(route('api-tokens.store'), [
                'name' => 'super-admin-token',
                'permissions' => ['documents.show'],
            ])
            ->assertRedirect(route('api-tokens.index'))
            ->assertSessionHas('plainTextToken');
    }

    public function test_financial_abilities_can_be_granted_only_with_user_permission(): void
    {
        $user = User::factory()->create();
        foreach (['api-tokens.*', 'api.access', 'reports.cost-income', 'customers.show'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->actingAs($user)->get(route('api-tokens.create'))
            ->assertOk()->assertSee('reports.cost-income')->assertSee('customers.show')
            ->assertDontSee('reports.trial-balance');
        $this->post(route('api-tokens.store'), [
            'name' => 'MCP reader', 'permissions' => ['reports.cost-income', 'customers.show'],
        ])->assertRedirect(route('api-tokens.index'));
        self::assertSame(['reports.cost-income', 'customers.show'], $user->tokens()->first()->abilities);
        $this->post(route('api-tokens.store'), [
            'name' => 'Forbidden', 'permissions' => ['reports.trial-balance'],
        ])->assertSessionHasErrors('permissions.0');
    }

    public function test_token_page_shows_the_registered_mcp_endpoint(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('api-tokens.index', 'web'));
        $this->actingAs($user)->get(route('api-tokens.index'))
            ->assertOk()
            ->assertSee(route('mcp.handle'))
            ->assertSee(__('MCP Server URL'))
            ->assertSee(__('Copy URL'));
    }
}
