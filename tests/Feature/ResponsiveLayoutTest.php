<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ResponsiveLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_workspace_layout_has_mobile_navigation_and_a_fluid_content_container(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'home']),
            Permission::firstOrCreate(['name' => 'customers.index']),
        );

        $company = Company::create([
            'name' => 'Responsive Company',
            'fiscal_year' => (int) toEnglish(jdate('Y')),
            'currency' => 'Rial',
        ]);
        $user->companies()->attach($company);

        $response = $this->actingAs($user)
            ->withCookie('active-company-id', (string) $company->id)
            ->get(route('home'));

        $response->assertOk()
            ->assertSee('data-mobile-menu', false)
            ->assertSee('mobile-main-menu', false)
            ->assertSee('max-w-[1430px] px-3 sm:mt-5 sm:px-4 lg:px-5', false);
    }

    public function test_management_layout_has_mobile_navigation_and_a_fluid_content_container(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));

        $this->actingAs($user)
            ->get(route('management.dashboard'))
            ->assertOk()
            ->assertSee('data-mobile-menu', false)
            ->assertSee('max-w-[1430px] px-3 sm:mt-5 sm:px-4 lg:px-5', false);
    }
}
