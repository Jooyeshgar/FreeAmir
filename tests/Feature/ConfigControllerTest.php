<?php

namespace Tests\Feature;

use App\Enums\ConfigTitle;
use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Config;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->company->users()->syncWithoutDetaching([$this->user->id]);
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'configs.index']),
            Permission::firstOrCreate(['name' => 'configs.edit']),
            Permission::firstOrCreate(['name' => 'configs.update']),
        ]);

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        config(['active-company-id' => $this->company->id]);
    }

    public function test_index_lists_supported_settings_without_unused_cash_setting(): void
    {
        $response = $this->get(route('configs.index'));

        $response->assertOk()->assertViewHas('configsTitle', function (array $titles): bool {
            $keys = array_column($titles, 'value');

            return ! in_array('CASH', $keys, true)
                && in_array('CASH_BOOK', $keys, true)
                && in_array('SALES_RETURNS', $keys, true)
                && in_array('COST_OF_GOODS_SOLD', $keys, true)
                && in_array('COGS_SERVICE', $keys, true);
        });
    }

    public function test_edit_rejects_unused_config_key_without_creating_it(): void
    {
        $this->get(route('configs.edit', 'cash'))->assertNotFound();

        $this->assertDatabaseMissing('configs', [
            'company_id' => $this->company->id,
            'key' => 'cash',
        ]);
    }

    public function test_update_rejects_unused_config_key(): void
    {
        DB::table('subjects')->insert([
            'company_id' => $this->company->id,
            'code' => '001',
            'name' => 'Cash subject',
            'type' => SubjectType::BOTH->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cash = Config::create([
            'company_id' => $this->company->id,
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
                'company_id' => $this->company->id,
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
            'company_id' => $this->company->id,
            'key' => 'cash',
        ]);
        $this->assertDatabaseHas('configs', [
            'company_id' => $this->company->id,
            'key' => 'cash_book',
        ]);
    }

    public function test_edit_still_accepts_every_supported_config_key(): void
    {
        foreach (ConfigTitle::cases() as $title) {
            $key = strtolower($title->value);

            $this->get(route('configs.edit', $key))->assertOk();
            $this->assertDatabaseHas('configs', [
                'company_id' => $this->company->id,
                'key' => $key,
            ]);
        }
    }
}
