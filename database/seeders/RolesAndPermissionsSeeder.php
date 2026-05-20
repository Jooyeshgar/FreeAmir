<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\OrganizationUnit;
use App\Models\OrgChart;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Standard RESTful resource actions.
     */
    private const CRUD = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];

    /**
     * RESTful resource actions without `show`.
     */
    private const CRUD_NO_SHOW = ['index', 'create', 'store', 'edit', 'update', 'destroy'];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->definePermissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->seedRoles();
        $this->seedDemoUsersAndEmployees();
    }

    /**
     * Build the full flat list of permissions from the grouped definition.
     *
     * Each entry of `$groups` is `prefix => actions[]`. The resulting permission
     * name is `"{$prefix}.{$action}"`. Free-standing permissions (not tied to a
     * RESTful resource) are listed under `extras`.
     *
     * @return array<int, string>
     */
    private function definePermissions(): array
    {
        $groups = [
            // Subjects / accounting subjects
            'subjects' => [...self::CRUD, 'search', 'search-code'],

            // Documents (accounting documents) and their files
            'documents' => [
                ...self::CRUD,
                'print', 'duplicate', 'change-status', 'approve-all',
                'search-account-balance',
                'sort-numbers', 'sort-numbers.start', 'sort-numbers.process',
            ],
            'documents.files' => ['create', 'store', 'edit', 'update', 'destroy', 'view', 'download'],

            'transactions' => ['index', 'show'],

            // Products / services and their groups
            'products' => [...self::CRUD, 'search-product-group'],
            'product-groups' => self::CRUD,
            'services' => [...self::CRUD, 'search-service-group'],
            'service-groups' => self::CRUD,

            // Customers
            'customers' => self::CRUD,
            'customer-groups' => self::CRUD,

            // Companies + fiscal-year wizard
            'companies' => [
                ...self::CRUD,
                'close-fiscal-year',
                'closing-wizard', 'closing-wizard.step1', 'closing-wizard.step3',
            ],

            // Backups
            'backups' => ['create', 'export', 'import', 'upload', 'document-files-size'],

            // Banks / bank accounts
            'bank-accounts' => [...self::CRUD, 'search-bank'],
            'banks' => self::CRUD,

            // Invoices and their statuses
            'invoices' => [
                ...self::CRUD,
                'print', 'change-status', 'search', 'approve', 'get-items',
                'moadian-form', 'moadian-histories.index', 'moadian-histories.show', 'send-moadian', 'moadian-check-status',
                'search-customer', 'search-product-service',
                'inactive', 'inactive.approve',
                'conflicts', 'conflicts.more',
                'group-action', 'void-form', 'void',
            ],

            // Ancillary costs
            'ancillary-costs' => ['index', 'search-customer', 'search-invoice', 'get-products', 'approve', 'change-status'],
            'invoices.ancillary-costs' => ['create', 'store', 'show', 'edit', 'update', 'destroy'],

            // Users / Roles / Permissions / Configs
            'users' => [...self::CRUD, 'create-employee'],
            'permissions' => self::CRUD_NO_SHOW,
            'roles' => self::CRUD_NO_SHOW,
            'configs' => self::CRUD,

            // HR
            'hr.employees' => [...self::CRUD, 'export'],
            'hr.org-charts' => self::CRUD,
            'hr.organization-units' => self::CRUD,
            'hr.personnel-requests' => [...self::CRUD, 'approve', 'reject'],

            // Attendance
            'attendance.attendance-logs' => [
                ...self::CRUD,
                'recalculate', 'recalculate-all',
                'import', 'import.preview', 'import.store',
            ],
            'attendance.monthly-attendances' => [...self::CRUD, 'recalculate', 'payroll.store'],
            'attendance.work-shifts' => self::CRUD_NO_SHOW,

            // Salary / Payroll
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

            // Warehouse
            'warehouse' => ['dashboard'],

            // Comments
            'comments' => self::CRUD_NO_SHOW,

            // Reports
            'reports' => ['ledger', 'journal', 'sub-ledger', 'trial-balance', 'trial-balance.print', 'documents', 'result'],

            // API
            'api-tokens' => ['index', 'create', 'store', 'destroy'],

            // Home
            'home' => ['cash-banks', 'bank-account', 'seed-demo-data', 'refresh-database'],

            // Employee portal
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

        $extras = [
            'home',
            'api.access',
            'api-tokens.index',
            'api-tokens.create',
            'api-tokens.store',
            'api-tokens.destroy',

            'subjects.index',
            'subjects.create',
            'subjects.store',
            'subjects.show',
            'subjects.edit',
            'subjects.update',
            'subjects.destroy',
            'subjects.search',
            'subjects.search-code',

            'documents.index',
            'documents.create',
            'documents.store',
            'documents.show',
            'documents.edit',
            'documents.update',
            'documents.destroy',
            'documents.print',
            'documents.duplicate',
            'documents.change-status',
            'documents.approve-all',
            'documents.search-account-balance',
            'documents.sort-numbers',
            'documents.sort-numbers.start',
            'documents.sort-numbers.process',

            'transactions.index',
            'transactions.show',

            'products.index',
            'products.create',
            'products.store',
            'products.show',
            'products.edit',
            'products.update',
            'products.destroy',
            'products.search-product-group',

            'product-groups.index',
            'product-groups.create',
            'product-groups.store',
            'product-groups.show',
            'product-groups.edit',
            'product-groups.update',
            'product-groups.destroy',

            'services.index',
            'services.create',
            'services.store',
            'services.show',
            'services.edit',
            'services.update',
            'services.destroy',
            'services.search-service-group',

            'service-groups.index',
            'service-groups.create',
            'service-groups.store',
            'service-groups.show',
            'service-groups.edit',
            'service-groups.update',
            'service-groups.destroy',

            'customers.index',
            'customers.create',
            'customers.store',
            'customers.show',
            'customers.edit',
            'customers.update',
            'customers.destroy',

            'customer-groups.index',
            'customer-groups.create',
            'customer-groups.store',
            'customer-groups.show',
            'customer-groups.edit',
            'customer-groups.update',
            'customer-groups.destroy',

            'companies.index',
            'companies.create',
            'companies.store',
            'companies.show',
            'companies.edit',
            'companies.update',
            'companies.destroy',
            'companies.close-fiscal-year',
            'companies.closing-wizard',
            'companies.closing-wizard.step1',
            'companies.closing-wizard.step3',

            'backups.create',
            'backups.export',
            'backups.import',
            'backups.upload',
            'backups.document-files-size',

            'bank-accounts.index',
            'bank-accounts.create',
            'bank-accounts.store',
            'bank-accounts.show',
            'bank-accounts.edit',
            'bank-accounts.update',
            'bank-accounts.destroy',
            'bank-accounts.search-bank',

            'banks.index',
            'banks.create',
            'banks.store',
            'banks.show',
            'banks.edit',
            'banks.update',
            'banks.destroy',

            'invoices.index',
            'invoices.create',
            'invoices.store',
            'invoices.show',
            'invoices.edit',
            'invoices.update',
            'invoices.destroy',
            'invoices.print',
            'invoices.change-status',
            'invoices.search',
            'invoices.approve',
            'invoices.get-items',
            'invoices.search-customer',
            'invoices.search-product-service',
            'invoices.inactive',
            'invoices.inactive.approve',
            'invoices.conflicts',
            'invoices.conflicts.more',
            'invoices.group-action',
            'invoices.void-form',
            'invoices.void',
            'invoices.moadian-form',
            'invoices.moadian-histories.index',
            'invoices.moadian-histories.show',
            'invoices.send-moadian',
            'invoices.moadian-check-status',

            'ancillary-costs.index',
            'ancillary-costs.search-customer',
            'ancillary-costs.search-invoice',
            'ancillary-costs.get-products',
            'ancillary-costs.approve',
            'ancillary-costs.change-status',

            'invoices.ancillary-costs.create',
            'invoices.ancillary-costs.store',
            'invoices.ancillary-costs.show',
            'invoices.ancillary-costs.edit',
            'invoices.ancillary-costs.update',
            'invoices.ancillary-costs.destroy',

            'users.index',
            'users.create',
            'users.store',
            'users.show',
            'users.edit',
            'users.update',
            'users.destroy',
            'users.create-employee',

            'permissions.index',
            'permissions.create',
            'permissions.store',
            'permissions.edit',
            'permissions.update',
            'permissions.destroy',

            'roles.index',
            'roles.create',
            'roles.store',
            'roles.edit',
            'roles.update',
            'roles.destroy',

            'configs.index',
            'configs.create',
            'configs.store',
            'configs.show',
            'configs.edit',
            'configs.update',
            'configs.destroy',

            'hr.employees.index',
            'hr.employees.create',
            'hr.employees.store',
            'hr.employees.show',
            'hr.employees.export',
            'hr.employees.edit',
            'hr.employees.update',
            'hr.employees.destroy',

            'hr.org-charts.index',
            'hr.org-charts.create',
            'hr.org-charts.store',
            'hr.org-charts.show',
            'hr.org-charts.edit',
            'hr.org-charts.update',
            'hr.org-charts.destroy',

            'hr.organization-units.index',
            'hr.organization-units.create',
            'hr.organization-units.store',
            'hr.organization-units.show',
            'hr.organization-units.edit',
            'hr.organization-units.update',
            'hr.organization-units.destroy',

            'hr.personnel-requests.index',
            'hr.personnel-requests.create',
            'hr.personnel-requests.store',
            'hr.personnel-requests.show',
            'hr.personnel-requests.edit',
            'hr.personnel-requests.update',
            'hr.personnel-requests.destroy',
            'hr.personnel-requests.approve',
            'hr.personnel-requests.reject',

            'attendance.attendance-logs.index',
            'attendance.attendance-logs.create',
            'attendance.attendance-logs.store',
            'attendance.attendance-logs.show',
            'attendance.attendance-logs.edit',
            'attendance.attendance-logs.update',
            'attendance.attendance-logs.destroy',
            'attendance.attendance-logs.recalculate',
            'attendance.attendance-logs.import',
            'attendance.attendance-logs.import.preview',
            'attendance.attendance-logs.import.store',
            'attendance.attendance-logs.recalculate-all',

            'attendance.monthly-attendances.index',
            'attendance.monthly-attendances.create',
            'attendance.monthly-attendances.store',
            'attendance.monthly-attendances.show',
            'attendance.monthly-attendances.edit',
            'attendance.monthly-attendances.update',
            'attendance.monthly-attendances.destroy',
            'attendance.monthly-attendances.recalculate',
            'attendance.monthly-attendances.payroll.store',

            'attendance.work-shifts.index',
            'attendance.work-shifts.create',
            'attendance.work-shifts.store',
            'attendance.work-shifts.edit',
            'attendance.work-shifts.update',
            'attendance.work-shifts.destroy',

            'salary.tax-slabs.index',
            'salary.tax-slabs.create',
            'salary.tax-slabs.store',
            'salary.tax-slabs.show',
            'salary.tax-slabs.edit',
            'salary.tax-slabs.update',
            'salary.tax-slabs.destroy',

            'salary.work-sites.index',
            'salary.work-sites.create',
            'salary.work-sites.store',
            'salary.work-sites.edit',
            'salary.work-sites.update',
            'salary.work-sites.destroy',

            'salary.work-site-contracts.index',
            'salary.work-site-contracts.create',
            'salary.work-site-contracts.store',
            'salary.work-site-contracts.edit',
            'salary.work-site-contracts.update',
            'salary.work-site-contracts.destroy',

            'salary.public-holidays.index',
            'salary.public-holidays.create',
            'salary.public-holidays.store',
            'salary.public-holidays.edit',
            'salary.public-holidays.update',
            'salary.public-holidays.destroy',

            'salary.payroll-elements.index',
            'salary.payroll-elements.create',
            'salary.payroll-elements.store',
            'salary.payroll-elements.edit',
            'salary.payroll-elements.update',
            'salary.payroll-elements.destroy',

            'salary.salary-decrees.index',
            'salary.salary-decrees.create',
            'salary.salary-decrees.store',
            'salary.salary-decrees.edit',
            'salary.salary-decrees.update',
            'salary.salary-decrees.destroy',

            'salary.payrolls.index',
            'salary.payrolls.show',
            'salary.payrolls.destroy',
            'salary.payrolls.transition.draft-to-pending-manager-approval',
            'salary.payrolls.transition.pending-manager-approval-to-approved',
            'salary.payrolls.transition.approved-to-paid',

            'salary.payroll-items.edit',
            'salary.payroll-items.update',

            'reports.ledger',
            'reports.journal',
            'reports.sub-ledger',
            'reports.trial-balance',
            'reports.trial-balance.print',
            'reports.documents',
            'reports.result',

            'comments.index',
            'comments.create',
            'comments.store',
            'comments.edit',
            'comments.update',
            'comments.destroy',

            'documents.files.create',
            'documents.files.store',
            'documents.files.edit',
            'documents.files.update',
            'documents.files.destroy',
            'documents.files.view',
            'documents.files.download',

            'employee-portal.employee.show',
            'employee-portal.change-employee-information',
            'employee-portal.update-employee-information',
            'employee-portal.dashboard',
            'employee-portal.attendance-logs',
            'employee-portal.monthly-attendances',
            'employee-portal.monthly-attendances.show',
            'employee-portal.payrolls',
            'employee-portal.payrolls.show',

            'employee-portal.personnel-requests.index',
            'employee-portal.personnel-requests.create',
            'employee-portal.personnel-requests.store',
            'employee-portal.personnel-requests.edit',
            'employee-portal.personnel-requests.update',
            'employee-portal.personnel-requests.destroy',

            'change-company',
        ];

        $permissions = $extras;
        foreach ($groups as $prefix => $actions) {
            foreach ($actions as $action) {
                $permissions[] = $prefix.'.'.$action;
            }
        }

        return array_values(array_unique($permissions));
    }

    private function seedRoles(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'Super-Admin']);
        $superAdmin->syncPermissions(Permission::all());

        $accountant = Role::firstOrCreate(['name' => __('Accountant')]);
        $accountant->syncPermissions(
            Permission::query()
                ->where('name', 'NOT LIKE', 'users.%')
                ->where('name', 'NOT LIKE', 'roles.%')
                ->where('name', 'NOT LIKE', 'permissions.%')
                ->where('name', 'NOT LIKE', 'configs.%')
                ->where('name', 'NOT LIKE', 'api.%')
                ->where('name', 'NOT LIKE', 'api-tokens.%')
                ->where('name', '!=', 'salary.payrolls.transition.pending-manager-approval-to-approved')
                ->pluck('name')
                ->toArray()
        );

        $warehouse = Role::firstOrCreate(['name' => __('Warehousekeeper')]);
        $warehouse->syncPermissions(
            Permission::query()
                ->where(fn ($q) => $q
                    ->where('name', 'LIKE', 'products.%')
                    ->orWhere('name', 'LIKE', 'product-groups.%')
                    ->orWhere('name', 'LIKE', 'warehouse.%')
                    ->orWhere('name', 'LIKE', 'home%'))
                ->pluck('name')
                ->toArray()
        );

        $seller = Role::firstOrCreate(['name' => __('Seller')]);
        $seller->syncPermissions(
            Permission::query()
                ->where(fn ($q) => $q
                    ->where('name', 'LIKE', 'invoices.%')
                    ->orWhere('name', 'LIKE', 'ancillary-costs.%')
                    ->orWhere('name', 'LIKE', 'customers.%')
                    ->orWhere('name', 'LIKE', 'customer-groups.%')
                    ->orWhere('name', '=', 'home'))
                ->whereNotIn('name', [
                    'invoices.approve',
                    'invoices.change-status',
                    'invoices.conflicts',
                    'invoices.conflicts.more',
                    'invoices.group-action',
                    'invoices.inactive',
                    'invoices.inactive.approve',
                    'ancillary-costs.approve',
                    'ancillary-costs.change-status',
                ])
                ->pluck('name')
                ->toArray()
        );

        $employeeRole = Role::firstOrCreate(['name' => __('Employee')]);
        $employeeRole->syncPermissions(
            Permission::query()
                ->where('name', 'LIKE', 'employee-portal.%')
                ->orWhere('name', '=', 'change-company')
                ->orWhere('name', '=', 'home')
                ->pluck('name')
                ->toArray()
        );
    }

    private function seedDemoUsersAndEmployees(): void
    {
        $users = [
            'admin' => [
                'roles' => ['Super-Admin', __('Employee')],
                'org_chart' => 'مدیرعامل',
                'org_unit' => 'مدیریت',
            ],
            'accountant' => [
                'roles' => [__('Accountant'), __('Employee')],
                'org_chart' => 'حسابدار ارشد',
                'org_unit' => 'حسابداری',
            ],
            'seller' => [
                'roles' => [__('Seller'), __('Employee')],
                'org_chart' => 'کارشناس فروش',
                'org_unit' => 'فروش و بازاریابی',
            ],
            'warehouse' => [
                'roles' => [__('Warehousekeeper'), __('Employee')],
                'org_chart' => 'سرپرست انبار',
                'org_unit' => 'انبار و لجستیک',
            ],
            'seller-warehouse' => [
                'roles' => [__('Seller'), __('Warehousekeeper'), __('Employee')],
                'org_chart' => 'کارشناس فروش',
                'org_unit' => 'فروش و بازاریابی',
            ],
            'accountant-seller-warehouse' => [
                'roles' => [__('Accountant'), __('Seller'), __('Warehousekeeper'), __('Employee')],
                'org_chart' => 'مدیر مالی',
                'org_unit' => 'امور مالی',
            ],
            'employee' => [
                'roles' => [__('Employee')],
                'org_chart' => 'کارشناس منابع انسانی',
                'org_unit' => 'منابع انسانی',
            ],
        ];

        $workSite = WorkSite::firstOrCreate(
            ['code' => 'DEMO-WS-1'],
            [
                'company_id' => 1,
                'name' => 'کارگاه ۱',
                'is_active' => true,
            ]
        );

        $workShift = WorkShift::firstOrCreate(
            [
                'company_id' => 1,
                'name' => 'شیفت کاری',
            ],
            [
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'float' => 0,
                'break' => 0,
                'is_active' => true,
                'paid_leave' => 1200,
            ]
        );

        $orgCharts = OrgChart::withoutGlobalScopes()->where('company_id', 1)->get()->keyBy('title');
        $orgUnits = OrganizationUnit::withoutGlobalScopes()->where('company_id', 1)->get()->keyBy('name');

        foreach ($users as $name => $config) {
            $user = User::firstOrCreate(
                ['email' => $name.'@example.com'],
                [
                    'name' => $name,
                    'password' => bcrypt('password'),
                ]
            );
            $user->companies()->sync([1]);
            $user->assignRole($config['roles']);

            $baseCode = 'EMP-'.$user->id;
            $employeeCode = $baseCode;
            $counter = 1;

            while (Employee::withoutGlobalScopes()
                ->where('code', $employeeCode)
                ->where('user_id', '!=', $user->id)
                ->exists()) {
                $employeeCode = substr($baseCode.'-'.$counter, 0, 20);
                $counter++;
            }

            Employee::withoutGlobalScopes()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $name,
                    'last_name' => 'Demo',
                    'user_id' => $user->id,
                    'company_id' => 1,
                    'code' => $employeeCode,
                    'work_site_id' => $workSite->id,
                    'work_shift_id' => $workShift->id,
                    'org_chart_id' => $orgCharts->get($config['org_chart'])?->id,
                    'organization_unit_id' => $orgUnits->get($config['org_unit'])?->id,
                    'is_active' => true,
                    'leave_remain' => 1200,
                ]
            );
        }
    }
}
