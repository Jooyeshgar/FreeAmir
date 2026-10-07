<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $duplicateYear = DB::table('companies')->select('name', 'fiscal_year')->groupBy('name', 'fiscal_year')->havingRaw('COUNT(*) > 1')->first();

        if ($duplicateYear) {
            throw new RuntimeException("Company {$duplicateYear->name} has more than one record for fiscal year {$duplicateYear->fiscal_year}.");
        }

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

        // Keep the original IDs so old company references resolve to their corresponding fiscal years without changing stored row values.
        $canonicalCompanyIds = [];
        $duplicateCompanyIds = [];

        foreach (DB::table('companies')->orderBy('id')->cursor() as $company) {
            $canonicalCompanyIds[$company->name] ??= $company->id;
            $canonicalCompanyId = $canonicalCompanyIds[$company->name];

            if ($canonicalCompanyId !== $company->id) {
                $duplicateCompanyIds[] = $company->id;
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

        $this->remapReferences('company_id', 'fiscal_year_id', 'fiscal_years');
        Schema::rename('company_user', 'fiscal_year_user');

        foreach ($duplicateCompanyIds as $companyId) {
            DB::table('companies')->where('id', $companyId)->delete();
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'fiscal_year', 'pl_document_id', 'closing_document_id',
                'closing_recalculation_step', 'closed_at', 'closed_by',
            ]);
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
        $this->remapReferences('fiscal_year_id', 'company_id', 'companies');
        Schema::dropIfExists('fiscal_years');

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('fiscal_year')->nullable(false)->change();
        });
    }

    private function remapReferences(string $from, string $to, string $target): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        if ($sqlite) {
            Schema::disableForeignKeyConstraints();
        }

        try {
            foreach (Schema::getTableListing(schemaQualified: false) as $tableName) {
                if ($tableName === 'fiscal_years' || ! Schema::hasColumn($tableName, $from)) {
                    continue;
                }

                $foreignKeys = array_values(array_filter(
                    Schema::getForeignKeys($tableName),
                    fn (array $key) => $key['columns'] === [$from]
                ));

                foreach ($foreignKeys as $key) {
                    Schema::table($tableName, function (Blueprint $table) use ($from, $key, $sqlite) {
                        $table->dropForeign($sqlite ? [$from] : $key['name']);
                    });
                }

                Schema::table($tableName, function (Blueprint $table) use ($from, $to) {
                    $table->renameColumn($from, $to);
                });

                foreach ($foreignKeys as $key) {
                    Schema::table($tableName, function (Blueprint $table) use ($to, $target, $key) {
                        $table->foreign($to)
                            ->references('id')->on($target)
                            ->onUpdate($key['on_update'])
                            ->onDelete($key['on_delete']);
                    });
                }
            }
        } finally {
            if ($sqlite) {
                Schema::enableForeignKeyConstraints();
            }
        }
    }
};
