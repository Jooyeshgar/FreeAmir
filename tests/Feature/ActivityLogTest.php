<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Config;
use App\Models\Document;
use App\Models\Scopes\FiscalYearScope;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\DocumentService;
use App\Services\SubjectService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_page_is_limited_to_super_admin_panel_users(): void
    {
        $regularUser = User::factory()->create();
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));

        $this->get(route('management.activity-logs.index'))->assertRedirect(route('login'));
        $this->actingAs($regularUser)->get(route('management.activity-logs.index'))->assertForbidden();
        $this->actingAs($superAdmin)->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertViewIs('super-admin.activity-logs.index')
            ->assertSee(__('Activity log'))
            ->assertDontSee('name="source"', false)
            ->assertSee('name="action"', false)
            ->assertSee('name="model_type"', false)
            ->assertSee('name="date_from"', false)
            ->assertSee('data-jdp', false)
            ->assertSee('placeholder="'.__('Select date').'"', false);
    }

    public function test_application_model_changes_are_recorded_with_actor_context_and_without_secrets(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['name' => 'Before']);

        $this->actingAs($actor);
        $target->update([
            'name' => 'After',
            'password' => Hash::make('new-password'),
        ]);

        $activity = Activity::query()->where('source', 'model')->where('action', 'updated')->latest('id')->firstOrFail();

        $this->assertSame($actor->id, $activity->user_id);
        $this->assertSame($actor->id, $activity->user->id);
        $this->assertTrue(Schema::hasColumn('activity_log', 'user_id'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'causer_id'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'causer_type'));
        $this->assertSame(User::class, $activity->model_type);
        $this->assertSame($target->id, $activity->model_id);
        $this->assertSame('After', $activity->details->get('attributes')['name']);
        $this->assertSame('Before', $activity->details->get('old')['name']);
        $this->assertArrayNotHasKey('password', $activity->details->get('attributes'));
        $this->assertArrayNotHasKey('password', $activity->details->get('old'));
    }

    public function test_successful_write_requests_are_recorded_with_sensitive_input_redacted(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor)->post(route('locale'), [
            'locale' => 'fa',
            'password' => 'do-not-store-this',
        ])->assertRedirect();

        $activity = Activity::query()->where('source', 'request')->latest('id')->firstOrFail();

        $this->assertSame($actor->id, $activity->user_id);
        $this->assertSame('post', $activity->action);
        $this->assertSame('locale', $activity->details->get('route'));
        $this->assertSame('POST', $activity->details->get('method'));
        $this->assertSame('[REDACTED]', $activity->details->get('request_input')['password']);
        $this->assertStringNotContainsString('do-not-store-this', $activity->details->toJson());
    }

    public function test_a_write_request_stores_all_changed_models_in_one_database_row(): void
    {
        $actor = User::factory()->create();

        Route::post('/test/activity-log/aggregate', function () {
            $first = Company::factory()->create(['name' => 'Before']);
            $first->update(['name' => 'After']);
            Company::factory()->create(['name' => 'Second']);

            return response()->noContent();
        })->middleware('web');

        Activity::query()->delete();
        $this->actingAs($actor)->post('/test/activity-log/aggregate')->assertNoContent();

        $this->assertSame(1, Activity::query()->count());
        $activity = Activity::query()->sole();
        $models = collect($activity->details->get('models'));

        $this->assertSame('request', $activity->source);
        $this->assertSame('POST', $activity->details->get('method'));
        $this->assertCount(2, $models);
        $this->assertTrue($models->every(fn (array $model): bool => $model['model_type'] === Company::class));
        $this->assertSame('created', $models->first()['event']);
        $this->assertSame('After', $models->first()['attributes']['name']);
        $this->assertArrayNotHasKey('name', $models->first()['old']);
    }

    public function test_activity_log_listing_does_not_load_request_model_snapshots(): void
    {
        $actor = User::factory()->create();
        Activity::create([
            'log_name' => 'request',
            'description' => 'POST test',
            'event' => 'post',
            'user_id' => $actor->id,
            'properties' => ['route' => 'test', 'models' => [['model_type' => Company::class, 'model_id' => 1]]],
        ]);

        $service = app(ActivityLogService::class);
        $result = $service->index([]);
        $listed = $result['activities']->getCollection()->first();

        $this->assertNotNull($listed);
        $this->assertFalse($listed->details->has('models'));
    }

    public function test_request_activity_details_are_fetched_on_demand(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $activity = Activity::create([
            'log_name' => 'request',
            'description' => 'POST test',
            'event' => 'post',
            'user_id' => $actor->id,
            'properties' => [
                'route' => 'test',
                'models' => [[
                    'model_type' => Company::class,
                    'model_id' => 1,
                    'event' => 'created',
                    'attributes' => ['name' => 'Acme'],
                    'old' => [],
                ]],
            ],
        ]);

        $response = $this->actingAs($actor)->getJson(route('management.activity-logs.details', $activity));

        $response->assertOk()->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Acme'));
    }

    public function test_model_activity_details_are_fetched_on_demand(): void
    {
        $actor = User::factory()->create();
        $actor->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $company = Company::factory()->create(['name' => 'Seeded company']);

        $activity = Activity::create([
            'log_name' => 'model',
            'description' => 'created',
            'event' => 'created',
            'user_id' => $actor->id,
            'subject_type' => Company::class,
            'subject_id' => $company->id,
            'properties' => [
                'model_label' => $company->name,
                'attributes' => ['name' => $company->name],
            ],
        ]);

        $response = $this->actingAs($actor)->getJson(route('management.activity-logs.details', $activity));

        $response->assertOk()->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Seeded company'));
    }

    public function test_a_write_request_only_keeps_attributes_that_really_changed(): void
    {
        $actor = User::factory()->create();
        $document = Document::create([
            'number' => 100,
            'documentable_id' => null,
            'documentable_type' => null,
        ]);
        Activity::query()->delete();

        Route::put('/test/activity-log/real-changes', function () use ($document) {
            $document->update([
                'number' => 100,
                'documentable_id' => null,
                'documentable_type' => null,
            ]);

            return response()->noContent();
        })->middleware('web');

        $this->actingAs($actor)->put('/test/activity-log/real-changes')->assertNoContent();

        $activity = Activity::query()->sole();
        $this->assertSame([], $activity->details->get('models'));
    }

    public function test_document_delete_request_records_the_document_and_every_transaction(): void
    {
        $actor = User::factory()->create();
        $company = Company::factory()->create(['fiscal_year' => 1403]);
        $actor->companies()->syncWithoutDetaching([$company->id]);
        config(['active-company-id' => $company->id, 'active-company-fiscal-year' => 1403]);

        $subject = Subject::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'parent_id' => null,
            'name' => 'Cash',
            'code' => '001',
            'type' => 3,
        ]);
        $document = Document::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'number' => 101,
            'date' => '2024-03-21',
            'creator_id' => $actor->id,
            'title' => 'Delete audit',
        ]);
        $transactions = collect([100, -100])->map(fn (int $value) => Transaction::create([
            'subject_id' => $subject->id,
            'document_id' => $document->id,
            'user_id' => $actor->id,
            'value' => $value,
            'desc' => 'Delete audit',
        ]));

        Route::delete('/test/activity-log/document/{document}', function (int $document) {
            DocumentService::deleteDocument($document);

            return response()->noContent();
        })->middleware('web');

        Activity::query()->delete();
        $this->actingAs($actor)->delete("/test/activity-log/document/{$document->id}")->assertNoContent();

        $models = collect(Activity::query()->sole()->details->get('models'));

        $this->assertCount(3, $models);
        $this->assertTrue($models->contains(fn (array $model): bool => $model['event'] === 'deleted'
            && $model['model_type'] === Document::class
            && $model['model_id'] === $document->id));
        $this->assertEqualsCanonicalizing(
            $transactions->pluck('id')->all(),
            $models->where('event', 'deleted')->where('model_type', Transaction::class)->pluck('model_id')->all()
        );
    }

    public function test_subject_transfer_request_records_every_updated_transaction(): void
    {
        $actor = User::factory()->create();
        $company = Company::factory()->create(['fiscal_year' => 1403]);
        $actor->companies()->syncWithoutDetaching([$company->id]);
        config(['active-company-id' => $company->id, 'active-company-fiscal-year' => 1403]);

        $source = Subject::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'parent_id' => null,
            'name' => 'Source',
            'code' => '001',
            'type' => 3,
        ]);
        $destination = Subject::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'parent_id' => null,
            'name' => 'Destination',
            'code' => '002',
            'type' => 3,
        ]);
        $document = Document::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'number' => 102,
            'date' => '2024-03-21',
            'creator_id' => $actor->id,
            'title' => 'Transfer audit',
        ]);
        $transactions = collect([200, -200])->map(fn (int $value) => Transaction::create([
            'subject_id' => $source->id,
            'document_id' => $document->id,
            'user_id' => $actor->id,
            'value' => $value,
            'desc' => 'Transfer audit',
        ]));

        Route::put('/test/activity-log/subject-transfer', function () use ($source, $destination) {
            app(SubjectService::class)->transferSubject($source, $destination);

            return response()->noContent();
        })->middleware('web');

        Activity::query()->delete();
        $this->actingAs($actor)->put('/test/activity-log/subject-transfer')->assertNoContent();

        $models = collect(Activity::query()->sole()->details->get('models'))
            ->where('event', 'updated')->where('model_type', Transaction::class);

        $this->assertEqualsCanonicalizing($transactions->pluck('id')->all(), $models->pluck('model_id')->all());
        $this->assertTrue($models->every(fn (array $model): bool => $model['old']['subject_id'] === $source->id
            && $model['attributes']['subject_id'] === $destination->id));
    }

    public function test_failed_write_request_discards_buffered_model_changes(): void
    {
        $actor = User::factory()->create();

        Route::post('/test/activity-log/failure', function () {
            Company::factory()->create();

            throw new \RuntimeException('request failed');
        })->middleware('web');

        Activity::query()->delete();
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($actor)->post('/test/activity-log/failure');
        } catch (\RuntimeException) {
            // Expected.
        }

        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_activity_log_can_be_filtered_by_action_company_and_date(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $company = Company::factory()->create(['name' => 'Audit Company']);

        activity('model')
            ->causedBy($superAdmin)
            ->performedOn($company)
            ->event('created')
            ->withProperties([
                'company_id' => $company->id,
                'model_label' => $company->name,
                'attributes' => ['name' => $company->name],
            ])
            ->log('created');
        activity('request')
            ->causedBy($superAdmin)
            ->event('post')
            ->withProperties(['route' => 'locale'])
            ->log('POST locale');

        $this->actingAs($superAdmin)
            ->get(route('management.activity-logs.index', ['action' => 'created', 'company_id' => $company->id]))
            ->assertOk()
            ->assertSee('Audit Company')
            ->assertSee(__('Company').' #'.$company->id)
            ->assertDontSee('POST locale')
            ->assertViewHas('activities', fn ($activities): bool => $activities->getCollection()->first()['changes']->contains(fn (array $change): bool => $change['field'] === 'name'))
            ->assertViewHas('modelOptions', fn ($options): bool => $options->contains(fn (array $option): bool => $option === [
                'value' => Company::class,
                'label' => __('Company'),
            ]));

        $this->get(route('management.activity-logs.index', ['action' => 'created']))
            ->assertOk()
            ->assertSee('Audit Company')
            ->assertSee('locale')
            ->assertDontSee('POST locale')
            ->assertSee('>POST</span>', false)
            ->assertSee(__('Details'))
            ->assertSee('x-show="detailsOpen"', false)
            ->assertSee(route('users.show', $superAdmin), false)
            ->assertViewHas('activities', fn ($activities): bool => collect($activities->items())->every(fn (array $activity): bool => ! array_key_exists('userEmail', $activity)))
            ->assertDontSee('HTTP POST')
            ->assertDontSee(__('Request details'));

        $this->get(route('management.activity-logs.index', ['search' => $superAdmin->email]))
            ->assertOk()
            ->assertSee('Audit Company');

        $today = jdate('Y/m/d', now()->timestamp, tr_num: 'en');

        $this->get(route('management.activity-logs.index', ['date_from' => $today, 'date_to' => $today]))
            ->assertOk()
            ->assertSee('Audit Company');
    }

    public function test_activity_log_is_paginated(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));

        foreach (range(1, 26) as $index) {
            activity('request')
                ->causedBy($superAdmin)
                ->event('post')
                ->log("POST audit.page.{$index}");
        }

        $this->actingAs($superAdmin)
            ->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertViewHas('activities', fn ($activities): bool => $activities->count() === 25 && $activities->lastPage() === 2)
            ->assertSee('page=2', false);
    }

    public function test_request_and_its_model_change_are_shown_as_one_activity(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $company = Company::factory()->create(['name' => 'Merged Company']);

        activity('model')
            ->causedBy($superAdmin)
            ->performedOn($company)
            ->event('updated')
            ->withProperties([
                'route' => 'companies.update',
                'model_label' => $company->name,
                'attributes' => ['name' => 'Merged Company'],
                'old' => ['name' => 'Old Company'],
            ])
            ->log('updated');
        activity('request')
            ->causedBy($superAdmin)
            ->event('put')
            ->withProperties([
                'route' => 'companies.update',
                'method' => 'PUT',
                'path' => "/companies/{$company->id}",
                'request_input' => ['name' => 'Merged Company'],
            ])
            ->log('PUT companies.update');

        $this->actingAs($superAdmin)
            ->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertViewHas('activities', function ($activities): bool {
                $row = $activities->getCollection()->first();

                return $activities->count() === 1
                    && $row['requestMethod'] === 'PUT'
                    && $row['changes']->count() === 1
                    && $row['requestInput'] !== null;
            })
            ->assertSee('companies.update')
            ->assertSee(__('Company').' #'.$company->id);
        $requestActivity = Activity::query()->where('source', 'request')->latest('id')->firstOrFail();
        $this->actingAs($superAdmin)->getJson(route('management.activity-logs.details', $requestActivity))->assertOk();
    }

    public function test_store_activity_keeps_request_details_and_renders_created_fields_as_changes(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $company = Company::factory()->create(['name' => 'Created Company']);

        activity('model')
            ->causedBy($superAdmin)
            ->performedOn($company)
            ->event('created')
            ->withProperties([
                'route' => 'companies.store',
                'model_label' => $company->name,
                'attributes' => ['name' => $company->name, 'fiscal_year' => 1405],
            ])
            ->log('created');
        activity('request')
            ->causedBy($superAdmin)
            ->event('post')
            ->withProperties([
                'route' => 'companies.store',
                'method' => 'POST',
                'path' => '/companies',
                'ip_address' => '127.0.0.1',
                'request_input' => ['name' => $company->name],
            ])
            ->log('POST companies.store');

        $this->actingAs($superAdmin)
            ->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertViewHas('activities', function ($activities) use ($company): bool {
                $row = $activities->getCollection()->first();

                return $activities->count() === 1
                    && $row['actionLabel'] === __('Created')
                    && $row['requestMethod'] === 'POST'
                    && $row['route'] === 'companies.store'
                    && $row['modelContextLabel'] === __('Company').' #'.$company->id
                    && $row['requestInput'] !== null
                    && $row['requestContext'] !== []
                    && $row['changes']->contains(fn (array $change): bool => $change['field'] === 'name')
                    && $row['hasDetails'];
            })
            ->assertSee('companies.store')
            ->assertSee(__('Company').' #'.$company->id)
            ->assertSee('127.0.0.1')
            ->assertSee('Created Company');
        $requestActivity = Activity::query()->where('source', 'request')->latest('id')->firstOrFail();
        $this->actingAs($superAdmin)->getJson(route('management.activity-logs.details', $requestActivity))->assertOk();
    }

    public function test_model_event_with_no_real_changes_is_not_recorded(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['name' => 'Unchanged']);

        $this->actingAs($actor);
        app(ActivityLogService::class)->recordModelEvent('updated', $target);

        $this->assertDatabaseMissing('activity_log', [
            'source' => 'model',
            'action' => 'updated',
            'model_type' => User::class,
            'model_id' => $target->id,
        ]);
    }

    public function test_numbered_models_do_not_show_the_database_id_in_the_activity_header(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $this->actingAs($superAdmin);
        $document = Document::create(['number' => 123]);

        $this->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertViewHas('activities', function ($activities) use ($document): bool {
                $row = $activities->getCollection()->first(fn (array $activity): bool => $activity['modelId'] === $document->id);

                return $row !== null && $row['modelContextLabel'] === __('Document').' #123' && $row['contextUrl'] === route('documents.show', $document);
            });
    }

    public function test_request_and_all_of_its_model_events_are_shown_as_one_activity(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $firstCompany = Company::factory()->create(['name' => 'First related model']);
        $secondCompany = Company::factory()->create(['name' => 'Second related model']);

        foreach ([$firstCompany, $secondCompany] as $company) {
            activity('model')
                ->causedBy($superAdmin)
                ->performedOn($company)
                ->event('created')
                ->withProperties([
                    'route' => 'companies.store',
                    'model_label' => $company->name,
                    'attributes' => ['name' => $company->name],
                ])
                ->log('created');
        }

        activity('request')
            ->causedBy($superAdmin)
            ->event('post')
            ->withProperties([
                'route' => 'companies.store',
                'method' => 'POST',
                'path' => '/companies',
                'request_input' => ['name' => 'Parent request'],
            ])
            ->log('POST companies.store');

        $this->actingAs($superAdmin)
            ->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertViewHas('activities', function ($activities) use ($firstCompany, $secondCompany): bool {
                $row = $activities->getCollection()->first();

                return $activities->count() === 1
                    && $row['requestMethod'] === 'POST'
                    && $row['modelContextLabels']->contains(__('Company').' #'.$firstCompany->id)
                    && $row['modelContextLabels']->contains(__('Company').' #'.$secondCompany->id)
                    && $row['hasDetails'];
            })
            ->assertSee(__('Company').' #'.$firstCompany->id)
            ->assertSee(__('Company').' #'.$secondCompany->id);
        $requestActivity = Activity::query()->where('source', 'request')->latest('id')->firstOrFail();
        $this->actingAs($superAdmin)->getJson(route('management.activity-logs.details', $requestActivity))
            ->assertOk()->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Parent request'));
    }

    public function test_merged_changes_identify_their_affected_model(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();

        foreach ([$firstCompany, $secondCompany] as $company) {
            activity('model')
                ->causedBy($superAdmin)
                ->performedOn($company)
                ->event('updated')
                ->withProperties([
                    'route' => 'companies.update',
                    'attributes' => ['name' => "After {$company->id}"],
                    'old' => ['name' => "Before {$company->id}"],
                ])
                ->log('updated');
        }

        activity('request')
            ->causedBy($superAdmin)
            ->event('put')
            ->withProperties(['route' => 'companies.update', 'method' => 'PUT'])
            ->log('PUT companies.update');

        $response = $this->actingAs($superAdmin)
            ->get(route('management.activity-logs.index'));

        $response
            ->assertOk()
            ->assertViewHas('activities', function ($activities) use ($firstCompany, $secondCompany): bool {
                $changes = $activities->getCollection()->first()['changes'];

                return $activities->count() === 1
                    && $changes->count() === 2
                    && $changes->pluck('model')->contains(__('Company').' #'.$firstCompany->id)
                    && $changes->pluck('model')->contains(__('Company').' #'.$secondCompany->id);
            })
            ->assertSee(__('Affected model'));

        $this->assertStringContainsString(__('Company').' #'.$firstCompany->id, $response->getContent());
        $this->assertStringContainsString(__('Company').' #'.$secondCompany->id, $response->getContent());
    }

    public function test_request_and_model_events_are_merged_before_pagination(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();

        foreach ([$firstCompany, $secondCompany] as $company) {
            activity('model')
                ->causedBy($superAdmin)
                ->performedOn($company)
                ->event('created')
                ->withProperties(['route' => 'companies.store'])
                ->log('created');
        }

        activity('request')
            ->causedBy($superAdmin)
            ->event('post')
            ->withProperties(['route' => 'companies.store', 'method' => 'POST'])
            ->log('POST companies.store');

        foreach (range(1, 24) as $index) {
            activity('request')
                ->causedBy($superAdmin)
                ->event('post')
                ->withProperties(['route' => "audit.page.{$index}", 'method' => 'POST'])
                ->log("POST audit.page.{$index}");
        }

        $this->actingAs($superAdmin)
            ->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertViewHas('activities', function ($activities) use ($firstCompany, $secondCompany): bool {
                $row = $activities->getCollection()->last();

                return $activities->count() === 25
                    && $activities->total() === 25
                    && $activities->lastPage() === 1
                    && $row['route'] === 'companies.store'
                    && $row['modelContextLabels']->contains(__('Company').' #'.$firstCompany->id)
                    && $row['modelContextLabels']->contains(__('Company').' #'.$secondCompany->id);
            });
    }

    public function test_unrelated_request_and_model_rows_are_not_merged(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $company = Company::factory()->create();

        activity('model')
            ->causedBy($superAdmin)
            ->performedOn($company)
            ->event('updated')
            ->withProperties(['route' => 'companies.update', 'attributes' => ['name' => 'Changed'], 'old' => ['name' => 'Before']])
            ->log('updated');
        activity('request')
            ->causedBy($superAdmin)
            ->event('put')
            ->withProperties(['route' => 'users.update', 'method' => 'PUT'])
            ->log('PUT users.update');

        $this->actingAs($superAdmin)
            ->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertViewHas('activities', fn ($activities): bool => $activities->count() === 2)
            ->assertSee('companies.update')
            ->assertSee('users.update');
    }

    public function test_activity_details_have_responsive_mobile_and_desktop_change_layouts(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $company = Company::factory()->create();

        activity('model')
            ->causedBy($superAdmin)
            ->performedOn($company)
            ->event('updated')
            ->withProperties(['attributes' => ['name' => 'After'], 'old' => ['name' => 'Before']])
            ->log('updated');

        $this->actingAs($superAdmin)
            ->get(route('management.activity-logs.index'))
            ->assertOk()
            ->assertSee('aria-controls="activity-details-', false)
            ->assertSee('aria-controls="activity-details-', false);
    }

    public function test_activity_logging_can_be_changed_from_management_settings(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo(
            Permission::firstOrCreate(['name' => 'access-super-admin-panel']),
            Permission::firstOrCreate(['name' => 'update-global-configs']),
        );

        $this->actingAs($superAdmin)->get(route('management.settings'))
            ->assertOk()
            ->assertSee(__('about.activity_logger'))
            ->assertSee(__('Record user actions and model changes across the platform.'));

        $this->put(route('update-global-configs'), ['app_activity_logger_enabled' => 'false'])
            ->assertRedirect(route('management.settings'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configs', [
            'key' => 'app_activity_logger_enabled',
            'value' => 'false',
            'company_id' => null,
        ]);
        $this->assertDatabaseMissing('configs', ['key' => 'activity_logger_enabled']);

        $this->get(route('management.settings'))->assertOk();
        $this->assertFalse(config('activitylog.enabled'));

        $this->put(route('update-global-configs'), ['app_activity_logger_enabled' => 'true'])
            ->assertRedirect(route('management.settings'))
            ->assertSessionHasNoErrors();

        $this->get(route('management.settings'))->assertOk();
        $this->assertTrue(config('activitylog.enabled'));
    }

    public function test_management_dashboard_exposes_activity_kpis_without_recent_activity_panel(): void
    {
        $superAdmin = User::factory()->create(['name' => 'Audit Administrator']);
        $superAdmin->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $company = Company::factory()->create(['name' => 'Dashboard Audit Company']);

        activity('model')
            ->causedBy($superAdmin)
            ->performedOn($company)
            ->event('updated')
            ->withProperties(['company_id' => $company->id, 'model_label' => $company->name])
            ->log('updated');
        activity('request')
            ->causedBy($superAdmin)
            ->event('put')
            ->withProperties(['route' => 'management.settings', 'method' => 'PUT'])
            ->log('PUT management.settings');

        $this->actingAs($superAdmin)->get(route('management.dashboard'))
            ->assertOk()
            ->assertSee(__('Activity records'))
            ->assertDontSee(__('Recent activity'))
            ->assertSee(route('management.activity-logs.index'), false)
            ->assertViewHas('activityMetrics', fn (array $metrics): bool => $metrics === [
                'total' => 2,
            ])
            ->assertViewMissing('recentActivities');
    }

    public function test_initial_migration_creates_the_final_schema_and_renames_the_config_key(): void
    {
        Config::withoutGlobalScope(FiscalYearScope::class)->updateOrCreate(
            ['key' => 'app_activity_logger_enabled', 'company_id' => null],
            ['value' => null, 'type' => 3, 'category' => 1, 'desc' => __('app_activity_logger_enabled')],
        );

        $this->assertTrue(Schema::hasTable('activity_log'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'user_id'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'source'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'action'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'model_type'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'model_id'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'details'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'log_name'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'event'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'subject_type'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'subject_id'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'properties'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'causer_id'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'causer_type'));
        $this->assertDatabaseMissing('configs', ['key' => 'activity_logger_enabled']);
        $this->assertDatabaseHas('configs', [
            'key' => 'app_activity_logger_enabled',
            'value' => null,
            'company_id' => null,
        ]);
    }

    public function test_impersonated_activity_is_attributed_to_the_real_user(): void
    {
        $impersonator = User::factory()->create();
        $impersonated = User::factory()->create();

        $this->actingAs($impersonator);
        $this->assertTrue($impersonator->impersonate($impersonated));

        $this->post(route('locale'), ['locale' => 'fa'])->assertRedirect();

        $requestActivity = Activity::query()
            ->where('source', 'request')
            ->where('details->route', 'locale')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($impersonator->id, $requestActivity->user_id);
        $this->assertNotSame($impersonated->id, $requestActivity->user_id);
        $this->assertSame($impersonated->id, $requestActivity->details->get('impersonated_user_id'));

        $company = Company::factory()->create();
        $modelActivity = Activity::query()
            ->where('source', 'model')
            ->where('model_type', Company::class)
            ->where('model_id', $company->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($impersonator->id, $modelActivity->user_id);
        $this->assertSame($impersonated->id, $modelActivity->details->get('impersonated_user_id'));

        $this->post(route('impersonation.leave'))->assertRedirect(route('users.index'));

        $leaveActivity = Activity::query()
            ->where('source', 'request')
            ->where('details->route', 'impersonation.leave')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($impersonator->id, $leaveActivity->user_id);
        $this->assertSame($impersonated->id, $leaveActivity->details->get('impersonated_user_id'));
    }

    public function test_impersonator_is_loaded_at_most_once_when_a_request_triggers_multiple_model_events(): void
    {
        $impersonator = User::factory()->create();
        $impersonated = User::factory()->create();

        $this->actingAs($impersonator);
        $this->assertTrue($impersonator->impersonate($impersonated));

        Route::post('/test/activity-log/multiple-model-events', function () {
            Company::factory()->count(3)->create();

            return response()->noContent();
        })->middleware('web');

        $impersonatorLookupCount = 0;
        DB::listen(function (QueryExecuted $query) use ($impersonator, &$impersonatorLookupCount): void {
            $normalizedSql = str_replace(['"', '`', '[', ']'], '', strtolower($query->sql));

            if (preg_match('/\bfrom\s+(?:\w+\.)?users\b/', $normalizedSql) === 1
                && collect($query->bindings)->contains(fn (mixed $binding): bool => (string) $binding === (string) $impersonator->id)) {
                $impersonatorLookupCount++;
            }
        });

        $this->post('/test/activity-log/multiple-model-events')->assertNoContent();

        $activity = Activity::query()->where('source', 'request')->latest('id')->firstOrFail();

        $this->assertCount(3, $activity->details->get('models'));
        $this->assertSame($impersonator->id, (int) $activity->user_id);
        $this->assertSame($impersonated->id, (int) $activity->details->get('impersonated_user_id'));
        $this->assertLessThanOrEqual(1, $impersonatorLookupCount);
    }

    public function test_actor_cache_is_reset_between_requests(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Route::get('/test/activity-log/resolved-actor', function (ActivityLogService $activityLogService) {
            return response()->json([
                'actor_id' => $activityLogService->resolveActor(request()->user())?->getKey(),
            ]);
        })->middleware('web');

        $this->actingAs($firstUser)->get('/test/activity-log/resolved-actor')->assertOk()->assertJsonPath('actor_id', $firstUser->id);
        $this->actingAs($secondUser)->get('/test/activity-log/resolved-actor')->assertOk()->assertJsonPath('actor_id', $secondUser->id);
    }
}
