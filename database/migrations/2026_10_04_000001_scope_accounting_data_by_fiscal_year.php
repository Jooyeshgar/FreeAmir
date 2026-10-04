<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'ancillary_costs', 'attendance_logs', 'banks', 'bank_accounts',
        'cheques', 'chequebooks', 'commercial_ledger_exports', 'configs',
        'customers', 'customer_groups', 'documents', 'employees', 'invoices',
        'monthly_attendances', 'monthly_budgets', 'org_charts',
        'organization_units', 'payrolls', 'payroll_elements',
        'personnel_requests', 'products', 'product_groups', 'public_holidays',
        'salary_decrees', 'services', 'service_groups', 'subjects', 'tax_slabs',
        'warehouses', 'warehouse_transfers', 'work_shifts', 'work_sites',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->cascadeOnDelete();
            });
        }

        foreach (DB::table('fiscal_years')->get(['id', 'legacy_company_id']) as $year) {
            foreach (self::TABLES as $name) {
                DB::table($name)->where('company_id', $year->legacy_company_id)->update(['fiscal_year_id' => $year->id]);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('fiscal_year_id');
            });
        }
    }
};
