<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Config;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Scopes\FiscalYearScope;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\DocumentService;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame('fa', $activity->details->get('request_input')['locale']);
        $this->assertSame('[REDACTED]', $activity->details->get('request_input')['password']);
        $this->assertStringNotContainsString('do-not-store-this', $activity->details->toJson());
    }

    public function test_large_json_upload_is_logged_as_metadata_after_its_temporary_file_is_removed(): void
    {
        $actor = User::factory()->create();
        $contents = json_encode(['payload' => str_repeat('large-json-content', 100_000)], JSON_THROW_ON_ERROR);
        $path = tempnam(sys_get_temp_dir(), 'activity-log-upload-');
        file_put_contents($path, $contents);
        $upload = new UploadedFile($path, 'large.json', 'application/json', null, true);

        Route::post('/test/activity-log/upload', function (Request $request) {
            unlink($request->file('payload')->getPathname());

            return response()->noContent();
        })->middleware('web');

        $this->actingAs($actor)->post('/test/activity-log/upload', [
            'description' => 'Keep this value',
            'payload' => $upload,
        ])->assertNoContent();

        $activity = Activity::query()->where('source', 'request')->latest('id')->firstOrFail();
        $requestInput = $activity->details->get('request_input');

        $this->assertSame('Keep this value', $requestInput['description']);
        $this->assertSame([
            'file' => 'large.json',
            'mime_type' => 'application/json',
        ], $requestInput['payload']);
        $this->assertStringNotContainsString('large-json-content', $activity->details->toJson());
    }

    public function test_nested_uploaded_files_are_logged_as_metadata(): void
    {
        $actor = User::factory()->create();

        Route::post('/test/activity-log/nested-uploads', fn () => response()->noContent())->middleware('web');

        $this->actingAs($actor)->post('/test/activity-log/nested-uploads', [
            'documents' => [
                'primary' => UploadedFile::fake()->create('primary.json', 2048, 'application/json'),
                'supporting' => [
                    UploadedFile::fake()->create('notes.txt', 1024, 'text/plain'),
                ],
            ],
        ])->assertNoContent();

        $requestInput = Activity::query()->where('source', 'request')->latest('id')->firstOrFail()->details->get('request_input');

        $this->assertCount(2, $requestInput['documents']);
        $this->assertSame([
            'file' => 'primary.json',
            'mime_type' => 'application/json',
        ], $requestInput['documents']['primary']);
        $this->assertSame([[
            'file' => 'notes.txt',
            'mime_type' => 'text/plain',
        ]], $requestInput['documents']['supporting']);
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
            'properties' => ['route' => 'test', 'models' => [['model_type' => FiscalYear::class, 'model_id' => 1]]],
        ]);

        $service = app(ActivityLogService::class);
        $result = $service->index([]);
        $listed = $result['activities']->getCollection()->first();

        $this->assertNotNull($listed);
        $this->assertFalse($listed->details->has('models'));
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
        $fiscalYear = FiscalYear::factory()->create(['year' => 1403]);
        $actor->fiscalYears()->syncWithoutDetaching([$fiscalYear->id]);
        config(['active-fiscal-year-id' => $fiscalYear->id, 'active-company-fiscal-year' => 1403]);

        $subject = Subject::withoutGlobalScopes()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'parent_id' => null,
            'name' => 'Cash',
            'code' => '001',
            'type' => 3,
        ]);
        $document = Document::withoutGlobalScopes()->create([
            'fiscal_year_id' => $fiscalYear->id,
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
        $fiscalYear = FiscalYear::factory()->create(['year' => 1403]);
        $actor->fiscalYears()->syncWithoutDetaching([$fiscalYear->id]);
        config(['active-fiscal-year-id' => $fiscalYear->id, 'active-company-fiscal-year' => 1403]);

        $source = Subject::withoutGlobalScopes()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'parent_id' => null,
            'name' => 'Source',
            'code' => '001',
            'type' => 3,
        ]);
        $destination = Subject::withoutGlobalScopes()->create([
            'fiscal_year_id' => $fiscalYear->id,
            'parent_id' => null,
            'name' => 'Destination',
            'code' => '002',
            'type' => 3,
        ]);
        $document = Document::withoutGlobalScopes()->create([
            'fiscal_year_id' => $fiscalYear->id,
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
            FiscalYear::factory()->create();

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

    public function test_initial_migration_creates_the_final_schema_and_renames_the_config_key(): void
    {
        Config::withoutGlobalScope(FiscalYearScope::class)->updateOrCreate(
            ['key' => 'app_activity_logger_enabled', 'fiscal_year_id' => null],
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
            'fiscal_year_id' => null,
        ]);
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
