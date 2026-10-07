<?php

namespace Tests\Feature;

use App\Enums\ConfigTitle;
use App\Enums\SubjectType;
use App\Models\Config;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FiscalYear $fiscalYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->fiscalYear = FiscalYear::factory()->create();
        $this->fiscalYear->users()->syncWithoutDetaching([$this->user->id]);
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'configs.index']),
            Permission::firstOrCreate(['name' => 'configs.edit']),
            Permission::firstOrCreate(['name' => 'configs.update']),
        ]);

        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => (string) $this->fiscalYear->id]);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);
    }

    public function test_index_lists_supported_settings_without_unused_cash_setting(): void
    {
        $response = $this->get(route('configs.index'));

        $response->assertOk()->assertViewHas('configsTitle', function (array $titles): bool {
            $keys = array_column($titles, 'value');

            return ! in_array('CASH', $keys, true)
                && in_array('CASH_BOOK', $keys, true)
                && in_array('PAYROLL', $keys, true)
                && in_array('SALES_RETURNS', $keys, true)
                && in_array('COST_OF_GOODS_SOLD', $keys, true)
                && in_array('COGS_SERVICE', $keys, true)
                && collect($titles)->firstWhere('value', 'PAYROLL')['label'] === 'حقوق و دستمزد';
        });
    }

    public function test_edit_rejects_unused_config_key_without_creating_it(): void
    {
        $this->get(route('configs.edit', 'cash'))->assertNotFound();

        $this->assertDatabaseMissing('configs', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'key' => 'cash',
        ]);
    }

    public function test_update_rejects_unused_config_key(): void
    {
        DB::table('subjects')->insert([
            'fiscal_year_id' => $this->fiscalYear->id,
            'code' => '001',
            'name' => 'Cash subject',
            'type' => SubjectType::BOTH->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cash = Config::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'key' => 'cash',
            'value' => '0',
            'desc' => 'Cash',
            'type' => '2',
            'category' => '1',
        ]);

        $this->put(route('configs.update', $cash), [
            'code' => '001',
            'key' => 'cash',
        ])->assertSessionHasErrors('key');

        $this->assertSame('0', $cash->fresh()->value);
    }

    public function test_migration_removes_stored_cash_config_without_removing_cash_book(): void
    {
        foreach (['cash', 'cash_book'] as $key) {
            Config::create([
                'fiscal_year_id' => $this->fiscalYear->id,
                'key' => $key,
                'value' => '1',
                'desc' => $key,
                'type' => '2',
                'category' => '1',
            ]);
        }

        $migration = require database_path('migrations/2026_09_09_000002_remove_unused_cash_config.php');
        $migration->up();

        $this->assertDatabaseMissing('configs', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'key' => 'cash',
        ]);
        $this->assertDatabaseHas('configs', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'key' => 'cash_book',
        ]);
    }

    public function test_migration_renames_stored_wage_config_to_payroll(): void
    {
        Config::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'key' => 'wage',
            'value' => '42',
            'desc' => 'حقوق پرسنل',
            'type' => '3',
            'category' => '1',
        ]);

        $migration = require database_path('migrations/2026_09_12_000001_rename_wage_config_to_payroll.php');
        $migration->up();

        $this->assertDatabaseMissing('configs', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'key' => 'wage',
        ]);
        $this->assertDatabaseHas('configs', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'key' => 'payroll',
            'value' => '42',
            'desc' => 'حقوق و دستمزد',
        ]);
    }

    public function test_edit_still_accepts_every_supported_config_key(): void
    {
        foreach (ConfigTitle::cases() as $title) {
            $key = strtolower($title->value);

            $this->get(route('configs.edit', $key))->assertOk();
            $this->assertDatabaseHas('configs', [
                'fiscal_year_id' => $this->fiscalYear->id,
                'key' => $key,
            ]);
        }
    }

    public function test_edit_keeps_moved_subject_search_results_in_the_selector_scope(): void
    {
        $response = $this->get(route('configs.edit', 'payroll'));

        $response->assertOk()
            ->assertSee('Alpine.addScopeToNode(panel, {}, root)', false)
            ->assertSee('releasePanelScope()', false);
    }
}
