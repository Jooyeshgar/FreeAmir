<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_token_only_from_own_permissions(): void
    {
        $user = User::factory()->create();
        foreach (['api-tokens.*', 'api.access', 'documents.show'] as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

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
                'name' => 'too-powerful',
                'permissions' => ['documents.store'],
            ])
            ->assertSessionHasErrors('permissions.0');
    }
}
