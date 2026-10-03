<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\User;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FiscalYearAccessSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_named_years_share_a_company_but_keep_separate_access_and_accounting_data(): void
    {
        $first = FiscalYear::factory()->create(['name' => 'Shared Business', 'fiscal_year' => 1403]);
        $second = FiscalYear::factory()->create(['name' => 'Shared Business', 'fiscal_year' => 1404, 'company_id' => $first->company_id]);
        $other = FiscalYear::factory()->create(['name' => 'Other Business', 'fiscal_year' => 1404]);
        $user = User::factory()->create();
        $first->users()->attach($user);

        $this->assertSame($first->company_id, $second->company_id);
        $this->assertNotSame($first->company_id, $other->company_id);
        $this->assertSame(2, Company::findOrFail($first->company_id)->fiscalYears()->count());
        $this->assertFalse($user->companies()->whereKey($second->id)->exists());

        config(['active-fiscal-year-id' => $first->id]);
        $document = Document::factory()->create(['company_id' => $first->id]);
        $this->assertTrue(Document::query()->whereKey($document->id)->exists());

        config(['active-fiscal-year-id' => $second->id]);
        $this->assertFalse(Document::query()->whereKey($document->id)->exists());

        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'api.access']));
        $token = $user->createToken('fiscal-year-check', ['api.access', 'hr.employees.index'])->plainTextToken;
        $this->getJson('/api/companies/'.$second->id.'/employees', ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();

        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'change-company', 'guard_name' => 'web']));
        $this->actingAs($user, 'web')->get(route('change-company', $second))->assertForbidden();
        $this->get(route('change-company', $first))->assertRedirect(route('home'));
        $this->assertSame($first->company_id, getActiveCompany());
        $this->assertSame($first->id, getActiveFiscalYear());

        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'users.create', 'guard_name' => 'web']));
        $this->get(route('users.create'))->assertOk()
            ->assertSee('Shared Business')
            ->assertDontSee('value="'.$second->id.'"', false);
    }

    public function test_copying_into_a_new_year_reuses_the_company_only_for_the_same_name(): void
    {
        $source = FiscalYear::factory()->create(['name' => 'Source Business', 'fiscal_year' => 1403]);
        $user = User::factory()->create();
        $this->actingAs($user);

        $nextYear = FiscalYearService::createWithCopiedData([
            'name' => 'Source Business',
            'fiscal_year' => 1404,
        ], $source->id, []);
        $otherBusiness = FiscalYearService::createWithCopiedData([
            'name' => 'Other Business',
            'fiscal_year' => 1404,
        ], $source->id, []);

        $this->assertSame($source->company_id, $nextYear->company_id);
        $this->assertNotSame($source->company_id, $otherBusiness->company_id);
    }

    public function test_editing_shared_company_fields_updates_all_years(): void
    {
        $first = FiscalYear::factory()->create(['name' => 'Old Business', 'fiscal_year' => 1403]);
        $second = FiscalYear::factory()->create([
            'name' => 'Old Business',
            'fiscal_year' => 1404,
            'company_id' => $first->company_id,
        ]);
        $admin = User::factory()->create();
        $admin->givePermissionTo(
            Permission::firstOrCreate(['name' => 'access-super-admin-panel']),
            Permission::firstOrCreate(['name' => 'companies.update']),
        );

        $this->actingAs($admin)->put(route('companies.update', $first), [
            'name' => 'New Business',
            'fiscal_year' => 1403,
            'currency' => 'Rial',
        ])->assertRedirect(route('companies.index'));

        $this->assertSame('New Business', $first->company->fresh()->name);
        $this->assertSame('New Business', $second->fresh()->name);
        $this->assertDatabaseMissing('companies', ['id' => $first->company_id, 'name' => 'Old Business']);
    }

    public function test_export_and_import_keep_year_boundaries_and_create_a_company_for_an_external_year(): void
    {
        $source = FiscalYear::factory()->create(['name' => 'Exported Business', 'fiscal_year' => 1403]);
        $user = User::factory()->create();
        $this->actingAs($user);

        $export = FiscalYearService::exportData($source->id, []);
        $imported = FiscalYearService::importData($export, [
            'name' => 'Imported Business',
            'fiscal_year' => 1404,
        ]);

        $this->assertSame('Exported Business', $export['meta']['source_company_name']);
        $this->assertNotSame($source->company_id, $imported->company_id);
        $this->assertTrue($user->companies()->whereKey($imported->id)->exists());
    }

    public function test_company_form_creates_a_new_year_under_the_accessible_existing_company(): void
    {
        $source = FiscalYear::factory()->create(['name' => 'Form Business', 'fiscal_year' => 1403]);
        $user = User::factory()->create();
        $source->users()->attach($user);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.store']));

        $this->actingAs($user, 'web')->withCookie('active-fiscal-year-id', (string) $source->id)
            ->post(route('companies.store'), [
                'name' => 'Form Business',
                'fiscal_year' => 1404,
                'source_year_id' => $source->id,
                'tables_to_copy' => [],
            ])->assertRedirect(route('companies.index'));

        $newYear = FiscalYear::where('company_id', $source->company_id)
            ->where('fiscal_year', 1404)->firstOrFail();
        $this->assertTrue($newYear->users()->whereKey($user->id)->exists());
        $this->assertSame(1, Company::query()->where('name', 'Form Business')->count());
    }
}
