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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkspaceActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_scans_do_not_transfer_large_saved_attributes_or_request_input(): void
    {
        $viewer = $this->viewer();
        $year = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$year->id]);
        $largeValue = str_repeat('Large audit snapshot ', 5000);
        foreach (range(1, 30) as $number) {
            $this->activity($viewer, [
                'fiscal_year_id' => $year->id,
                'request_input' => ['payload' => $largeValue],
                'model_types' => [FiscalYear::class],
                'models' => [[
                    'model_type' => FiscalYear::class, 'model_id' => $year->id,
                    'fiscal_year_id' => $year->id,
                    'attributes' => ['year' => 1405, 'payload' => $largeValue],
                    'old' => ['year' => 1404, 'payload' => $largeValue],
                ]],
            ], source: 'request');
        }
        $this->activity($viewer, [
            'fiscal_year_id' => $year->id,
            'attributes' => ['payload' => $largeValue],
            'old' => ['payload' => $largeValue],
        ]);

        $this->actingAs($viewer);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $data = app(ActivityLogService::class)->index([]);
            $queries = collect(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertSame(31, $data['activities']->total());
        $this->assertCount(25, $data['activities']);
        $this->assertSame(31, $data['metrics']['total']);
        $scans = $queries->filter(fn (array $query): bool => str_contains($query['query'], 'activity_log')
            && str_starts_with(strtolower($query['query']), 'select')
            && ! str_contains($query['query'], 'activity_log.*'));
        $this->assertNotEmpty($scans);
        foreach ($scans as $query) {
            $rows = DB::select($query['query'], $query['bindings']);
            $this->assertLessThan(30_000, strlen(json_encode($rows)), 'History scans must return compact metadata regardless of snapshot size.');
            $this->assertStringNotContainsString('Large audit snapshot', json_encode($rows));
        }

        $activity = Activity::query()->where('source', 'request')->latest('id')->first();
        $this->getJson(route('activity-logs.details', $activity))->assertOk()
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Large audit snapshot'));
        $this->assertSame($largeValue, $activity->details['models'][0]['attributes']['payload']);
    }

    public function test_history_loading_does_not_query_model_ownership_or_hydrate_other_pages(): void
    {
        $viewer = $this->viewer();
        $year = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$year->id]);
        config(['active-fiscal-year-id' => $year->id]);

        foreach (range(1, 520) as $number) {
            $document = Document::create([
                'number' => $number, 'date' => '2026-11-01', 'title' => 'Audit query fixture',
                'creator_id' => $viewer->id, 'fiscal_year_id' => $year->id,
            ]);
            Activity::create([
                'source' => 'model', 'user_id' => $viewer->id, 'action' => 'created',
                'description' => 'Created document', 'model_type' => Document::class,
                'model_id' => $document->id, 'details' => ['fiscal_year_id' => $year->id],
            ]);
        }

        $retrieved = 0;
        Event::listen('eloquent.retrieved: '.Activity::class, function () use (&$retrieved): void {
            $retrieved++;
        });
        $this->actingAs($viewer);
        foreach ([[], ['fiscal_year_id' => $year->id]] as $filters) {
            $retrieved = 0;
            DB::enableQueryLog();
            DB::flushQueryLog();
            try {
                $data = app(ActivityLogService::class)->index($filters);
                $queries = collect(DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }

            $this->assertSame(520, $data['activities']->total());
            $this->assertCount(25, $data['activities']);
            $this->assertSame(25, $retrieved, 'Only the displayed page should instantiate Activity models.');
            $ownershipQueries = $queries->filter(fn (array $query): bool => preg_match('/from ["`]documents["`]/i', $query['query']) === 1);
            $this->assertCount(0, $ownershipQueries, 'Activity permissions grant access to the full history without checking each affected model.');
        }
    }

    public function test_legacy_groups_keep_page_boundaries_without_hydrating_the_entire_history(): void
    {
        $viewer = $this->viewer();
        $year = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$year->id]);
        $groups = collect();
        foreach (range(1, 30) as $minute) {
            $date = now()->startOfDay()->addMinutes($minute);
            $models = collect(range(1, 2))->map(function () use ($viewer, $year, $date): Activity {
                $activity = $this->activity($viewer, ['fiscal_year_id' => $year->id, 'route' => 'fiscal-years.update']);
                $activity->forceFill(['created_at' => $date])->save();

                return $activity;
            });
            $request = $this->activity($viewer, ['fiscal_year_id' => $year->id, 'route' => 'fiscal-years.update'], source: 'request');
            $request->forceFill(['created_at' => $date])->save();
            $groups->prepend(collect([$request->id])->concat($models->pluck('id')));
        }

        $retrieved = 0;
        Event::listen('eloquent.retrieved: '.Activity::class, function () use (&$retrieved): void {
            $retrieved++;
        });
        $this->actingAs($viewer);
        foreach ([1 => 25, 2 => 5, 3 => 0] as $page => $count) {
            $retrieved = 0;
            $this->get(route('activity-logs.index', ['page' => $page]))->assertOk()
                ->assertViewHas('activities', function ($activities) use ($groups, $page, $count): bool {
                    $expected = $groups->slice(($page - 1) * 25, 25)->map(fn ($group) => $group->first())->values()->all();

                    return $activities->total() === 30 && $activities->count() === $count
                        && $activities->pluck('detailsActivityId')->all() === $expected;
                });
            $this->assertSame($count * 3, $retrieved);
        }
    }

    public function test_date_filters_include_the_whole_day_and_exclude_adjacent_days(): void
    {
        $viewer = $this->viewer();
        $year = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$year->id]);
        $activities = collect(['2026-10-31 23:59:59', '2026-11-01 00:00:00', '2026-11-01 23:59:59', '2026-11-02 00:00:00'])
            ->map(function (string $date) use ($viewer, $year): Activity {
                $activity = $this->activity($viewer, ['fiscal_year_id' => $year->id]);
                $activity->forceFill(['created_at' => $date])->save();

                return $activity;
            });

        $this->actingAs($viewer);
        $data = app(ActivityLogService::class)->index(['date_from' => '2026-11-01', 'date_to' => '2026-11-01']);
        $this->assertEqualsCanonicalizing($activities->slice(1, 2)->pluck('id')->all(), $data['activities']->pluck('id')->all());
    }

    public function test_activity_permissions_expose_all_history_metrics_and_filter_options_without_fiscal_year_assignments(): void
    {
        $viewer = $this->viewer();
        $viewer->fiscalYears()->detach();
        $years = collect([FiscalYear::factory()->create(), FiscalYear::factory()->create()]);
        $actor = User::factory()->create();
        $actor->fiscalYears()->sync([$years->last()->id]);
        $records = $years->map(fn ($year) => $this->activity($actor, ['fiscal_year_id' => $year->id]));
        $records->push($this->activity($actor, [], User::class));

        $this->actingAs($viewer)->get(route('activity-logs.index'))->assertOk()
            ->assertViewHas('activities', fn ($activities) => $activities->total() === 3)
            ->assertViewHas('metrics', fn ($metrics) => $metrics === ['total' => 3, 'today' => 3, 'model' => 3, 'request' => 0])
            ->assertViewHas('users', fn ($users) => $users->pluck('id')->all() === [$actor->id])
            ->assertViewHas('fiscalYears', fn ($options) => $options->pluck('id')->sort()->values()->all() === $years->pluck('id')->sort()->values()->all())
            ->assertViewHas('modelTypes', fn ($types) => $types->all() === [FiscalYear::class, User::class]);
        foreach ($records as $record) {
            $this->getJson(route('activity-logs.details', $record))->assertOk();
        }
        $this->assertDatabaseCount('activity_log', 3);
    }

    public function test_filters_use_recorded_context_without_checking_current_model_access(): void
    {
        $viewer = $this->viewer();
        $viewer->fiscalYears()->detach();
        $first = FiscalYear::factory()->create();
        $second = FiscalYear::factory()->create();
        $actor = User::factory()->create(['name' => 'History actor']);
        $other = User::factory()->create();
        config(['active-fiscal-year-id' => $second->id]);
        $document = Document::factory()->create(['fiscal_year_id' => $second->id]);
        $matching = Activity::create([
            'source' => 'model', 'user_id' => $actor->id, 'action' => 'updated',
            'description' => 'Legacy context match', 'model_type' => Document::class,
            'model_id' => $document->id,
            'details' => ['fiscal_year_id' => $first->id, 'model_label' => 'Legacy context reference'],
        ]);
        $this->activity($other, ['fiscal_year_id' => $second->id]);
        $this->actingAs($viewer);
        foreach ([
            ['search' => 'Legacy context'], ['search' => 'History actor'], ['user_id' => $actor->id],
            ['model_type' => Document::class], ['model_reference' => 'Legacy context reference'],
            ['fiscal_year_id' => $first->id], ['action' => 'updated', 'user_id' => $actor->id],
        ] as $filters) {
            $data = app(ActivityLogService::class)->index($filters);
            $this->assertSame([$matching->id], $data['activities']->pluck('id')->all());
            $this->assertSame(2, $data['metrics']['total']);
        }
        $this->getJson(route('activity-logs.details', $matching))->assertOk();
        $this->assertSame(2, Activity::query()->count());
    }

    public function test_mixed_company_aggregate_details_and_model_types_are_visible(): void
    {
        $viewer = $this->viewer();
        $first = FiscalYear::factory()->create();
        $second = FiscalYear::factory()->create();
        $viewer->fiscalYears()->sync([$first->id]);
        $models = collect([$first, $second])->map(fn ($year) => [
            'model_type' => FiscalYear::class, 'model_id' => $year->id,
            'fiscal_year_id' => $year->id, 'event' => 'created',
            'attributes' => ['year' => $year->year], 'old' => [],
        ])->all();
        $record = $this->activity($viewer, [
            'fiscal_year_ids' => [$first->id, $second->id],
            'models' => $models, 'model_types' => [FiscalYear::class],
            'request_input' => ['name' => 'Complete aggregate history'],
        ], source: 'request');
        $this->actingAs($viewer);
        foreach ([[], ['model_type' => FiscalYear::class], ['action' => 'created'], ['fiscal_year_id' => $second->id]] as $filters) {
            $data = app(ActivityLogService::class)->index($filters);
            $this->assertSame([$record->id], $data['activities']->pluck('id')->all());
            $this->assertSame([FiscalYear::class], $data['modelTypes']->all());
            $this->assertSame(['total' => 1, 'today' => 1, 'model' => 1, 'request' => 1], $data['metrics']);
        }
        $this->getJson(route('activity-logs.details', $record))->assertOk()
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Complete aggregate history'));
        $this->getJson(route('activity-logs.details', $record).'?model_type='.urlencode(FiscalYear::class).'&model_id='.$second->id)
            ->assertOk();
        $this->assertSame($models, $record->fresh()->details['models']);
    }

    public function test_company_and_deleted_model_history_remains_visible_without_access_to_the_affected_company(): void
    {
        $viewer = $this->viewer();
        $viewer->fiscalYears()->detach();
        $year = FiscalYear::factory()->create();
        $records = collect([
            Activity::create([
                'source' => 'model', 'user_id' => $viewer->id, 'action' => 'updated',
                'description' => 'Company history', 'model_type' => Company::class,
                'model_id' => $year->company_id, 'details' => ['attributes' => ['name' => 'Company history']],
            ]),
            Activity::create([
                'source' => 'model', 'user_id' => $viewer->id, 'action' => 'deleted',
                'description' => 'Deleted document', 'model_type' => Document::class, 'model_id' => 999999,
                'details' => ['old' => ['fiscal_year_id' => $year->id, 'title' => 'Deleted document']],
            ]),
        ]);
        $this->actingAs($viewer)->get(route('activity-logs.index'))->assertOk()
            ->assertViewHas('activities', fn ($activities) => $activities->total() === 2);
        foreach ($records as $record) {
            $this->getJson(route('activity-logs.details', $record))->assertOk();
        }
        $this->assertSame(2, Activity::query()->count());
    }

    public function test_context_only_requests_filter_by_either_year_after_access_is_removed(): void
    {
        $viewer = $this->viewer();
        $years = collect([FiscalYear::factory()->create(), FiscalYear::factory()->create()]);
        $viewer->fiscalYears()->detach();
        $record = $this->activity($viewer, ['fiscal_year_ids' => $years->pluck('id')->all()], source: 'request');
        $this->actingAs($viewer);
        foreach ($years as $year) {
            $this->get(route('activity-logs.index', ['fiscal_year_id' => $year->id]))->assertOk()
                ->assertViewHas('activities', fn ($activities) => $activities->total() === 1);
        }
        $this->getJson(route('activity-logs.details', $record))->assertOk();
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
        $this->actingAs($viewer)->withSession(['interface_mode' => 'management'])
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
            'model_id' => $details['fiscal_year_id'] ?? 1,
            'action' => $source === 'model' ? 'updated' : 'post',
            'description' => 'Historical record',
            'details' => $details,
        ]);
    }
}
