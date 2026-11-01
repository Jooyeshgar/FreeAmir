<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkspaceActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_metrics_and_filter_options_only_include_accessible_fiscal_years(): void
    {
        $viewer = $this->viewer();
        $first = FiscalYear::factory()->create(['year' => 1405]);
        $second = FiscalYear::factory()->create(['year' => 1404]);
        $sameCompanyHidden = FiscalYear::factory()->create(['company_id' => $first->company_id, 'year' => 1404]);
        $otherCompanyHidden = FiscalYear::factory()->create();
        $viewer->fiscalYears()->detach();
        $viewer->fiscalYears()->attach([$first->id, $second->id]);
        $visibleActor = User::factory()->create();
        $hiddenActor = User::factory()->create();
        $visible = collect([$first, $second])->map(fn ($year) => $this->activity($visibleActor, ['fiscal_year_id' => $year->id]));
        $hidden = collect([$sameCompanyHidden, $otherCompanyHidden])->map(fn ($year) => $this->activity($hiddenActor, ['fiscal_year_id' => $year->id], User::class));
        $global = $this->activity($hiddenActor, []);

        $this->actingAs($viewer);
        config(['active-fiscal-year-id' => $first->id]);
        $data = app(ActivityLogService::class)->index([]);
        $this->assertEqualsCanonicalizing($visible->pluck('id')->all(), $data['activities']->pluck('id')->all());
        $this->assertSame(['total' => 2, 'today' => 2, 'model' => 2, 'request' => 0], $data['metrics']);
        $this->assertSame([$visibleActor->id], $data['users']->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $data['fiscalYears']->pluck('id')->all());
        $this->assertSame([FiscalYear::class], $data['modelTypes']->all());

        foreach ($hidden->push($global) as $activity) {
            $this->getJson(route('activity-logs.details', $activity))->assertNotFound();
        }
        foreach ($visible as $activity) {
            $this->getJson(route('activity-logs.details', $activity))->assertOk();
        }
        $this->get(route('activity-logs.index', ['fiscal_year_id' => $sameCompanyHidden->id]))
            ->assertOk()->assertViewHas('activities', fn ($activities) => $activities->isEmpty());
        $this->assertSame(5, Activity::query()->count());
    }

    public function test_aggregated_requests_require_access_to_every_recorded_fiscal_year(): void
    {
        $viewer = $this->viewer();
        $allowed = FiscalYear::factory()->create();
        $denied = FiscalYear::factory()->create();
        $viewer->fiscalYears()->detach();
        $viewer->fiscalYears()->attach($allowed);
        $model = ['model_type' => FiscalYear::class, 'model_id' => $allowed->id, 'event' => 'created', 'attributes' => ['year' => 1405], 'old' => [], 'fiscal_year_id' => $allowed->id];
        $visible = $this->activity($viewer, [
            'fiscal_year_ids' => [$allowed->id],
            'models' => [$model],
            'model_types' => [FiscalYear::class],
        ], source: 'request');
        $mixed = $this->activity($viewer, [
            'fiscal_year_id' => $allowed->id,
            'fiscal_year_ids' => [$allowed->id, $denied->id],
            'models' => [$model, [...$model, 'fiscal_year_id' => $denied->id]],
            'request_input' => ['name' => 'Private company data'],
        ], source: 'request');
        $foreignContext = $this->activity($viewer, [
            'fiscal_year_id' => $denied->id,
            'fiscal_year_ids' => [$allowed->id],
        ], source: 'request');
        $deletedYear = $this->activity($viewer, ['fiscal_year_ids' => [$allowed->id, 999999]], source: 'request');

        $this->actingAs($viewer)->get(route('activity-logs.index'))
            ->assertOk()->assertViewHas('activities', fn ($activities) => $activities->total() === 1);
        $this->getJson(route('activity-logs.details', $visible))->assertOk();
        foreach ([$mixed, $foreignContext, $deletedYear] as $activity) {
            $this->getJson(route('activity-logs.details', $activity, false).'?model_type='.urlencode(FiscalYear::class).'&model_id='.$allowed->id)
                ->assertNotFound();
        }
        $viewer->fiscalYears()->attach($denied);
        $this->getJson(route('activity-logs.details', $mixed))->assertOk();
    }

    public function test_no_fiscal_year_access_fails_closed_even_for_a_platform_administrator(): void
    {
        $viewer = $this->viewer();
        $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $year = FiscalYear::factory()->create();
        $viewer->fiscalYears()->detach();
        $activity = $this->activity($viewer, ['fiscal_year_id' => $year->id]);

        $this->actingAs($viewer)->get(route('activity-logs.index'))->assertOk()
            ->assertViewHas('activities', fn ($activities) => $activities->isEmpty())
            ->assertViewHas('metrics', fn ($metrics) => array_sum($metrics) === 0)
            ->assertViewHas('users', fn ($users) => $users->isEmpty())
            ->assertViewHas('fiscalYears', fn ($years) => $years->isEmpty());
        $this->getJson(route('activity-logs.details', $activity))->assertNotFound();
    }

    public function test_page_and_details_require_their_respective_permissions(): void
    {
        $viewer = $this->viewer(['activity-logs.index']);
        $year = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$year->id]);
        $activity = $this->activity($viewer, ['fiscal_year_id' => $year->id]);

        $this->actingAs($viewer)->get(route('activity-logs.index'))->assertOk()
            ->assertSee(route('activity-logs.index'), false)
            ->assertDontSee('aria-controls="activity-details-', false);
        $this->assertStringContainsString(
            route('activity-logs.index'),
            Blade::render('<x-menu />'),
        );
        $this->getJson(route('activity-logs.details', $activity))->assertForbidden();
        $viewer->syncPermissions([Permission::firstOrCreate(['name' => 'activity-logs.details'])]);
        $this->get(route('activity-logs.index'))->assertForbidden();
        $this->getJson(route('activity-logs.details', $activity))->assertOk();
        $viewer->syncPermissions([Permission::firstOrCreate(['name' => 'access-super-admin-panel'])]);
        $this->get(route('activity-logs.index'))->assertForbidden();
        $this->getJson(route('activity-logs.details', $activity))->assertForbidden();
    }

    public function test_old_platform_endpoints_are_removed_and_workspace_layout_preserves_session_mode(): void
    {
        $viewer = $this->viewer();
        $this->actingAs($viewer)->withSession(['interface_mode' => 'workspace'])
            ->get(route('activity-logs.index'))->assertOk()
            ->assertViewIs('activity-logs.index')
            ->assertSee('workspace-drawer', false)
            ->assertDontSee('management-drawer', false)
            ->assertSessionHas('interface_mode', 'workspace');
        $this->get('/management/activity-logs')->assertNotFound();
        $this->get('/management/activity-logs/1/details')->assertNotFound();
    }

    private function viewer(array $permissions = ['activity-logs.index', 'activity-logs.details']): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        return $user;
    }

    private function activity(User $actor, array $details, string $model = FiscalYear::class, string $source = 'model'): Activity
    {
        return Activity::create([
            'source' => $source,
            'user_id' => $actor->id,
            'model_type' => $source === 'model' ? $model : null,
            'model_id' => 1,
            'action' => $source === 'model' ? 'updated' : 'post',
            'description' => 'Historical record',
            'details' => $details,
        ]);
    }
}
