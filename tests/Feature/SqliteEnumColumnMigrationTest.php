<?php

namespace Tests\Feature;

use App\Enums\ThursdayStatus;
use App\Models\FiscalYear;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SqliteEnumColumnMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = __DIR__.'/../../database/migrations/2026_11_24_000001_convert_sqlite_work_shift_thursday_status.php';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('This migration only applies to SQLite.');
        }
    }

    public function test_every_converted_column_is_an_integer_on_sqlite(): void
    {
        $columns = [
            'subjects' => ['type'], 'invoices' => ['invoice_type', 'status'], 'ancillary_costs' => ['type', 'status'],
            'ancillary_cost_items' => ['type'], 'employees' => ['nationality', 'gender', 'marital_status', 'duty_status', 'insurance_type', 'education_level', 'employment_type'],
            'payroll_elements' => ['system_code', 'category', 'calc_type'], 'payrolls' => ['status'],
            'payroll_status_histories' => ['from_status', 'to_status'], 'personnel_requests' => ['request_type', 'status'],
            'work_shifts' => ['thursday_status'], 'customers' => ['type'],
        ];

        foreach ($columns as $table => $names) {
            foreach ($names as $column) {
                $this->assertSame('integer', Schema::getColumnType($table, $column), "{$table}.{$column} should be an integer column");
            }
        }
    }

    public function test_up_converts_existing_string_rows_and_down_restores_them(): void
    {
        $migration = require self::MIGRATION;
        $migration->down();

        $this->assertSame('varchar', Schema::getColumnType('work_shifts', 'thursday_status'));

        $fiscalYearId = FiscalYear::factory()->create()->id;
        $row = ['fiscal_year_id' => $fiscalYearId, 'name' => 'x', 'start_time' => '08:00', 'end_time' => '16:00'];
        $holiday = DB::table('work_shifts')->insertGetId($row + ['thursday_status' => 'holiday']);
        $fullDay = DB::table('work_shifts')->insertGetId($row + ['thursday_status' => 'full_day']);
        $default = DB::table('work_shifts')->insertGetId($row);

        $migration->up();

        $this->assertSame('integer', Schema::getColumnType('work_shifts', 'thursday_status'));
        $this->assertSame(ThursdayStatus::HOLIDAY, WorkShift::find($holiday)->thursday_status);
        $this->assertSame(ThursdayStatus::FULL_DAY, WorkShift::find($fullDay)->thursday_status);
        $this->assertSame(ThursdayStatus::HALF_DAY, WorkShift::find($default)->thursday_status);

        $migration->down();

        $this->assertSame(['holiday', 'full_day', 'half_day'], [
            DB::table('work_shifts')->where('id', $holiday)->value('thursday_status'),
            DB::table('work_shifts')->where('id', $fullDay)->value('thursday_status'),
            DB::table('work_shifts')->where('id', $default)->value('thursday_status'),
        ]);

        $migration->up();
    }

    public function test_indexes_on_converted_columns_are_preserved(): void
    {
        $migration = require self::MIGRATION;
        $migration->down();
        $migration->up();

        $index = collect(Schema::getIndexes('invoices'))->firstWhere('name', 'invoices_number_invoice_type_company_id_unique');

        $this->assertNotNull($index);
        $this->assertTrue($index['unique']);
        $this->assertContains('invoice_type', $index['columns']);
    }
}
