<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompanyIdentityConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_year_inherits_the_persistent_company_details(): void
    {
        $first = Company::create(['name' => 'Shared', 'fiscal_year' => 1402, 'address' => 'Canonical']);
        $second = Company::create(['name' => 'Shared', 'fiscal_year' => 1403, 'address' => 'Stale']);

        $this->assertSame('Canonical', $second->address);
        $this->assertDatabaseHas('companies', ['id' => $second->id, 'address' => 'Canonical']);
        $this->assertSame($first->fiscalYear->company_identity_id, $second->fiscalYear->company_identity_id);
    }

    public function test_shared_details_update_all_year_rows_without_changing_year_state(): void
    {
        $first = Company::create(['name' => 'Shared', 'fiscal_year' => 1402, 'address' => 'Old address']);
        $second = Company::create(['name' => 'Shared', 'fiscal_year' => 1403, 'address' => 'Old address']);
        $second->update(['closed_at' => '2026-01-01 12:00:00']);

        $first->update(['address' => 'New address', 'phone_number' => '09123456789']);

        $this->assertDatabaseHas('company_identities', [
            'id' => $first->fiscalYear->company_identity_id,
            'address' => 'New address',
            'phone_number' => '09123456789',
        ]);
        $this->assertDatabaseHas('companies', [
            'id' => $second->id,
            'address' => 'New address',
            'phone_number' => '09123456789',
            'fiscal_year' => 1403,
        ]);
        $this->assertNotNull($second->fresh()->closed_at);
        $this->assertSame($first->fiscalYear->company_identity_id, $second->fiscalYear->company_identity_id);
    }

    public function test_renaming_one_year_into_another_company_is_rejected(): void
    {
        $first = Company::create(['name' => 'First', 'fiscal_year' => 1402]);
        $other = Company::create(['name' => 'Other', 'fiscal_year' => 1403]);

        try {
            $first->update(['name' => $other->name]);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $this->assertDatabaseHas('companies', ['id' => $first->id, 'name' => 'First']);
        $this->assertDatabaseHas('company_identities', [
            'id' => $first->fiscalYear->company_identity_id,
            'name' => 'First',
        ]);
    }

    public function test_duplicate_year_is_rejected_before_a_legacy_row_is_inserted(): void
    {
        Company::create(['name' => 'Shared', 'fiscal_year' => 1403]);

        try {
            Company::create(['name' => 'Shared', 'fiscal_year' => 1403]);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fiscal_year', $exception->errors());
        }

        $this->assertSame(1, Company::where('name', 'Shared')->count());
    }

    public function test_upgrade_reconciles_legacy_year_details_by_identity_without_changing_links(): void
    {
        $first = Company::create(['name' => 'Shared', 'fiscal_year' => 1402, 'address' => 'Old']);
        $second = Company::create(['name' => 'Shared', 'fiscal_year' => 1403, 'address' => 'Old']);
        $identityId = $first->fiscalYear->company_identity_id;
        DB::table('company_identities')->where('id', $identityId)->update([
            'address' => 'Canonical', 'phone_number' => '',
        ]);
        DB::table('companies')->where('id', $second->id)->update([
            'address' => 'Different', 'phone_number' => '09123456789',
        ]);

        $migration = require database_path('migrations/2026_10_05_000000_sync_legacy_company_details.php');
        $migration->up();

        foreach ([$first, $second] as $company) {
            $this->assertDatabaseHas('companies', [
                'id' => $company->id, 'address' => 'Canonical', 'phone_number' => '',
            ]);
            $this->assertDatabaseHas('fiscal_years', [
                'legacy_company_id' => $company->id,
                'company_identity_id' => $identityId,
                'year' => $company->fiscal_year,
            ]);
        }
    }
}
