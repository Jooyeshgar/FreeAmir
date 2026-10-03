<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiscalYearSeparationMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // This test builds the pre-migration schema itself; CI's migrated MySQL
        // database is not a suitable starting point for that fixture.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    public function test_existing_years_are_grouped_by_exact_name_without_changing_year_ids_or_access(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('fiscal_year');
            $table->string('currency')->default('Rial');
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->string('economical_code')->nullable();
            $table->string('national_code')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('private_key_path')->nullable();
            $table->string('moadian_username')->nullable();
            $table->string('tax_id')->nullable();
        });
        Schema::create('company_user', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
        });

        DB::table('companies')->insert([
            ['id' => 10, 'name' => 'Same', 'fiscal_year' => 1403, 'currency' => 'Rial'],
            ['id' => 20, 'name' => 'Same', 'fiscal_year' => 1404, 'currency' => 'Rial'],
            ['id' => 30, 'name' => 'Different', 'fiscal_year' => 1404, 'currency' => 'Rial'],
            ['id' => 40, 'name' => 'same', 'fiscal_year' => 1405, 'currency' => 'Rial'],
        ]);
        DB::table('company_user')->insert(['company_id' => 10, 'user_id' => 5]);
        DB::table('documents')->insert(['id' => 1, 'company_id' => 20]);

        $migration = require database_path('migrations/2026_10_03_000000_separate_companies_and_fiscal_years.php');
        $migration->up();

        $this->assertSame(3, DB::table('companies')->count());
        $this->assertFalse(Schema::hasColumn('fiscal_years', 'name'));
        $this->assertFalse(Schema::hasColumn('companies', 'fiscal_year'));
        $this->assertSame(
            DB::table('fiscal_years')->where('id', 10)->value('company_id'),
            DB::table('fiscal_years')->where('id', 20)->value('company_id')
        );
        $this->assertNotSame(
            DB::table('fiscal_years')->where('id', 10)->value('company_id'),
            DB::table('fiscal_years')->where('id', 30)->value('company_id')
        );
        $this->assertNotSame(
            DB::table('fiscal_years')->where('id', 10)->value('company_id'),
            DB::table('fiscal_years')->where('id', 40)->value('company_id')
        );
        $this->assertDatabaseHas('fiscal_year_user', ['fiscal_year_id' => 10, 'user_id' => 5]);
        $this->assertDatabaseHas('documents', ['id' => 1, 'company_id' => 20]);
        $this->assertContains('fiscal_years', array_column(DB::select('PRAGMA foreign_key_list(documents)'), 'table'));

        $migration->down();

        $this->assertDatabaseHas('companies', ['id' => 10, 'name' => 'Same', 'fiscal_year' => 1403]);
        $this->assertDatabaseHas('company_user', ['company_id' => 10, 'user_id' => 5]);
        $this->assertDatabaseHas('documents', ['id' => 1, 'company_id' => 20]);
    }
}
