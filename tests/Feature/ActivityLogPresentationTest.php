<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WarehouseProductStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityLogPresentationTest extends TestCase
{
    use RefreshDatabase;

    private string $routePrefix = 'activity-logs';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
        $user = User::factory()->create();
        $year = FiscalYear::factory()->create();
        $user->fiscalYears()->attach($year);
        config(['active-fiscal-year-id' => $year->id]);
        foreach (['activity-logs.index', 'activity-logs.details'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($user);
        Activity::query()->delete();
    }

    public function test_model_labels_are_translated_in_rows_and_filter_options(): void
    {
        $labels = [FiscalYear::class => 'سال مالی', Transaction::class => 'تراکنش', WarehouseProductStock::class => 'موجودی محصول انبار'];
        foreach ($labels as $type => $label) {
            $this->record($type, 999999, ['attributes' => ['audit_label' => 'Translation fixture']]);
        }
        $this->withSession(['locale' => 'fa'])->get(route($this->routePrefix.'.index'))->assertOk()
            ->assertViewHas('modelOptions', fn ($options): bool => $options->pluck('label')->sort()->values()->all() === collect($labels)->values()->sort()->values()->all())
            ->assertSee('سال مالی')->assertSee('تراکنش')->assertSee('موجودی محصول انبار');
    }

    public function test_existing_models_are_linked_and_missing_models_are_plain_text_in_standalone_and_grouped_logs(): void
    {
        $existing = User::factory()->create();
        $live = $this->record(User::class, $existing->id, ['attributes' => ['audit_name' => 'After'], 'old' => ['audit_name' => 'Before']]);
        $missing = $this->record(User::class, 999999, ['old' => ['audit_name' => 'Deleted']]);
        $aggregate = $this->record(null, null, ['models' => [
            ['model_type' => User::class, 'model_id' => $existing->id, 'event' => 'updated', 'attributes' => ['audit_name' => 'After'], 'old' => ['audit_name' => 'Before']],
            ['model_type' => User::class, 'model_id' => 999999, 'event' => 'deleted', 'old' => ['audit_name' => 'Deleted']],
        ]]);

        $this->get(route($this->routePrefix.'.index'))->assertOk()->assertViewHas('activities', function ($activities) use ($live, $missing): bool {
            $rows = $activities->getCollection()->keyBy('id');
            self::assertNotNull($rows[$live->id]['contextUrl']);
            self::assertNull($rows[$missing->id]['contextUrl']);

            return true;
        });
        foreach ([$live, $missing, $aggregate] as $record) {
            $html = $this->getJson(route($this->routePrefix.'.details', $record->id))->assertOk()->json('html');
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            self::assertSame(0, $xpath->query('//a[contains(@href, "999999")]')->length);
            self::assertSame(0, $xpath->query('//span[contains(@class, "text-sky-")]')->length);
            self::assertSame($record->id === $missing->id ? 0 : 1, $xpath->query('//header/a')->length);
        }
    }

    public function test_legacy_and_grouped_details_hide_format_only_changes_but_keep_real_changes(): void
    {
        $details = [
            'old' => ['audit_format_only' => '10.00', 'audit_real_change' => '10.00', 'audit_null_change' => null, 'audit_precise_change' => '9007199254740992.00'],
            'attributes' => ['audit_format_only' => 10, 'audit_real_change' => '10.01', 'audit_null_change' => 0, 'audit_precise_change' => '9007199254740993.00'],
        ];
        $legacy = $this->record(User::class, auth()->id(), $details);
        $aggregate = $this->record(null, null, ['models' => [[
            'model_type' => User::class, 'model_id' => auth()->id(), 'event' => 'updated', ...$details,
        ]]]);
        foreach ([$legacy, $aggregate] as $record) {
            $html = $this->getJson(route($this->routePrefix.'.details', $record->id))->assertOk()->json('html');
            self::assertStringNotContainsString('audit_format_only', $html);
            foreach (['audit_real_change', 'audit_null_change', 'audit_precise_change'] as $field) {
                self::assertStringContainsString($field, $html);
            }
        }
    }

    private function record(?string $type, ?int $id, array $details): Activity
    {
        return Activity::create([
            'source' => $type ? 'model' : 'request', 'user_id' => auth()->id(),
            'action' => $type ? 'updated' : 'put', 'description' => 'Activity presentation regression',
            'model_type' => $type, 'model_id' => $id, 'details' => $details,
        ]);
    }
}
