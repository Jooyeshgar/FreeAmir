<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Document;
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
        $visibleActor->fiscalYears()->attach([$first->id, $second->id]);
        $visible = collect([$first, $second])->map(fn ($year) => $this->activity($visibleActor, ['fiscal_year_id' => $year->id]));
        $hidden = collect([$sameCompanyHidden, $otherCompanyHidden])->map(fn ($year) => $this->activity($hiddenActor, ['fiscal_year_id' => $year->id]));
        $global = $this->activity($hiddenActor, [], User::class);

        $this->actingAs($viewer);
        config(['active-fiscal-year-id' => $first->id]);
        $data = app(ActivityLogService::class)->index([]);
        $this->assertEqualsCanonicalizing($visible->pluck('id')->all(), $data['activities']->pluck('id')->all());
        $this->assertSame(['total' => 2, 'today' => 2, 'model' => 2, 'request' => 0], $data['metrics']);
        $this->assertEqualsCanonicalizing([$viewer->id, $visibleActor->id], $data['users']->pluck('id')->all());
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

    public function test_performed_by_options_follow_selected_fiscal_year_access_even_without_activity(): void
    {
        $viewer = $this->viewer();
        $first = FiscalYear::factory()->create();
        $second = FiscalYear::factory()->create();
        $hidden = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$first->id, $second->id]);
        $firstUser = User::factory()->create();
        $firstUser->fiscalYears()->sync([$first->id]);
        $secondUser = User::factory()->create();
        $secondUser->fiscalYears()->sync([$second->id]);
        $hiddenUser = User::factory()->create();
        $hiddenUser->fiscalYears()->sync([$hidden->id]);
        $formerUser = User::factory()->create();
        $formerUser->fiscalYears()->detach();
        $this->activity($formerUser, ['fiscal_year_id' => $first->id]);

        $this->actingAs($viewer);
        $this->get(route('activity-logs.index'))->assertOk()
            ->assertViewHas('users', fn ($users) => $users->pluck('id')->sort()->values()->all() === collect([$viewer->id, $firstUser->id, $secondUser->id])->sort()->values()->all());
        foreach ([$first->id => $firstUser, $second->id => $secondUser] as $yearId => $user) {
            $this->get(route('activity-logs.index', ['fiscal_year_id' => $yearId]))->assertOk()
                ->assertViewHas('users', fn ($users) => $users->pluck('id')->sort()->values()->all() === collect([$viewer->id, $user->id])->sort()->values()->all());
        }
        $this->get(route('activity-logs.index', ['fiscal_year_id' => $hidden->id]))->assertOk()
            ->assertViewHas('users', fn ($users) => $users->isEmpty());
    }

    public function test_aggregated_requests_require_access_to_every_affected_fiscal_year(): void
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
            'models' => [$model, [...$model, 'model_id' => $denied->id, 'fiscal_year_id' => $denied->id]],
            'request_input' => ['name' => 'Private company data'],
        ], source: 'request');
        $foreignModel = $this->activity($viewer, [
            'fiscal_year_id' => $denied->id,
            'fiscal_year_ids' => [$allowed->id],
            'models' => [[...$model, 'model_id' => $denied->id]],
        ], source: 'request');
        $deletedYear = $this->activity($viewer, ['fiscal_year_ids' => [$allowed->id, 999999]], source: 'request');

        $this->actingAs($viewer)->get(route('activity-logs.index'))
            ->assertOk()->assertViewHas('activities', fn ($activities) => $activities->total() === 1);
        $this->getJson(route('activity-logs.details', $visible))->assertOk();
        foreach ([$mixed, $foreignModel, $deletedYear] as $activity) {
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

    public function test_real_company_write_and_historical_misattribution_follow_the_affected_company(): void
    {
        $a = FiscalYear::factory()->create();
        $b = FiscalYear::factory()->create();
        $editor = $this->viewer(['companies.update']);
        $editor->fiscalYears()->sync([$a->id, $b->id]);
        $viewerA = $this->viewer();
        $viewerA->fiscalYears()->sync([$a->id]);
        $viewerB = $this->viewer();
        $viewerB->fiscalYears()->sync([$b->id]);

        config(['active-fiscal-year-id' => $a->id]);
        $this->actingAs($editor)->withSession(['interface_mode' => 'workspace'])
            ->put(route('companies.update', $b->company_id), ['name' => 'Private Company B Changed'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $record = Activity::query()->where('source', 'request')->where('details->route', 'companies.update')->sole();
        $this->assertSame($b->company_id, $record->details->get('models')[0]['model_id']);
        $this->assertSame($b->company_id, $record->details->get('models')[0]['company_id']);
        $this->assertNull($record->details->get('models')[0]['fiscal_year_id']);
        $this->assertDatabaseHas('companies', ['id' => $b->company_id, 'name' => 'Private Company B Changed']);

        // Reproduce stored records produced by the old writer, without rewriting them.
        $details = $record->details->all();
        $details['fiscal_year_id'] = $a->id;
        $details['fiscal_year_ids'] = [$a->id];
        $details['models'][0]['fiscal_year_id'] = $a->id;
        unset($details['models'][0]['company_id']);
        $historical = $record->replicate();
        $historical->details = $details;
        $historical->save();
        $legacyModel = Activity::create([
            'source' => 'model', 'user_id' => $editor->id, 'action' => 'updated',
            'description' => 'Historical company update', 'model_type' => Company::class,
            'model_id' => $b->company_id,
            'details' => ['fiscal_year_id' => $a->id, 'attributes' => ['name' => 'Private Company B Changed']],
        ]);

        foreach ([$record, $historical, $legacyModel] as $activity) {
            $this->actingAs($viewerA)->getJson(route('activity-logs.details', $activity))->assertNotFound();
            $this->getJson(route('activity-logs.details', $activity).'?model_type='.urlencode(Company::class).'&model_id='.$b->company_id)
                ->assertNotFound();
            $this->actingAs($viewerB)->getJson(route('activity-logs.details', $activity))->assertOk()
                ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Private Company B Changed'));
        }
        $this->actingAs($viewerA)->get(route('activity-logs.index'))->assertOk()
            ->assertViewHas('activities', fn ($activities) => $activities->isEmpty())
            ->assertViewHas('metrics', fn ($metrics) => array_sum($metrics) === 0);
        $this->actingAs($viewerB)->get(route('activity-logs.index', ['fiscal_year_id' => $b->id]))->assertOk()
            ->assertViewHas('activities', fn ($activities) => $activities->total() === 3);
        $this->assertSame($details, $historical->fresh()->details->all());
        $this->assertSame(3, Activity::query()->count());
    }

    public function test_unverifiable_historical_models_and_context_only_requests_fail_closed(): void
    {
        $viewer = $this->viewer();
        $year = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$year->id]);
        $records = collect([
            $this->activity($viewer, ['fiscal_year_id' => $year->id], User::class),
            $this->activity($viewer, ['fiscal_year_id' => $year->id], source: 'request'),
            $this->activity($viewer, ['fiscal_year_id' => $year->id, 'models' => [[
                'model_type' => Company::class, 'model_id' => 999999, 'fiscal_year_id' => $year->id,
            ]]], source: 'request'),
            $this->activity($viewer, ['fiscal_year_id' => $year->id, 'models' => [[
                'model_type' => FiscalYear::class, 'model_id' => $year->id,
            ], ['model_type' => User::class, 'model_id' => $viewer->id]]], source: 'request'),
        ]);
        $this->actingAs($viewer)->get(route('activity-logs.index'))->assertOk()
            ->assertViewHas('activities', fn ($activities) => $activities->isEmpty());
        foreach ($records as $record) {
            $this->getJson(route('activity-logs.details', $record))->assertNotFound();
        }
        $this->assertSame(4, Activity::query()->count());
    }

    public function test_deleted_fiscal_scoped_models_remain_visible_from_their_own_snapshots(): void
    {
        $viewer = $this->viewer();
        $allowed = FiscalYear::factory()->create();
        $denied = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$allowed->id]);
        $records = collect([$allowed, $denied])->map(fn ($year) => Activity::create([
            'source' => 'model', 'user_id' => $viewer->id, 'action' => 'deleted',
            'description' => 'Deleted document', 'model_type' => Document::class, 'model_id' => 999999,
            'details' => ['fiscal_year_id' => $allowed->id, 'old' => ['fiscal_year_id' => $year->id]],
        ]));
        $this->actingAs($viewer)->getJson(route('activity-logs.details', $records[0]))->assertOk();
        $this->getJson(route('activity-logs.details', $records[1]))->assertNotFound();
    }

    public function test_fiscal_scope_changes_require_access_to_both_old_and_new_ownership(): void
    {
        $viewer = $this->viewer();
        $allowed = FiscalYear::factory()->create();
        $denied = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$allowed->id]);
        $activity = Activity::create([
            'source' => 'model', 'user_id' => $viewer->id, 'action' => 'updated',
            'description' => 'Moved document', 'model_type' => Document::class, 'model_id' => 999999,
            'details' => ['fiscal_year_id' => $allowed->id,
                'attributes' => ['fiscal_year_id' => $allowed->id],
                'old' => ['fiscal_year_id' => $denied->id],
            ],
        ]);
        $this->actingAs($viewer)->getJson(route('activity-logs.details', $activity))->assertNotFound();
        $viewer->fiscalYears()->attach($denied);
        $this->getJson(route('activity-logs.details', $activity))->assertOk();
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
            'model_id' => $details['fiscal_year_id'] ?? 1,
            'action' => $source === 'model' ? 'updated' : 'post',
            'description' => 'Historical record',
            'details' => $details,
        ]);
    }
}
