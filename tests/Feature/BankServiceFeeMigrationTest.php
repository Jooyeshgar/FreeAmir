<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\FiscalYear;
use App\Models\Subject;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BankServiceFeeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_each_fiscal_year_independently_and_preserves_custom_settings(): void
    {
        $firstYear = FiscalYear::factory()->create(['year' => 1405]);
        $secondYear = FiscalYear::factory()->create([
            'company_id' => $firstYear->company_id,
            'year' => 1406,
        ]);
        $customYear = FiscalYear::factory()->create([
            'company_id' => $firstYear->company_id,
            'year' => 1407,
        ]);
        $yearWithoutSubject = FiscalYear::factory()->create([
            'company_id' => $firstYear->company_id,
            'year' => 1408,
        ]);

        $subjectIds = [];
        foreach ([$firstYear, $secondYear, $customYear] as $year) {
            $subjectIds[$year->id] = Subject::create([
                'fiscal_year_id' => $year->id,
                'code' => '040010',
                'name' => 'کارمزد بانک',
                'parent_id' => null,
                'type' => SubjectType::DEBTOR,
            ])->id;
        }
        $customSubject = Subject::create([
            'fiscal_year_id' => $customYear->id,
            'code' => '040099',
            'name' => 'Custom fee account',
            'parent_id' => null,
            'type' => SubjectType::DEBTOR,
        ]);
        DB::table('configs')->insert([
            'fiscal_year_id' => $customYear->id,
            'key' => 'bank_service_fee',
            'value' => (string) $customSubject->id,
            'desc' => 'Custom bank fee',
            'type' => 3,
            'category' => 1,
        ]);

        // Recreate the schema immediately before this migration on an existing installation.
        foreach (['payments', 'cheque_histories'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('bank_service_fee'));
        }
        $migration = require database_path('migrations/2026_10_11_000001_add_bank_service_fee_to_payments_table.php');
        $migration->up();

        foreach ([$firstYear, $secondYear] as $year) {
            $this->assertDatabaseHas('configs', [
                'fiscal_year_id' => $year->id,
                'key' => 'bank_service_fee',
                'value' => (string) $subjectIds[$year->id],
                'desc' => 'کارمزد بانک',
            ]);
        }
        $this->assertDatabaseHas('configs', [
            'fiscal_year_id' => $customYear->id,
            'key' => 'bank_service_fee',
            'value' => (string) $customSubject->id,
            'desc' => 'Custom bank fee',
        ]);
        $this->assertDatabaseMissing('configs', [
            'fiscal_year_id' => $yearWithoutSubject->id,
            'key' => 'bank_service_fee',
        ]);
        $this->assertSame(3, DB::table('configs')->where('key', 'bank_service_fee')->count());
        $this->assertTrue(Schema::hasColumn('payments', 'bank_service_fee'));
        $this->assertTrue(Schema::hasColumn('cheque_histories', 'bank_service_fee'));
    }
}
