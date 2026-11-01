<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Config;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FiscalYearDemoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FiscalYear $year;

    private FiscalYear $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $company = Company::factory()->create();
        $this->year = FiscalYear::create(['company_id' => $company->id, 'year' => 1405]);
        $this->other = FiscalYear::create(['company_id' => $company->id, 'year' => 1404]);
        $this->user->fiscalYears()->attach($this->year);
        config(['active-fiscal-year-id' => $this->year->id, 'app.debug' => true, 'app.env' => 'local']);
        $this->actingAs($this->user)->withCookie('active-fiscal-year-id', (string) $this->year->id);
    }

    private function allow(string ...$permissions): void
    {
        foreach ($permissions as $name) {
            $this->user->givePermissionTo(Permission::findOrCreate($name));
        }
    }

    public function test_refresh_can_load_real_demo_data_without_changing_another_year(): void
    {
        $this->allow('reports.company-overview.refresh-database');
        $otherDocument = Document::factory()->create(['fiscal_year_id' => $this->other->id]);

        $this->post(route('reports.company-overview.refresh-database'))->assertSessionHas('success');

        $this->assertGreaterThan(0, DB::table('invoices')->where('fiscal_year_id', $this->year->id)->count());
        $this->assertDatabaseHas('documents', ['id' => $otherDocument->id, 'fiscal_year_id' => $this->other->id]);
        $this->assertSame(1, DB::table('documents')->where('fiscal_year_id', $this->other->id)->count());
        $this->assertSame(1, User::count());
    }

    public function test_refresh_rebuilds_only_active_year_and_preserves_accounts_assignments_and_global_settings(): void
    {
        $this->allow('reports.company-overview.refresh-database');
        $role = Role::findOrCreate('Workspace admin');
        $this->user->assignRole($role);
        $this->user->fiscalYears()->attach($this->other);
        $oldDocument = Document::factory()->create(['fiscal_year_id' => $this->year->id]);
        $this->year->update(['pl_document_id' => $oldDocument->id, 'closing_recalculation_step' => 2]);
        $otherDocument = Document::factory()->create(['fiscal_year_id' => $this->other->id]);
        Config::withoutGlobalScopes()->create(['fiscal_year_id' => null, 'key' => 'app_debug', 'value' => 'true', 'type' => 3, 'category' => 1]);
        $otherConfig = Config::withoutGlobalScopes()->create(['fiscal_year_id' => $this->other->id, 'key' => 'cash_book', 'value' => '999', 'type' => 3, 'category' => 1]);
        $globalBefore = DB::table('configs')->whereNull('fiscal_year_id')->get()->toArray();
        $assignmentsBefore = DB::table('fiscal_year_user')->orderBy('id')->get()->toArray();
        $this->mock(DemoSeeder::class)->shouldReceive('run')->once()->with($this->year->id)->andReturnUsing(function (int $id): void {
            Document::factory()->create(['fiscal_year_id' => $id, 'title' => 'Fresh demo document']);
        });

        $this->post(route('reports.company-overview.refresh-database'))->assertRedirect(route('home'))->assertSessionHas('success');
        $this->assertDatabaseMissing('documents', ['id' => $oldDocument->id]);
        $this->assertDatabaseHas('documents', ['id' => $otherDocument->id, 'fiscal_year_id' => $this->other->id]);
        $this->assertDatabaseHas('documents', ['fiscal_year_id' => $this->year->id, 'title' => 'Fresh demo document']);
        $this->assertDatabaseHas('configs', ['id' => $otherConfig->id, 'value' => '999']);
        $this->assertEquals($globalBefore, DB::table('configs')->whereNull('fiscal_year_id')->get()->toArray());
        $this->assertEquals($assignmentsBefore, DB::table('fiscal_year_user')->orderBy('id')->get()->toArray());
        $this->assertTrue($this->user->fresh()->hasRole($role));
        $this->assertDatabaseHas('fiscal_years', ['id' => $this->year->id, 'company_id' => $this->year->company_id, 'year' => 1405]);
        $this->assertDatabaseHas('subjects', ['fiscal_year_id' => $this->year->id, 'code' => '010']);
        $this->assertNull($this->year->fresh()->pl_document_id);
        $this->assertNull($this->year->fresh()->closing_recalculation_step);
    }

    public function test_failed_refresh_rolls_back_the_original_year_data_and_assignments(): void
    {
        $this->allow('reports.company-overview.refresh-database');
        $document = Document::factory()->create(['fiscal_year_id' => $this->year->id]);
        $this->mock(DemoSeeder::class)->shouldReceive('run')->once()->andThrow(new \RuntimeException('Demo failure'));
        $this->post(route('reports.company-overview.refresh-database'))->assertSessionHas('error');
        $this->assertDatabaseHas('documents', ['id' => $document->id]);
        $this->assertTrue($this->user->fresh()->fiscalYears()->whereKey($this->year->id)->exists());
    }

    public function test_seed_demo_data_passes_only_the_active_year_to_the_seeder(): void
    {
        $this->allow('reports.company-overview.seed-demo-data');
        $this->mock(DemoSeeder::class)->shouldReceive('run')->once()->with($this->year->id);
        $this->post(route('reports.company-overview.seed-demo-data'))->assertSessionHas('success');
    }

    public static function actions(): array
    {
        return [['seed-demo-data'], ['refresh-database']];
    }

    #[DataProvider('actions')]
    public function test_actions_require_their_own_permission(string $action): void
    {
        $this->mock(DemoSeeder::class)->shouldNotReceive('run');
        $this->post(route('reports.company-overview.'.$action))->assertForbidden();
    }

    #[DataProvider('actions')]
    public function test_actions_are_blocked_for_a_closed_year(string $action): void
    {
        $this->allow('reports.company-overview.'.$action);
        $this->year->update(['closed_at' => now()]);
        $this->mock(DemoSeeder::class)->shouldNotReceive('run');
        $this->post(route('reports.company-overview.'.$action))->assertSessionHasErrors('fiscal_year');
    }

    public function test_workspace_settings_update_only_the_active_year(): void
    {
        $this->allow('configs.index', 'configs.edit', 'configs.update');
        $subject = Subject::factory()->create(['fiscal_year_id' => $this->year->id]);
        $this->get(route('configs.edit', 'cash_book'))->assertOk();
        $other = Config::withoutGlobalScopes()->create(['fiscal_year_id' => $this->other->id, 'key' => 'cash_book', 'value' => '999', 'type' => 3, 'category' => 1]);
        $this->put(route('configs.update', 'cash_book'), ['key' => 'cash_book', 'code' => $subject->code])->assertSessionHasNoErrors()->assertRedirect(route('configs.index'));
        $this->assertDatabaseHas('configs', ['fiscal_year_id' => $this->year->id, 'key' => 'cash_book', 'value' => (string) $subject->id]);
        $this->assertDatabaseHas('configs', ['id' => $other->id, 'value' => '999']);
        $this->get(route('configs.index'))->assertOk()->assertDontSee('/management/settings', false);
    }
}
