<?php

namespace Tests\Feature\Extensions;

use App\Extensions\AdministrationContext;
use App\Models\Activity;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminPanelIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.email_verification' => false]);
    }

    private function viewer(bool $platform): User
    {
        $user = User::factory()->create();
        foreach (['roles.index', 'permissions.index', 'users.index', 'companies.index', 'fiscal-years.index', 'activity-logs.index', 'activity-logs.details', ...($platform ? ['access-super-admin-panel'] : [])] as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
        $year = FiscalYear::factory()->create();
        $user->fiscalYears()->sync([$year->id]);

        return $user;
    }

    public function test_workspace_routes_and_links_follow_the_installed_panel_capability(): void
    {
        foreach ([false, true] as $platform) {
            $user = $this->viewer($platform);
            $this->actingAs($user);
            $hasPanel = app(AdministrationContext::class)->panel() !== null;
            $html = Blade::render('<x-menu />');
            foreach (['roles.index', 'permissions.index', 'activity-logs.index'] as $name) {
                if ($hasPanel) {
                    $this->get(route($name))->assertRedirect(route('management.dashboard'));
                    $this->assertStringNotContainsString('href="'.route($name).'"', $html);
                } else {
                    $this->get(route($name))->assertOk();
                    $this->assertStringContainsString('href="'.route($name).'"', $html);
                }
            }
        }
    }

    public function test_admin_routes_are_absent_without_the_package_and_forbidden_without_capability(): void
    {
        $this->actingAs($this->viewer(false));
        foreach (['/management', '/management/admin/roles', '/management/admin/permissions', '/management/activity-logs'] as $path) {
            $response = $this->get($path);
            Route::has('management.dashboard') ? $response->assertForbidden() : $response->assertNotFound();
        }
    }

    public function test_activity_permission_still_exposes_full_host_history_without_company_membership(): void
    {
        $user = $this->viewer(false);
        $user->fiscalYears()->detach();
        $outside = FiscalYear::factory()->create();
        $user->fiscalYears()->detach();
        $this->assertFalse($user->fiscalYears()->exists());
        $activity = Activity::create(['source' => 'model', 'action' => 'updated', 'description' => 'Outside audit',
            'user_id' => $user->id, 'details' => ['fiscal_year_id' => $outside->id, 'attributes' => ['name' => 'Outside audit']]]);

        $this->actingAs($user)->get(route('activity-logs.index'))->assertOk()->assertViewHas('activities', fn ($activities) => $activities->total() === 1);
        $this->getJson(route('activity-logs.details', $activity))->assertOk();
    }

    public function test_installed_panel_reads_all_companies_years_and_history_without_assignments(): void
    {
        if (! Route::has('management.dashboard')) {
            $this->markTestSkipped('Run again after Composer installs amir/user-management.');
        }
        $user = $this->viewer(true);
        $user->fiscalYears()->detach();
        $user->syncPermissions(['access-super-admin-panel']);
        $outside = FiscalYear::factory()->create();
        $other = FiscalYear::factory()->create();
        $user->fiscalYears()->detach();
        $this->assertFalse($user->fiscalYears()->exists());
        Activity::create(['source' => 'request', 'action' => 'get', 'description' => 'Other company audit',
            'user_id' => $user->id, 'details' => ['fiscal_year_id' => $other->id]]);
        $activity = Activity::create(['source' => 'model', 'action' => 'updated', 'description' => 'Global audit',
            'user_id' => $user->id, 'details' => ['fiscal_year_id' => $outside->id, 'attributes' => ['name' => 'Global audit']]]);
        $this->actingAs($user);

        $this->get(route('management.dashboard'))->assertOk();
        $this->get(route('management.companies.index'))->assertOk()->assertSee($outside->company->name);
        $this->get(route('management.companies.show', $outside->company))->assertOk();
        $this->get(route('management.fiscal-years.index'))->assertOk()->assertSee($outside->company->name);
        $this->get(route('management.fiscal-years.show', $outside))->assertOk();
        $this->get(route('management.roles.index'))->assertOk();
        $this->get(route('management.permissions.index'))->assertOk();
        $this->get(route('management.users.index'))->assertOk();
        $this->get(route('management.settings'))->assertOk();
        $this->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertViewHas('activities', fn ($activities) => $activities->total() === 2)
            ->assertViewHas('metrics', fn ($metrics) => $metrics['total'] === 2)
            ->assertSee($outside->company->name)
            ->assertSee($other->company->name);
        $this->get(route('management.activity-logs.index', ['fiscal_year_id' => $outside->id]))
            ->assertOk()->assertViewHas('activities', fn ($activities) => $activities->total() === 1);
        $this->getJson(route('management.activity-logs.details', $activity))->assertOk();
    }

    public function test_disabling_the_panel_restores_workspace_routes_and_links(): void
    {
        config(['user-management.admin.enabled' => false]);
        $this->actingAs($this->viewer(true));
        $this->assertNull(app(AdministrationContext::class)->panel());
        $html = Blade::render('<x-menu />');
        foreach (['roles.index', 'permissions.index', 'activity-logs.index'] as $name) {
            $this->get(route($name))->assertOk();
            $this->assertStringContainsString('href="'.route($name).'"', $html);
        }
    }

    public function test_platform_login_uses_the_installed_panel_before_company_onboarding(): void
    {
        $user = $this->viewer(true);
        $user->fiscalYears()->detach();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(Route::has('management.dashboard') ? route('management.dashboard') : route('registered-user.company.create'));
    }
}
