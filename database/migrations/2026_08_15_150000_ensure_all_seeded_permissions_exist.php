<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const CRUD = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

    private const CRUD_NO_SHOW = ['index', 'create', 'store', 'edit', 'update', 'destroy'];

    public function up(): void
    {
        $timestamp = now();
        $permissions = array_map(fn (string $permission): array => [
            'name' => $permission,
            'guard_name' => 'web',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $this->permissions());

        DB::table(config('permission.table_names.permissions'))->insertOrIgnore($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally irreversible: some or all permissions may have existed before this backfill, so deleting them would also remove role links.
    }

    private function permissions(): array
    {
        $groups = [
            'subjects' => [...self::CRUD, 'search', 'search-code', 'transfer'],
            'documents' => [
                ...self::CRUD,
                'print', 'duplicate', 'change-status', 'approve-all',
                'search-account-balance', 'export', 'export.download', 'import', 'import.store',
                'sort-numbers', 'sort-numbers.start', 'sort-numbers.process', 'transfer',
            ],
            'documents.files' => ['create', 'store', 'edit', 'update', 'destroy', 'view', 'download'],
            'transactions' => ['index', 'show'],
            'products' => [...self::CRUD, 'search-product-group', 'report'],
            'product-groups' => self::CRUD,
            'services' => [...self::CRUD, 'search-service-group'],
            'service-groups' => self::CRUD,
            'customers' => [...self::CRUD, 'export', 'import', 'import.store'],
            'customer-groups' => self::CRUD,
            'crm' => ['dashboard'],
            'companies' => [
                ...self::CRUD,
                'close-fiscal-year',
                'closing-wizard', 'closing-wizard.step1', 'closing-wizard.step3',
            ],
            'backups' => ['create', 'export', 'import', 'upload', 'document-files-size'],
            'bank-accounts' => [...self::CRUD, 'search-bank'],
            'banks' => self::CRUD,
            'cheques' => [...self::CRUD, 'report', 'transition'],
            'chequebooks' => self::CRUD,
            'invoices' => [
                ...self::CRUD,
                'print', 'export', 'change-status', 'search', 'approve', 'get-items',
                'moadian-form', 'moadian-histories.index', 'moadian-histories.show', 'send-moadian', 'moadian-check-status',
                'search-customer', 'search-product-service',
                'inactive', 'inactive.approve',
                'conflicts', 'conflicts.more',
                'group-action', 'void-form', 'void', 'transfer',
            ],
            'ancillary-costs' => ['index', 'search-customer', 'search-invoice', 'get-products', 'approve', 'change-status'],
            'invoices.ancillary-costs' => ['create', 'store', 'show', 'edit', 'update', 'destroy'],
            'invoices.payments' => ['store', 'store-cheque', 'destroy', 'create-document', 'destroy-document'],
            'users' => [...self::CRUD, 'create-employee', 'impersonate'],
            'permissions' => self::CRUD_NO_SHOW,
            'roles' => self::CRUD_NO_SHOW,
            'configs' => self::CRUD,
            'hr.employees' => [...self::CRUD, 'export'],
            'hr.org-charts' => self::CRUD,
            'hr.organization-units' => self::CRUD,
            'hr.personnel-requests' => [...self::CRUD, 'approve', 'reject'],
            'attendance.attendance-logs' => [
                ...self::CRUD,
                'recalculate', 'recalculate-all',
                'import', 'import.preview', 'import.store',
                'bulk-create', 'bulk-store', 'search-employee',
            ],
            'attendance.monthly-attendances' => [...self::CRUD, 'recalculate', 'payroll.store', 'bulk-create', 'bulk-store'],
            'attendance.work-shifts' => self::CRUD_NO_SHOW,
            'salary.tax-slabs' => self::CRUD,
            'salary.work-sites' => self::CRUD_NO_SHOW,
            'salary.work-site-contracts' => self::CRUD_NO_SHOW,
            'salary.public-holidays' => self::CRUD_NO_SHOW,
            'salary.payroll-elements' => self::CRUD_NO_SHOW,
            'salary.salary-decrees' => self::CRUD_NO_SHOW,
            'salary.payrolls' => [
                'index', 'show', 'destroy', 'dashboard',
                'transition.draft-to-pending-manager-approval',
                'transition.pending-manager-approval-to-approved',
                'transition.approved-to-paid',
            ],
            'salary.payroll-items' => ['edit', 'update'],
            'warehouse' => ['dashboard'],
            'comments' => self::CRUD_NO_SHOW,
            'reports' => [
                'ledger', 'journal', 'sub-ledger', 'trial-balance', 'trial-balance.print',
                'trial-balance.export-csv', 'documents', 'result', 'cost-income', 'company-overview',
                'company-overview.cash-banks', 'company-overview.bank-account',
                'company-overview.seed-demo-data', 'company-overview.refresh-database',
            ],
            'api-tokens' => ['index', 'create', 'store', 'destroy'],
            'home' => ['summary'],
            'employee-portal' => [
                'employee.show',
                'change-employee-information', 'update-employee-information',
                'dashboard',
                'attendance-logs',
                'monthly-attendances', 'monthly-attendances.show',
                'payrolls', 'payrolls.show',
            ],
            'employee-portal.personnel-requests' => self::CRUD_NO_SHOW,
        ];

        $permissions = [
            'home',
            'access-super-admin-panel',
            'api.access',
            'change-company',
            'update-global-configs',
            'send-to-email',
        ];

        foreach ($groups as $prefix => $actions) {
            foreach ($actions as $action) {
                $permissions[] = $prefix.'.'.$action;
            }
        }

        return array_values(array_unique($permissions));
    }
};
