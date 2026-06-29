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
            'subjects' => [...self::CRUD, 'search', 'search-code', 'transfer-form', 'transfer'],

            // Documents (accounting documents) and their files
            'documents' => [
                ...self::CRUD,
                'print', 'duplicate', 'change-status', 'approve-all',
                'search-account-balance', 'export', 'export.download', 'import', 'import.store',
                'sort-numbers', 'sort-numbers.start', 'sort-numbers.process', 'transfer',
            ],
            'documents.files' => ['create', 'store', 'edit', 'update', 'destroy', 'view', 'download'],

            'transactions' => ['index', 'show'],

            // Products / services and their groups
            'products' => [...self::CRUD, 'search-product-group', 'report'],
            'product-groups' => self::CRUD,
            'services' => [...self::CRUD, 'search-service-group'],
            'service-groups' => self::CRUD,

            // Customers
            'customers' => [...self::CRUD, 'export', 'import', 'import.store'],
            'customer-groups' => self::CRUD,

            // CRM
            'crm' => ['dashboard'],

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
                'group-action', 'void-form', 'void', 'transfer',
            ],

            // Ancillary costs
            'ancillary-costs' => ['index', 'search-customer', 'search-invoice', 'get-products', 'approve', 'change-status'],
            'invoices.ancillary-costs' => ['create', 'store', 'show', 'edit', 'update', 'destroy'],

            // Invoice payments
            'invoices.payments' => ['store', 'destroy', 'create-document', 'destroy-document'],

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
                'bulk-create', 'bulk-store', 'search-employee',
            ],
            'attendance.monthly-attendances' => [...self::CRUD, 'recalculate', 'payroll.store', 'bulk-create', 'bulk-store'],
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
            'reports' => ['ledger', 'journal', 'sub-ledger', 'trial-balance', 'trial-balance.print', 'trial-balance.export-csv', 'documents', 'result', 'cost-income'],

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
            'change-company',
            'update-global-configs',
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
                ->where('name', '!=', 'update-global-configs')
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
                    ->orWhere('name', 'LIKE', 'crm.%')
                    ->orWhere('name', '=', 'home'))
                ->whereNotIn('name', [
                    'invoices.approve',
                    'invoices.change-status',
                    'invoices.conflicts',
                    'invoices.conflicts.more',
                    'invoices.group-action',
                    'invoices.inactive',
                    'invoices.inactive.approve',
                    'invoices.transfer',
                    'ancillary-costs.approve',
                    'ancillary-costs.change-status',
                    'invoices.payments.store',
                    'invoices.payments.destroy',
                    'invoices.payments.create-document',
                    'invoices.payments.destroy-document',
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
                ->orWhere('name', '=', 'attendance.attendance-logs.show')
                ->pluck('name')
                ->toArray()
        );
    }

    private function seedDemoUsersAndEmployees(): void
    {
        $users = [
            'super-admin' => [
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
            'Warehousekeeper' => [
                'roles' => [__('Warehousekeeper'), __('Employee')],
                'org_chart' => 'سرپرست انبار',
                'org_unit' => 'انبار و لجستیک',
            ],
            'seller-Warehousekeeper' => [
                'roles' => [__('Seller'), __('Warehousekeeper'), __('Employee')],
                'org_chart' => 'کارشناس فروش',
                'org_unit' => 'فروش و بازاریابی',
            ],
            'accountant-seller-Warehousekeeper' => [
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
            $email = $name === 'super-admin' ? 'admin@example.com' : $name.'@example.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => __($name),
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
                    'first_name' => __($name),
                    'last_name' => __('Demo'),
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
