<?php

namespace Tests\Feature;

use App\Models\CompanyIdentity;
use App\Models\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class FiscalYearIdentityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_names_and_per_field_fallback_preserve_each_legacy_year(): void
    {
        $first = $this->legacyCompany('Amir', 1402, [
            'address' => 'First address', 'phone_number' => '111', 'national_code' => null,
        ]);
        $second = $this->legacyCompany('Amir', 1403, [
            'address' => null, 'phone_number' => '', 'logo' => 'new-logo',
        ]);
        $spaced = $this->legacyCompany('Amir ', 1403, ['address' => 'Spaced']);
        $cased = $this->legacyCompany('amir', 1403, ['address' => 'Lowercase']);

        $this->runMapping();

        $this->assertSame(3, DB::table('company_identities')->count());
        $years = DB::table('fiscal_years')->whereIn('legacy_company_id', [$first, $second])->orderBy('year')->get();
        $this->assertCount(2, $years);
        $this->assertSame($years[0]->company_identity_id, $years[1]->company_identity_id);
        $this->assertSame([1402, 1403], $years->pluck('year')->all());
        $identity = DB::table('company_identities')->find($years[0]->company_identity_id);
        $this->assertSame('First address', $identity->address);
        $this->assertSame('', $identity->phone_number);
        $this->assertSame('new-logo', $identity->logo);
        $this->assertNull($identity->national_code);
        $this->assertCount(2, CompanyIdentity::findOrFail($identity->id)->fiscalYears);
        $this->assertSame($first, FiscalYear::where('legacy_company_id', $first)->firstOrFail()->legacyCompany->id);
        $this->assertNotEquals($years[0]->company_identity_id, DB::table('fiscal_years')->where('legacy_company_id', $spaced)->value('company_identity_id'));
        $this->assertNotEquals($years[0]->company_identity_id, DB::table('fiscal_years')->where('legacy_company_id', $cased)->value('company_identity_id'));
    }

    public function test_closing_references_accounting_rows_and_user_grants_remain_recoverable(): void
    {
        $first = $this->legacyCompany('Shared', 1402);
        $second = $this->legacyCompany('Shared', 1403);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Accountant', 'email' => 'accountant@example.test', 'password' => 'unused',
        ]);
        $documentId = DB::table('documents')->insertGetId(['company_id' => $first]);
        DB::table('companies')->where('id', $first)->update([
            'closed_at' => '2026-01-01 12:00:00', 'closed_by' => $userId,
            'pl_document_id' => $documentId, 'closing_document_id' => $documentId,
            'closing_recalculation_step' => 2,
        ]);
        DB::table('company_user')->insert(['company_id' => $first, 'user_id' => $userId]);

        $this->runMapping();

        $year = DB::table('fiscal_years')->where('legacy_company_id', $first)->first();
        $this->assertSame($userId, $year->closed_by);
        $this->assertSame($documentId, $year->pl_document_id);
        $this->assertSame($documentId, $year->closing_document_id);
        $this->assertSame(2, $year->closing_recalculation_step);
        $this->assertNotNull($year->closed_at);
        $this->assertDatabaseHas('documents', ['id' => $documentId, 'company_id' => $first]);
        $this->assertDatabaseHas('company_user', ['company_id' => $first, 'user_id' => $userId]);
        $this->assertDatabaseHas('fiscal_year_user', ['fiscal_year_id' => $year->id, 'user_id' => $userId]);
        $this->assertDatabaseMissing('fiscal_year_user', [
            'fiscal_year_id' => DB::table('fiscal_years')->where('legacy_company_id', $second)->value('id'),
            'user_id' => $userId,
        ]);
        $this->assertSame(2, DB::table('companies')->where('name', 'Shared')->count());
    }

    public function test_duplicate_year_rejects_group_before_creating_identity_tables(): void
    {
        $this->legacyCompany('Duplicate', 1403);
        $this->legacyCompany('Duplicate', 1403);
        $this->dropMappingTables();

        try {
            $this->migration()->up();
            $this->fail('Expected duplicate year rejection.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate fiscal year 1403', $exception->getMessage());
        }

        $this->assertFalse(Schema::hasTable('company_identities'));
        $this->assertFalse(Schema::hasTable('fiscal_years'));
        $this->assertSame(2, DB::table('companies')->where('name', 'Duplicate')->count());
    }

    private function legacyCompany(string $name, int $year, array $attributes = []): int
    {
        return DB::table('companies')->insertGetId(array_merge([
            'name' => $name, 'fiscal_year' => $year, 'currency' => 'Rial',
        ], $attributes));
    }

    private function runMapping(): void
    {
        $this->dropMappingTables();
        $this->migration()->up();
    }

    private function dropMappingTables(): void
    {
        Schema::dropIfExists('fiscal_year_user');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('company_identities');
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_04_000000_map_legacy_companies_to_fiscal_years.php');
    }
}
