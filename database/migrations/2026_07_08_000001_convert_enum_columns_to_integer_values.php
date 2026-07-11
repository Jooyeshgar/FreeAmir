<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        foreach ($this->schemaChanges() as $table => $columns) {
            foreach ($columns as $column => $definition) {
                $this->convertToTinyInteger($table, $column, $definition['map'], $definition['integer_default'], $definition['nullable'] ?? false);
            }
        }
    }

    public function down(): void
    {
        if ($this->isSqlite()) {
            return;
        }

        foreach ($this->schemaChanges() as $table => $columns) {
            foreach ($columns as $column => $definition) {
                $this->restoreToString($table, $column, $definition['map'], $definition['string_default'], $definition['nullable'] ?? false);
            }
        }
    }

    private function schemaChanges(): array
    {
        return [
            'subjects' => [
                'type' => [
                    'map' => ['debtor' => 1, 'creditor' => 2, 'both' => 3],
                    'integer_default' => 3,
                    'string_default' => 'both',
                ],
            ],

            'cheques' => [
                'status' => [
                    'map' => ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5],
                    'integer_default' => 1,
                    'string_default' => '1',
                ],
            ],

            'cheque_histories' => [
                'status' => [
                    'map' => ['1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5],
                    'integer_default' => 1,
                    'string_default' => '1',
                ],
            ],

            'invoices' => [
                'invoice_type' => [
                    'map' => [
                        'buy' => 1,
                        'sell' => 2,
                        'return_buy' => 3,
                        'return_sell' => 4,
                        'void' => 5,
                    ],
                    'integer_default' => 2,
                    'string_default' => 'sell',
                ],
                'status' => [
                    'map' => $this->invoiceStatuses(),
                    'integer_default' => 1,
                    'string_default' => 'pending',
                ],
            ],

            'ancillary_costs' => [
                'type' => [
                    'map' => $this->ancillaryCostTypes(),
                    'integer_default' => 6,
                    'string_default' => 'Other',
                ],
                'status' => [
                    'map' => $this->invoiceStatuses(),
                    'integer_default' => 1,
                    'string_default' => 'pending',
                ],
            ],

            'ancillary_cost_items' => [
                'type' => [
                    'map' => $this->ancillaryCostTypes(),
                    'integer_default' => 6,
                    'string_default' => 'Other',
                ],
            ],

            'employees' => [
                'nationality' => [
                    'map' => ['iranian' => 1, 'foreign' => 2],
                    'integer_default' => 1,
                    'string_default' => 'iranian',
                ],
                'gender' => [
                    'map' => ['male' => 1, 'female' => 2],
                    'integer_default' => null,
                    'string_default' => null,
                    'nullable' => true,
                ],
                'marital_status' => [
                    'map' => ['single' => 1, 'married' => 2, 'divorced' => 3, 'widowed' => 4],
                    'integer_default' => null,
                    'string_default' => null,
                    'nullable' => true,
                ],
                'duty_status' => [
                    'map' => ['liable' => 1, 'completed' => 2, 'in_progress' => 3, 'exempt' => 4],
                    'integer_default' => null,
                    'string_default' => null,
                    'nullable' => true,
                ],
                'insurance_type' => [
                    'map' => ['social_security' => 1, 'other' => 2],
                    'integer_default' => null,
                    'string_default' => null,
                    'nullable' => true,
                ],
                'education_level' => [
                    'map' => [
                        'below_diploma' => 1,
                        'diploma' => 2,
                        'associate' => 3,
                        'bachelor' => 4,
                        'master' => 5,
                        'phd' => 6,
                    ],
                    'integer_default' => null,
                    'string_default' => null,
                    'nullable' => true,
                ],
                'employment_type' => [
                    'map' => ['permanent' => 1, 'contract' => 2, 'other' => 3],
                    'integer_default' => null,
                    'string_default' => null,
                    'nullable' => true,
                ],
            ],

            'payroll_elements' => [
                'system_code' => [
                    'map' => $this->payrollElementSystemCodes(),
                    'integer_default' => 15,
                    'string_default' => 'OTHER',
                ],
                'category' => [
                    'map' => ['earning' => 1, 'deduction' => 2],
                    'integer_default' => 1,
                    'string_default' => 'earning',
                ],
                'calc_type' => [
                    'map' => $this->payrollElementCalcTypes(),
                    'integer_default' => 1,
                    'string_default' => 'fixed',
                ],
            ],

            'tax_slabs' => [
                'calc_type' => [
                    'map' => $this->payrollElementCalcTypes(),
                    'integer_default' => 1,
                    'string_default' => 'fixed',
                ],
            ],

            'payrolls' => [
                'status' => [
                    'map' => $this->payrollStatuses(),
                    'integer_default' => 1,
                    'string_default' => 'draft',
                ],
            ],

            'payroll_status_histories' => [
                'from_status' => [
                    'map' => $this->payrollStatuses(),
                    'integer_default' => 1,
                    'string_default' => 'draft',
                ],
                'to_status' => [
                    'map' => $this->payrollStatuses(),
                    'integer_default' => 1,
                    'string_default' => 'draft',
                ],
            ],

            'personnel_requests' => [
                'request_type' => [
                    'map' => $this->personnelRequestTypes(),
                    'integer_default' => 10,
                    'string_default' => 'OTHER',
                ],
                'status' => [
                    'map' => ['pending' => 1, 'approved' => 2, 'rejected' => 3],
                    'integer_default' => 1,
                    'string_default' => 'pending',
                ],
            ],

            'work_shifts' => [
                'thursday_status' => [
                    'map' => ['holiday' => 1, 'full_day' => 2, 'half_day' => 3],
                    'integer_default' => 3,
                    'string_default' => 'half_day',
                ],
            ],

            'customers' => [
                'type' => [
                    'map' => [
                        'individual' => 1,
                        'legal_entity' => 2,
                        'civil_partnership' => 3,
                        'foreign_national' => 4,
                    ],
                    'integer_default' => 1,
                    'string_default' => 'individual',
                ],
            ],
        ];
    }

    private function convertToTinyInteger(string $table, string $column, array $map, ?int $default, bool $nullable = false): void
    {
        if ($this->isSqlite()) {
            return;
        }

        if (! Schema::hasTable($table)) {
            return;
        }

        $this->recoverPartialIntegerMigration($table, $column, $default, $nullable);

        if (! $this->hasColumn($table, $column)) {
            return;
        }

        if ($this->isIntegerColumn($table, $column)) {
            return;
        }

        $this->modifyColumn($table, $column, $nullable ? 'VARCHAR(50) NULL' : 'VARCHAR(50) NOT NULL DEFAULT '.$this->quote((string) $default));

        foreach ($map as $stringValue => $integerValue) {
            DB::table($table)->where($column, $stringValue)->update([$column => (string) $integerValue]);
        }

        foreach ($map as $integerValue) {
            DB::table($table)->where($column, (string) $integerValue)->update([$column => (string) $integerValue]);
        }

        $validIntegerValues = array_map('strval', array_values($map));

        if ($nullable) {
            DB::table($table)->whereNotNull($column)->whereNotIn($column, $validIntegerValues)->update([$column => null]);
        } else {
            DB::table($table)
                ->where(function ($query) use ($column, $validIntegerValues) {
                    $query->whereNull($column)->orWhereNotIn($column, $validIntegerValues);
                })->update([$column => (string) $default]);
        }

        $this->modifyColumn($table, $column, $nullable ? 'TINYINT UNSIGNED NULL' : 'TINYINT UNSIGNED NOT NULL DEFAULT '.$default);
    }

    private function restoreToString(string $table, string $column, array $map, ?string $default, bool $nullable = false): void
    {
        if ($this->isSqlite()) {
            return;
        }

        if (! Schema::hasTable($table)) {
            return;
        }

        if (! $this->hasColumn($table, $column)) {
            return;
        }

        if ($this->isStringColumn($table, $column)) {
            return;
        }

        $this->modifyColumn($table, $column, $nullable ? 'VARCHAR(50) NULL' : 'VARCHAR(50) NOT NULL DEFAULT '.$this->quote($default));

        $reverseMap = array_flip($map);

        foreach ($reverseMap as $integerValue => $stringValue) {
            DB::table($table)->where($column, (string) $integerValue)->update([$column => $stringValue]);
        }

        $validStringValues = array_keys($map);

        if ($nullable) {
            DB::table($table)->whereNotNull($column)->whereNotIn($column, $validStringValues)->update([$column => null]);
        } else {
            DB::table($table)->where(function ($query) use ($column, $validStringValues) {
                $query->whereNull($column)->orWhereNotIn($column, $validStringValues);
            })->update([$column => $default]);
        }
    }

    private function recoverPartialIntegerMigration(string $table, string $column, ?int $default, bool $nullable): void
    {
        if ($this->isSqlite()) {
            return;
        }

        if ($this->hasColumn($table, $column)) {
            return;
        }

        $possibleTempColumns = ["{$column}_tmp_int", "{$column}_integer_value"];

        foreach ($possibleTempColumns as $tempColumn) {
            if (! $this->hasColumn($table, $tempColumn)) {
                continue;
            }

            $this->changeColumn($table, $tempColumn, $column, $nullable ? 'TINYINT UNSIGNED NULL' : 'TINYINT UNSIGNED NOT NULL DEFAULT '.$default);

            return;
        }
    }

    private function modifyColumn(string $table, string $column, string $definition): void
    {
        if ($this->isSqlite()) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` MODIFY `%s` %s',
            $this->escapeIdentifier($table),
            $this->escapeIdentifier($column),
            $definition
        ));
    }

    private function changeColumn(string $table, string $from, string $to, string $definition): void
    {
        if ($this->isSqlite()) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` CHANGE `%s` `%s` %s',
            $this->escapeIdentifier($table),
            $this->escapeIdentifier($from),
            $this->escapeIdentifier($to),
            $definition
        ));
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        if ($this->isSqlite()) {
            return Schema::hasColumn($table, $column);
        }

        $database = DB::getDatabaseName();

        return ! empty(DB::selectOne(
            '
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
            ',
            [$database, $table, $column]
        ));
    }

    private function isIntegerColumn(string $table, string $column): bool
    {
        return in_array($this->columnType($table, $column), ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true);
    }

    private function isStringColumn(string $table, string $column): bool
    {
        return in_array($this->columnType($table, $column), ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum'], true);
    }

    private function columnType(string $table, string $column): ?string
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        if ($this->isSqlite()) {
            return Schema::getColumnType($table, $column);
        }

        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            '
            SELECT DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
            ',
            [$database, $table, $column]
        );

        return $result ? strtolower($result->DATA_TYPE) : null;
    }

    private function quote(?string $value): string
    {
        return DB::getPdo()->quote($value ?? '');
    }

    private function escapeIdentifier(string $value): string
    {
        return str_replace('`', '``', $value);
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function invoiceStatuses(): array
    {
        return [
            'pending' => 1,
            'pre_invoice' => 2,
            'approved' => 3,
            'unapproved' => 4,
            'approved_inactive' => 5,
            'rejected' => 6,
            'ready_to_approve' => 7,
            'partially_paid' => 8,
            'paid' => 9,
        ];
    }

    private function ancillaryCostTypes(): array
    {
        return ['Shipping' => 1, 'Insurance' => 2, 'Customs' => 3, 'Taxes' => 4, 'Loading' => 5, 'Other' => 6];
    }

    private function payrollElementSystemCodes(): array
    {
        return [
            'CHILD_ALLOWANCE' => 1,
            'HOUSING_ALLOWANCE' => 2,
            'FOOD_ALLOWANCE' => 3,
            'MARRIAGE_ALLOWANCE' => 4,
            'OVERTIME' => 5,
            'AUTO_OVERTIME' => 6,
            'FRIDAY_PAY' => 7,
            'HOLIDAY_PAY' => 8,
            'MISSION_PAY' => 9,
            'INSURANCE_EMP' => 10,
            'INSURANCE_EMP2' => 11,
            'UNEMPLOYMENT_INS' => 12,
            'INCOME_TAX' => 13,
            'ABSENCE_DEDUCTION' => 14,
            'OTHER' => 15,
            'UNDERTIME' => 16,
        ];
    }

    private function payrollElementCalcTypes(): array
    {
        return ['fixed' => 1, 'formula' => 2, 'percentage' => 3, 'daily' => 4];
    }

    private function payrollStatuses(): array
    {
        return ['draft' => 1, 'pending_manager_approval' => 2, 'approved' => 3, 'paid' => 4];
    }

    private function personnelRequestTypes(): array
    {
        return [
            'LEAVE_HOURLY' => 1,
            'LEAVE_DAILY' => 2,
            'SICK_LEAVE' => 3,
            'LEAVE_WITHOUT_PAY' => 4,
            'LEAVE_WITHOUT_PAY_HOURLY' => 5,
            'MISSION_HOURLY' => 6,
            'MISSION_DAILY' => 7,
            'OVERTIME_ORDER' => 8,
            'REMOTE_WORK' => 9,
            'OTHER' => 10,
        ];
    }
};
