<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    private const COMPANY_REFERENCE_TABLES = [
        'banks' => ['foreign_key' => 'banks_company_id_foreign', 'on_delete' => 'cascade'],
        'documents' => ['foreign_key' => 'documents_company_id_foreign', 'on_delete' => 'cascade'],
        'configs' => ['foreign_key' => 'configs_company_id_foreign', 'on_delete' => 'cascade'],
        'subjects' => ['foreign_key' => 'subjects_company_id_foreign', 'on_delete' => 'cascade'],
        'customer_groups' => ['foreign_key' => 'customer_groups_company_id_foreign', 'on_delete' => 'cascade'],
        'customers' => ['foreign_key' => 'customers_company_id_foreign', 'on_delete' => 'cascade'],
        'invoices' => ['foreign_key' => 'invoices_company_id_foreign', 'on_delete' => 'cascade'],
        'product_groups' => ['foreign_key' => 'product_groups_company_id_foreign', 'on_delete' => 'cascade'],
        'products' => ['foreign_key' => 'products_company_id_foreign', 'on_delete' => 'cascade'],
        'bank_accounts' => ['foreign_key' => 'bank_accounts_company_id_foreign', 'on_delete' => 'cascade'],
        'cheques' => ['foreign_key' => 'cheques_company_id_foreign', 'on_delete' => 'cascade'],
        'company_user' => ['foreign_key' => 'company_user_company_id_foreign', 'on_delete' => 'cascade'],
        'ancillary_costs' => ['foreign_key' => 'ancillary_costs_company_id_foreign', 'on_delete' => 'cascade'],
        'service_groups' => ['foreign_key' => 'service_groups_company_id_foreign', 'on_delete' => 'cascade'],
        'services' => ['foreign_key' => 'services_company_id_foreign', 'on_delete' => 'cascade'],
        'work_shifts' => ['foreign_key' => 'work_shifts_company_id_foreign', 'on_delete' => 'cascade'],
        'org_charts' => ['foreign_key' => 'org_charts_company_id_foreign', 'on_delete' => 'cascade'],
        'work_sites' => ['foreign_key' => 'work_sites_company_id_foreign', 'on_delete' => 'cascade'],
        'public_holidays' => ['foreign_key' => 'public_holidays_company_id_foreign', 'on_delete' => 'cascade'],
        'employees' => ['foreign_key' => 'employees_company_id_foreign', 'on_delete' => 'cascade'],
        'payroll_elements' => ['foreign_key' => 'payroll_elements_company_id_foreign', 'on_delete' => 'cascade'],
        'salary_decrees' => ['foreign_key' => 'salary_decrees_company_id_foreign', 'on_delete' => 'cascade'],
        'monthly_attendances' => ['foreign_key' => 'monthly_attendances_company_id_foreign', 'on_delete' => 'cascade'],
        'attendance_logs' => ['foreign_key' => 'attendance_logs_company_id_foreign', 'on_delete' => 'cascade'],
        'tax_slabs' => ['foreign_key' => 'tax_slabs_company_id_foreign', 'on_delete' => 'cascade'],
        'payrolls' => ['foreign_key' => 'payrolls_company_id_foreign', 'on_delete' => 'cascade'],
        'personnel_requests' => ['foreign_key' => 'personnel_requests_company_id_foreign', 'on_delete' => 'cascade'],
        'organization_units' => ['foreign_key' => 'organization_units_company_id_foreign', 'on_delete' => 'cascade'],
        'monthly_budgets' => ['foreign_key' => 'monthly_budgets_company_id_foreign', 'on_delete' => 'cascade'],
        'chequebooks' => ['foreign_key' => 'chequebooks_company_id_foreign', 'on_delete' => 'cascade'],
        'warehouses' => ['foreign_key' => 'warehouses_company_id_foreign', 'on_delete' => 'cascade'],
        'warehouse_transfers' => ['foreign_key' => 'warehouse_transfers_company_id_foreign', 'on_delete' => 'cascade'],
        'commercial_ledger_exports' => ['foreign_key' => 'commercial_ledger_exports_company_id_foreign', 'on_delete' => 'cascade'],
    ];

    public function up(): void
    {
        $duplicateYear = DB::table('companies')->select('name', 'fiscal_year')->groupBy('name', 'fiscal_year')->havingRaw('COUNT(*) > 1')->first();

        if ($duplicateYear) {
            throw new RuntimeException("Company {$duplicateYear->name} has more than one record for fiscal year {$duplicateYear->fiscal_year}.");
        }

        $this->assertReferenceTables();

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->unsignedBigInteger('pl_document_id')->nullable();
            $table->unsignedBigInteger('closing_document_id')->nullable();
            $table->unsignedTinyInteger('closing_recalculation_step')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['company_id', 'year']);
        });

        $duplicateCompanyIds = DB::transaction(fn (): array => $this->migrateFiscalYearData());

        try {
            $this->assertReferenceForeignKeys('company_id', 'companies');
            $this->migrateReferenceSchema('company_id', 'fiscal_year_id', 'fiscal_years');
            Schema::rename('company_user', 'fiscal_year_user');

            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn([
                    'fiscal_year', 'pl_document_id', 'closing_document_id',
                    'closing_recalculation_step', 'closed_at', 'closed_by',
                ]);
            });
        } catch (Throwable $exception) {
            if (Schema::hasTable('fiscal_year_user')) {
                Schema::rename('fiscal_year_user', 'company_user');
            }

            $this->restoreReferenceSchema();
            Schema::dropIfExists('fiscal_years');

            throw $exception;
        }

        DB::transaction(function () use ($duplicateCompanyIds): void {
            foreach (array_keys($duplicateCompanyIds) as $companyId) {
                DB::table('companies')->where('id', $companyId)->delete();
            }
        });
    }

    public function down(): void
    {
        if (DB::table('fiscal_years')->select('company_id')->groupBy('company_id')->havingRaw('COUNT(*) > 1')->exists()
                || DB::table('fiscal_years')->whereColumn('id', '!=', 'company_id')->exists()) {
            throw new RuntimeException('Cannot roll back after additional fiscal years have been created.');
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('fiscal_year')->nullable();
            $table->unsignedBigInteger('pl_document_id')->nullable();
            $table->unsignedBigInteger('closing_document_id')->nullable();
            $table->unsignedTinyInteger('closing_recalculation_step')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
        });

        foreach (DB::table('fiscal_years')->orderBy('id')->cursor() as $fiscalYear) {
            DB::table('companies')->where('id', $fiscalYear->company_id)->update([
                'fiscal_year' => $fiscalYear->year,
                'pl_document_id' => $fiscalYear->pl_document_id,
                'closing_document_id' => $fiscalYear->closing_document_id,
                'closing_recalculation_step' => $fiscalYear->closing_recalculation_step,
                'closed_at' => $fiscalYear->closed_at,
                'closed_by' => $fiscalYear->closed_by,
            ]);
        }

        Schema::rename('fiscal_year_user', 'company_user');
        $this->migrateReferenceSchema('fiscal_year_id', 'company_id', 'companies');
        Schema::dropIfExists('fiscal_years');

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('fiscal_year')->nullable(false)->change();
        });
    }

    private function migrateFiscalYearData(): array
    {
        $canonicalCompanyIds = [];
        $duplicateCompanyIds = [];

        foreach (DB::table('companies')->orderBy('id')->cursor() as $company) {
            $canonicalCompanyIds[$company->name] ??= $company->id;
            $canonicalCompanyId = $canonicalCompanyIds[$company->name];

            if ($canonicalCompanyId !== $company->id) {
                $duplicateCompanyIds[$company->id] = $canonicalCompanyId;
            }

            DB::table('fiscal_years')->insert([
                'id' => $company->id,
                'company_id' => $canonicalCompanyId,
                'year' => $company->fiscal_year,
                'pl_document_id' => $company->pl_document_id,
                'closing_document_id' => $company->closing_document_id,
                'closing_recalculation_step' => $company->closing_recalculation_step,
                'closed_at' => $company->closed_at,
                'closed_by' => $company->closed_by,
            ]);
        }

        return $duplicateCompanyIds;
    }

    private function assertReferenceTables(): void
    {
        foreach (array_keys(self::COMPANY_REFERENCE_TABLES) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
                throw new RuntimeException("Expected company reference table {$tableName} with a company_id column.");
            }
        }
    }

    private function assertReferenceForeignKeys(string $column, string $target): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        foreach (self::COMPANY_REFERENCE_TABLES as $tableName => $definition) {
            $foreignKeys = Schema::getForeignKeys($tableName);
            $expectedName = $this->foreignKeyName($tableName, $column, $definition);

            $matches = array_filter($foreignKeys, function (array $foreignKey) use ($column, $target, $expectedName, $sqlite): bool {
                return $foreignKey['columns'] === [$column]
                    && $foreignKey['foreign_table'] === $target
                    && ($sqlite || $foreignKey['name'] === $expectedName);
            });

            if ($matches === []) {
                throw new RuntimeException("Expected {$tableName}.{$column} to reference {$target} before schema migration.");
            }
        }
    }

    private function migrateReferenceSchema(string $from, string $to, string $target): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        if ($sqlite) {
            Schema::disableForeignKeyConstraints();
        }

        try {
            foreach (self::COMPANY_REFERENCE_TABLES as $tableName => $definition) {
                $foreignKey = $this->foreignKeyName($tableName, $from, $definition);

                Schema::table($tableName, function (Blueprint $table) use ($from, $foreignKey, $sqlite) {
                    $table->dropForeign($sqlite ? [$from] : $foreignKey);
                });

                Schema::table($tableName, function (Blueprint $table) use ($from, $to) {
                    $table->renameColumn($from, $to);
                });

                Schema::table($tableName, function (Blueprint $table) use ($to, $target, $definition) {
                    $table->foreign($to)
                        ->references('id')->on($target)
                        ->onDelete($definition['on_delete']);
                });
            }
        } finally {
            if ($sqlite) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    private function foreignKeyName(string $tableName, string $column, array $definition): string
    {
        if ($column === 'company_id') {
            return $definition['foreign_key'];
        }

        return "{$tableName}_{$column}_foreign";
    }

    private function restoreReferenceSchema(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        if ($sqlite) {
            Schema::disableForeignKeyConstraints();
        }

        try {
            foreach (self::COMPANY_REFERENCE_TABLES as $tableName => $definition) {
                if (! Schema::hasTable($tableName)) {
                    continue;
                }

                $foreignKeys = Schema::getForeignKeys($tableName);
                $hasFiscalYearColumn = Schema::hasColumn($tableName, 'fiscal_year_id');

                if ($hasFiscalYearColumn) {
                    $newForeignKey = $this->foreignKeyName($tableName, 'fiscal_year_id', $definition);
                    $hasNewForeignKey = $sqlite
                        ? $this->hasForeignKey($foreignKeys, 'fiscal_year_id', 'fiscal_years')
                        : $this->hasForeignKey($foreignKeys, null, null, $newForeignKey);

                    if ($hasNewForeignKey) {
                        Schema::table($tableName, function (Blueprint $table) use ($sqlite, $newForeignKey) {
                            $table->dropForeign($sqlite ? ['fiscal_year_id'] : $newForeignKey);
                        });
                    }

                    Schema::table($tableName, function (Blueprint $table) {
                        $table->renameColumn('fiscal_year_id', 'company_id');
                    });
                }

                $foreignKeys = Schema::getForeignKeys($tableName);
                if (! $this->hasForeignKey($foreignKeys, 'company_id', 'companies')) {
                    Schema::table($tableName, function (Blueprint $table) use ($definition) {
                        $table->foreign('company_id')
                            ->references('id')->on('companies')
                            ->onDelete($definition['on_delete']);
                    });
                }
            }
        } finally {
            if ($sqlite) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }

    private function hasForeignKey(array $foreignKeys, ?string $column, ?string $target, ?string $name = null): bool
    {
        return array_filter($foreignKeys, function (array $foreignKey) use ($column, $target, $name): bool {
            return ($column === null || $foreignKey['columns'] === [$column])
                && ($target === null || $foreignKey['foreign_table'] === $target)
                && ($name === null || $foreignKey['name'] === $name);
        }) !== [];
    }
};
