<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CompanyBackup extends Command
{
    protected $signature = 'backup:company {company_id*} {--public-only}';

    protected $description = 'Backup public and company-related tables dynamically';

    public function handle()
    {
        $companyIds = (array) $this->argument('company_id');
        $publicOnly = $this->option('public-only');
        $timestamp = now()->format('Y_m_d_His');
        $baseName = 'companies_'.implode('_', $companyIds)."_{$timestamp}";
        $encryptedFile = "{$baseName}.sql.enc";

        $storageDisk = Storage::disk('local');

        // Define known public tables
        $publicTables = [
            'migrations',
            'ltm_translations',
            'model_has_permissions',
            'model_has_roles',
            'permissions',
            'roles',
            'role_has_permissions',
            'users',
        ];

        // Define known company tables
        $companyTables = [
            'banks',
            'bank_accounts',
            'cheques',
            'companies',
            'company_user',
            'configs',
            'customers',
            'customer_groups',
            'documents',
            'invoices',
            'payments',
            'products',
            'product_groups',
            'subjects',
            'transactions',
        ];

        /**
         * Recursive helper: find all tables related to company tables
         */
        $findRelatedTables = function ($baseTables, &$visited = []) use (&$findRelatedTables) {
            if (empty($baseTables)) {
                return [];
            }

            $related = [];
            foreach ($baseTables as $baseTable) {
                if (in_array($baseTable, $visited)) {
                    continue;
                }
                $visited[] = $baseTable;

                $foreignRefs = DB::select('
                SELECT TABLE_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND REFERENCED_TABLE_NAME = ?
            ', [$baseTable]);

                foreach ($foreignRefs as $ref) {
                    $relatedTable = $ref->TABLE_NAME;
                    if (! in_array($relatedTable, $related)) {
                        $related[] = $relatedTable;
                        $related = array_merge($related, $findRelatedTables([$relatedTable], $visited));
                    }
                }
            }

            return array_unique($related);
        };

        // Detect all tables
        $allTables = array_map(fn ($t) => array_values((array) $t)[0], DB::select('SHOW TABLES'));

        // Base company tables: direct company_id or explicitly listed
        $companyBaseTables = array_filter($allTables, fn ($table) => Schema::hasColumn($table, 'company_id') || in_array($table, $companyTables)
        );

        // Recursively find all tables related to company ones
        $relatedCompanyTables = $findRelatedTables($companyBaseTables);
        $companyRelatedTables = array_unique(array_merge($companyBaseTables, $relatedCompanyTables));

        $this->info('Detected company-related tables: '.implode(', ', $companyRelatedTables));

        // Merge final tables to backup
        $tablesToBackup = $publicOnly
            ? $publicTables
            : array_unique(array_merge($publicTables, $companyRelatedTables));

        $sqlDump = "SET NAMES 'utf8mb4';\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tablesToBackup as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $hasCompanyId = Schema::hasColumn($table, 'company_id');
            $rows = collect();

            if ($hasCompanyId) {
                // Collect all rows for each company
                $rows = DB::table($table)
                    ->whereIn('company_id', $companyIds)
                    ->get();
            } elseif ($table === 'companies') {
                $rows = DB::table($table)
                    ->whereIn('id', $companyIds)
                    ->get();
            } elseif ($table === 'company_user') {
                $rows = DB::table($table)
                    ->whereIn('company_id', $companyIds)
                    ->get();
            } elseif (in_array($table, $companyRelatedTables)) {
                // Related tables: we might need to inspect foreign keys
                $foreignKeys = DB::select('
                SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ', [$table]);

                foreach ($foreignKeys as $fk) {
                    if (in_array($fk->REFERENCED_TABLE_NAME, $companyBaseTables)) {
                        $refTable = $fk->REFERENCED_TABLE_NAME;
                        $refCol = $fk->REFERENCED_COLUMN_NAME;

                        if (Schema::hasColumn($refTable, 'company_id')) {
                            $refIds = DB::table($refTable)
                                ->whereIn('company_id', $companyIds)
                                ->pluck($refCol)
                                ->toArray();

                            if (! empty($refIds)) {
                                $rows = DB::table($table)
                                    ->whereIn($fk->COLUMN_NAME, $refIds)
                                    ->get();
                            }
                        }
                    }
                }
            } else {
                // Public table → dump everything
                $rows = DB::table($table)->get();
            }

            if ($rows->isEmpty()) {
                continue;
            }

            $tableEsc = '`'.str_replace('`', '``', $table).'`';
            $this->info("Backing up table: {$table} ({$rows->count()} rows)");

            $sqlDump .= "TRUNCATE TABLE {$tableEsc};\n";
            foreach ($rows as $row) {
                $rowArr = (array) $row;
                $columns = array_map(fn ($col) => '`'.str_replace('`', '``', $col).'`', array_keys($rowArr));
                $values = array_map(fn ($v) => is_null($v) ? 'NULL' : DB::getPdo()->quote($v), array_values($rowArr));

                $sqlDump .= "INSERT INTO {$tableEsc} (".implode(',', $columns).') VALUES ('.implode(',', $values).");\n";
            }
            $sqlDump .= "\n";
        }

        $sqlDump .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

        // Encrypt and save
        $encryptedContent = Crypt::encryptString($sqlDump);
        $storageDisk->put("backups/{$encryptedFile}", $encryptedContent);

        $this->info("Combined backup saved: storage/app/backups/{$encryptedFile}");
    }
}
