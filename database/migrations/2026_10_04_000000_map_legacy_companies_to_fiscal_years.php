<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COMPANY_FIELDS = [
        'name', 'logo', 'address', 'economical_code', 'national_code',
        'postal_code', 'phone_number', 'currency', 'certificate_path',
        'private_key_path', 'moadian_username', 'tax_id',
    ];

    // Every table whose company_id previously named a fiscal year receives the
    // old row's fiscal year before its company_id is changed to the survivor.
    private const YEAR_TABLES = [
        'ancillary_costs', 'attendance_logs', 'bank_accounts', 'banks',
        'cheques', 'chequebooks', 'commercial_ledger_exports', 'configs',
        'customer_groups', 'customers', 'documents', 'employees', 'invoices',
        'monthly_attendances', 'monthly_budgets', 'org_charts',
        'organization_units', 'payroll_elements', 'payrolls',
        'personnel_requests', 'product_groups', 'products', 'public_holidays',
        'salary_decrees', 'service_groups', 'services', 'subjects', 'tax_slabs',
        'warehouse_transfers', 'warehouses', 'work_shifts', 'work_sites',
    ];

    public function up(): void
    {
        $groups = DB::table('companies')->orderBy('id')->get()->groupBy(
            // SQL name comparison may ignore case or trailing spaces.
            fn ($row) => bin2hex($row->name)
        );

        foreach ($groups as $rows) {
            $seen = [];
            foreach ($rows as $row) {
                if (isset($seen[$row->fiscal_year])) {
                    throw new RuntimeException("Duplicate fiscal year {$row->fiscal_year} for company name [{$row->name}] in legacy rows {$seen[$row->fiscal_year]} and {$row->id}.");
                }
                $seen[$row->fiscal_year] = $row->id;
            }
        }

        Schema::create('fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->unsignedBigInteger('pl_document_id')->nullable();
            $table->unsignedBigInteger('closing_document_id')->nullable();
            $table->unsignedTinyInteger('closing_recalculation_step')->nullable();
            $table->unique(['company_id', 'year']);
        });

        Schema::create('fiscal_year_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['fiscal_year_id', 'user_id']);
        });

        foreach (self::YEAR_TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('fiscal_year_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->cascadeOnDelete();
        });

        // An old company_id was a year boundary. Move every unique constraint
        // using it to fiscal_year_id before collapsing IDs, so repeated codes or
        // invoice numbers in different years remain valid.
        $uniqueIndexes = [];
        foreach (self::YEAR_TABLES as $name) {
            $indexes = Schema::getIndexes($name);
            $replaced = array_filter($indexes, fn ($index) => ($index['unique'] ?? false) && in_array('company_id', $index['columns'], true)
            );
            if ($replaced !== [] && ! collect($indexes)->contains(fn ($index) => ! ($index['unique'] ?? false) && ($index['columns'][0] ?? null) === 'company_id'
            )) {
                // MySQL needs a separate supporting index for the company FK.
                Schema::table($name, fn (Blueprint $table) => $table->index('company_id', $name.'_company_id_lookup'));
            }
            foreach ($replaced as $index) {
                $uniqueIndexes[$name][] = $index;
                Schema::table($name, fn (Blueprint $table) => $table->dropUnique($index['name']));
            }
        }

        $companyGrants = [];
        foreach ($groups as $rows) {
            $survivor = $rows->first();
            $details = [];
            foreach (self::COMPANY_FIELDS as $field) {
                $details[$field] = $rows->sortByDesc('id')->first(
                    fn ($row) => $row->{$field} !== null
                )?->{$field};
            }
            DB::table('companies')->where('id', $survivor->id)->update($details);

            foreach ($rows as $row) {
                $yearId = DB::table('fiscal_years')->insertGetId([
                    'company_id' => $survivor->id,
                    'year' => $row->fiscal_year,
                    'closed_at' => $row->closed_at,
                    'closed_by' => $row->closed_by,
                    'pl_document_id' => $row->pl_document_id,
                    'closing_document_id' => $row->closing_document_id,
                    'closing_recalculation_step' => $row->closing_recalculation_step,
                ]);

                foreach (DB::table('company_user')->where('company_id', $row->id)->pluck('user_id') as $userId) {
                    DB::table('fiscal_year_user')->insertOrIgnore(['fiscal_year_id' => $yearId, 'user_id' => $userId]);
                    $companyGrants[$survivor->id][$userId] = true;
                }

                foreach (self::YEAR_TABLES as $name) {
                    DB::table($name)->where('company_id', $row->id)->update(['fiscal_year_id' => $yearId]);
                }
            }
        }

        // Payments no longer have company_id. Resolve their year from the
        // invoice, document, or cheque that owns the payment.
        foreach (DB::table('payments')->get(['id', 'invoice_id', 'document_id', 'cheque_id']) as $payment) {
            $yearId = null;
            foreach (['invoice_id' => 'invoices', 'document_id' => 'documents', 'cheque_id' => 'cheques'] as $key => $table) {
                if ($payment->{$key} !== null) {
                    $yearId = DB::table($table)->where('id', $payment->{$key})->value('fiscal_year_id');
                }
                if ($yearId !== null) {
                    break;
                }
            }
            if ($yearId !== null) {
                DB::table('payments')->where('id', $payment->id)->update(['fiscal_year_id' => $yearId]);
            }
        }

        // Company access is the union of all old year grants. Fiscal-year
        // access above retains the narrower per-year assignments.
        DB::table('company_user')->delete();
        foreach ($companyGrants as $companyId => $users) {
            foreach (array_keys($users) as $userId) {
                DB::table('company_user')->insert(['company_id' => $companyId, 'user_id' => $userId]);
            }
        }

        foreach ($groups as $rows) {
            $survivorId = $rows->first()->id;
            foreach ($rows as $row) {
                if ($row->id === $survivorId) {
                    continue;
                }
                foreach (self::YEAR_TABLES as $name) {
                    DB::table($name)->where('company_id', $row->id)->update(['company_id' => $survivorId]);
                }
                DB::table('companies')->where('id', $row->id)->delete();
            }
        }

        foreach ($uniqueIndexes as $name => $indexes) {
            foreach ($indexes as $index) {
                $columns = array_map(
                    fn ($column) => $column === 'company_id' ? 'fiscal_year_id' : $column,
                    $index['columns']
                );
                Schema::table($name, fn (Blueprint $table) => $table->unique($columns, $index['name']));
            }
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'fiscal_year', 'closed_at', 'closed_by', 'pl_document_id',
                'closing_document_id', 'closing_recalculation_step',
            ]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('The consolidated company migration cannot be reversed without restoring a database backup.');
    }
};
