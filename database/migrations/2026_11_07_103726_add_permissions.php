<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'subjects.*',
            'documents.*',
            'documents.files.*',
            'transactions.*',
            'products.*',
            'product-groups.*',
            'services.*',
            'service-groups.*',
            'customers.*',
            'customer-groups.*',
            'crm.*',
            'companies.*',
            'fiscal-years.*',
            'backups.*',
            'bank-accounts.*',
            'banks.*',
            'cheques.*',
            'chequebooks.*',
            'invoices.*',
            'ancillary-costs.*',
            'invoices.ancillary-costs.*',
            'invoices.payments.*',
            'users.*',
            'permissions.*',
            'roles.*',
            'activity-logs.*',
            'configs.*',
            'hr.employees.*',
            'hr.org-charts.*',
            'hr.organization-units.*',
            'hr.personnel-requests.*',
            'attendance.attendance-logs.*',
            'attendance.monthly-attendances.*',
            'attendance.work-shifts.*',
            'salary.tax-slabs.*',
            'salary.work-sites.*',
            'salary.work-site-contracts.*',
            'salary.public-holidays.*',
            'salary.payroll-elements.*',
            'salary.salary-decrees.*',
            'salary.payrolls.*',
            'salary.payroll-items.*',
            'warehouse.*',
            'warehouses.*',
            'budgets.*',
            'comments.*',
            'reports.*',
            'commercial-ledgers.*',
            'api-tokens.*',
            'home.*',
            'employee-portal.*',
            'employee-portal.personnel-requests.*',
            'api.*',
        ];

        DB::table('permissions')->insertOrIgnore(array_map(
            fn (string $permission) => ['name' => $permission, 'guard_name' => 'web'],
            $permissions
        ));
    }

    public function down(): void
    {
        // Intentionally irreversible: some or all permissions may have existed before this backfill, so deleting them would also remove role links.
    }
};
