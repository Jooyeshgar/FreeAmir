<?php

namespace Tests\Feature;

use Amir\UserManagement\Services\AdminDashboardMetrics;
use Amir\UserManagement\Services\Impersonation;
use App\Extensions\Contracts\AdminPanel;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminPanelImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_workspace_admin_link_is_available_on_desktop_and_mobile_only_when_authorized(): void
    {
        if (! class_exists(Impersonation::class)) {
            $this->markTestSkipped('Install amir/user-management to test the extension contract.');
        }
        $user = User::factory()->create();
        foreach (['home', 'products.index', 'access-super-admin-panel'] as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $year = FiscalYear::factory()->create();
        $user->fiscalYears()->sync([$year->id]);
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => $year->id]);
        app()->setLocale('fa');
        $response = $this->get('/')->assertOk()->assertSee('پنل مدیریت');
        self::assertSame(2, substr_count($response->getContent(), 'data-testid="admin-panel-link"'));
        $response->assertSee('href="'.route('management.dashboard').'"', false);

        $user->revokePermissionTo('access-super-admin-panel');
        $this->get('/')->assertOk()->assertDontSee('data-testid="admin-panel-link"', false);
        $user->givePermissionTo('access-super-admin-panel');
        config(['user-management.admin.enabled' => false]);
        $this->get('/')->assertOk()->assertDontSee('data-testid="admin-panel-link"', false);
        config(['user-management.admin.enabled' => true]);
        $this->app->offsetUnset(AdminPanel::class);
        $this->get('/')->assertOk()->assertDontSee('data-testid="admin-panel-link"', false);
    }

    public function test_dashboard_chart_dates_follow_the_host_locale(): void
    {
        if (! class_exists(Impersonation::class)) {
            $this->markTestSkipped('Install amir/user-management to test the extension contract.');
        }
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00'));
        foreach (['fa', 'en'] as $locale) {
            app()->setLocale($locale);
            $data = app(AdminDashboardMetrics::class)->data();
            self::assertSame(formatDate(now()), $data['viewModel']['growthLabels'][29]);
            self::assertSame(formatDate(now()), $data['viewModel']['activityLabels'][6]);
            self::assertCount(30, $data['viewModel']['growthTabs']['registrations']['datasets'][0]['data']);
        }
        $this->travelBack();
    }

    public function test_admin_impersonates_into_latest_assigned_year_and_can_return(): void
    {
        if (! class_exists(Impersonation::class)) {
            $this->markTestSkipped('Install amir/user-management to test the extension contract.');
        }
        $actor = User::factory()->create();
        $actor->givePermissionTo(Permission::findOrCreate('access-super-admin-panel', 'web'));
        $target = User::factory()->create();
        foreach (['home', 'products.index'] as $name) {
            $target->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $company = Company::factory()->create();
        $latest = FiscalYear::create(['company_id' => $company->id, 'year' => 1405]);
        $older = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        $unassigned = FiscalYear::create(['company_id' => $company->id, 'year' => 1406]);
        $target->fiscalYears()->attach([$latest->id, $older->id]);
        $actor->fiscalYears()->attach($unassigned->id);
        $this->actingAs($actor)->withCookies(['active-fiscal-year-id' => $unassigned->id]);
        $this->post(route('management.users.impersonate', $target))
            ->assertRedirect('/')
            ->assertSessionHas(Impersonation::SESSION_KEY.'.actor', $actor->id)
            ->assertCookie('active-fiscal-year-id', $latest->id);
        $this->app->forgetScopedInstances();
        $this->get('/')->assertOk()->assertSee(__('Return to administrator'));
        $this->app->forgetScopedInstances();
        $this->post(route('user-management.impersonation.leave'))
            ->assertRedirect(route('management.users.index'))
            ->assertSessionMissing(Impersonation::SESSION_KEY)
            ->assertCookie('active-fiscal-year-id', $unassigned->id);
        $this->app->forgetScopedInstances();
        $this->get(route('management.dashboard'))->assertOk();
    }
}
