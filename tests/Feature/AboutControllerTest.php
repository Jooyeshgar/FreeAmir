<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AboutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_with_config_update_permission_can_edit_global_settings_from_about(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->givePermissionTo(Permission::create(['name' => 'configs.update']));

        $this->actingAs($user)
            ->get(route('about'))
            ->assertOk()
            ->assertSee('gc_modal_app_env.showModal()', false)
            ->assertSee('gc_modal_app_debug.showModal()', false)
            ->assertSee('gc_modal_app_locale.showModal()', false)
            ->assertSee('gc_modal_app_registration.showModal()', false)
            ->assertSee('gc_modal_app_email_verification.showModal()', false);

        $this->actingAs($user)
            ->put(route('configs.global-update'), ['app_debug' => 'false'])
            ->assertRedirect(route('about'));

        $this->assertDatabaseHas('configs', [
            'key' => 'app_debug',
            'fiscal_year_id' => null,
            'value' => 'false',
        ]);
    }

    public function test_global_setting_update_requires_config_update_permission(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get(route('about'))
            ->assertOk()->assertDontSee('gc_modal_', false);

        $this->actingAs($user)
            ->put(route('configs.global-update'), ['app_debug' => 'false'])
            ->assertForbidden();

        $this->assertDatabaseMissing('configs', [
            'key' => 'app_debug',
            'fiscal_year_id' => null,
        ]);
    }

    public function test_all_editable_settings_persist_and_can_return_to_default(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'configs.update']));
        $values = [
            'app_env' => 'production',
            'app_debug' => 'false',
            'app_locale' => 'fa',
            'app_registration' => 'true',
            'app_email_verification' => 'true',
        ];

        $this->actingAs($user)->put(route('configs.global-update'), $values)
            ->assertRedirect(route('about'))->assertSessionHas('success');

        foreach ($values as $key => $value) {
            $this->assertDatabaseHas('configs', ['key' => $key, 'value' => $value, 'fiscal_year_id' => null]);
        }

        $this->get(route('about'))->assertOk()->assertViewHas('gcSettings', function (array $settings) use ($values) {
            foreach ($values as $key => $value) {
                if ($settings[$key]['current'] !== $value) {
                    return false;
                }
            }

            return true;
        });

        $this->put(route('configs.global-update'), ['app_debug' => ''])
            ->assertRedirect(route('about'));
        $this->assertDatabaseHas('configs', ['key' => 'app_debug', 'value' => null, 'fiscal_year_id' => null]);
        $this->assertDatabaseHas('configs', ['key' => 'app_env', 'value' => 'production', 'fiscal_year_id' => null]);
    }

    public function test_invalid_values_are_rejected_without_saving_any_settings(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'configs.update']));

        $this->actingAs($user)->from(route('about'))->put(route('configs.global-update'), [
            'app_env' => 'invalid',
            'app_debug' => 'invalid',
            'app_locale' => 'invalid',
            'app_registration' => 'invalid',
            'app_email_verification' => 'invalid',
        ])->assertSessionHasErrors(['app_env', 'app_debug', 'app_locale', 'app_registration', 'app_email_verification']);

        $this->assertDatabaseCount('configs', 0);
    }

    public function test_disabling_registration_also_disables_email_verification(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'configs.update']));

        $this->actingAs($user)->put(route('configs.global-update'), [
            'app_registration' => 'false',
            'app_email_verification' => 'true',
        ])->assertRedirect(route('about'));

        $this->assertDatabaseHas('configs', ['key' => 'app_registration', 'value' => 'false', 'fiscal_year_id' => null]);
        $this->assertDatabaseHas('configs', ['key' => 'app_email_verification', 'value' => 'false', 'fiscal_year_id' => null]);
    }
}
