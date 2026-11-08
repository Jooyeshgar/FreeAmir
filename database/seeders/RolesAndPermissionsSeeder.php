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
    public function run(): void
    {
        $this->seedPermissionsAndRoles();
        $this->seedDemoUsersAndEmployees();
    }

    public function seedPermissionsAndRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->definePermissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $this->seedRoles();
    }

    /**
     * Grant all actions for each route resource. Standalone capabilities keep
     * their existing names because they do not represent resource actions.
     *
     * @return array<int, string>
     */
    private function definePermissions(): array
    {
        $resources = [
            'subjects',
            'documents',
            'documents.files',
            'transactions',
            'products',
            'product-groups',
            'services',
            'service-groups',
            'customers',
            'customer-groups',
            'crm',
            'companies',
            'fiscal-years',
            'backups',
            'bank-accounts',
            'banks',
            'cheques',
            'chequebooks',
            'invoices',
            'ancillary-costs',
            'invoices.ancillary-costs',
            'invoices.payments',
            'users',
            'permissions',
            'roles',
            'activity-logs',
            'configs',
            'hr.employees',
            'hr.org-charts',
            'hr.organization-units',
            'hr.personnel-requests',
            'attendance.attendance-logs',
            'attendance.monthly-attendances',
            'attendance.work-shifts',
            'salary.tax-slabs',
            'salary.work-sites',
            'salary.work-site-contracts',
            'salary.public-holidays',
            'salary.payroll-elements',
            'salary.salary-decrees',
            'salary.payrolls',
            'salary.payroll-items',
            'warehouse',
            'warehouses',
            'budgets',
            'comments',
            'reports',
            'commercial-ledgers',
            'api-tokens',
            'home',
            'employee-portal',
            'employee-portal.personnel-requests',
        ];

        return [
            ...array_map(fn (string $resource) => $resource.'.*', $resources),
            'home',
            'access-super-admin-panel',
            'api.*',
            'change-company',
            'update-global-configs',
            'report',
        ];
    }

    private function seedRoles(): void
    {
        // Seeded roles use only the declared web permissions, including on reruns
        // where legacy action grants or unrelated wildcard records still exist.
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->definePermissions());

        $superAdmin = Role::firstOrCreate(['name' => 'Super-Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions((clone $permissions)->get());

        $admin = Role::firstOrCreate(['name' => __('Admin'), 'guard_name' => 'web']);
        $admin->syncPermissions(
            (clone $permissions)
                ->where('name', 'NOT LIKE', 'roles.%')
                ->where('name', 'NOT LIKE', 'permissions.%')
                ->where('name', '!=', 'access-super-admin-panel')
                ->where('name', '!=', 'update-global-configs')
                ->pluck('name')
                ->toArray()
        );

        $superAdmin->users()->each(fn (User $user) => $user->assignRole($admin));

        $accountant = Role::firstOrCreate(['name' => __('Accountant'), 'guard_name' => 'web']);
        $accountant->syncPermissions(
            (clone $permissions)
                ->where('name', 'NOT LIKE', 'roles.%')
                ->where('name', 'NOT LIKE', 'permissions.%')
                ->where('name', 'NOT LIKE', 'configs.%')
                ->where('name', '!=', 'access-super-admin-panel')
                ->where('name', '!=', 'update-global-configs')
                ->where('name', 'NOT LIKE', 'api.%')
                ->where('name', 'NOT LIKE', 'api-tokens.%')
                ->pluck('name')
                ->toArray()
        );

        $warehouse = Role::firstOrCreate(['name' => __('Warehousekeeper'), 'guard_name' => 'web']);
        $warehouse->syncPermissions(
            (clone $permissions)
                ->where(fn ($q) => $q
                    ->where('name', 'LIKE', 'products.%')
                    ->orWhere('name', 'LIKE', 'product-groups.%')
                    ->orWhere('name', 'LIKE', 'warehouse.%')
                    ->orWhere('name', 'LIKE', 'home%'))
                ->pluck('name')
                ->toArray()
        );

        $seller = Role::firstOrCreate(['name' => __('Seller'), 'guard_name' => 'web']);
        $seller->syncPermissions(
            (clone $permissions)
                ->where(fn ($q) => $q
                    ->where('name', 'LIKE', 'invoices.%')
                    ->orWhere('name', 'LIKE', 'ancillary-costs.%')
                    ->orWhere('name', 'LIKE', 'customers.%')
                    ->orWhere('name', 'LIKE', 'customer-groups.%')
                    ->orWhere('name', 'LIKE', 'crm.%')
                    ->orWhereIn('name', ['home', 'home.*']))
                ->pluck('name')
                ->toArray()
        );

        $employeeRole = Role::firstOrCreate(['name' => __('Employee'), 'guard_name' => 'web']);
        $employeeRole->syncPermissions(
            (clone $permissions)
                ->where(fn ($query) => $query
                    ->where('name', 'LIKE', 'employee-portal.%')
                    ->orWhere('name', '=', 'change-company')
                    ->orWhereIn('name', ['home', 'home.*'])
                    ->orWhere('name', '=', 'attendance.attendance-logs.*'))
                ->pluck('name')
                ->toArray()
        );
    }

    private function seedDemoUsersAndEmployees(): void
    {
        $fiscalYearId = (int) getActiveFiscalYear();
        $users = [
            'super-admin' => [
                'roles' => ['Super-Admin', __('Admin'), __('Employee')],
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
            ['fiscal_year_id' => $fiscalYearId, 'code' => 'DEMO-WS-1'],
            [
                'name' => 'کارگاه ۱',
                'is_active' => true,
            ]
        );

        $workShift = WorkShift::firstOrCreate(
            [
                'fiscal_year_id' => $fiscalYearId,
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

        $orgCharts = OrgChart::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)->get()->keyBy('title');
        $orgUnits = OrganizationUnit::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)->get()->keyBy('name');

        foreach ($users as $name => $config) {
            $email = $name === 'super-admin' ? 'admin@example.com' : $name.'@example.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => __($name),
                    'password' => bcrypt('password'),
                ]
            );

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->fiscalYears()->syncWithoutDetaching([$fiscalYearId]);
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
                ['user_id' => $user->id, 'fiscal_year_id' => $fiscalYearId],
                [
                    'first_name' => __($name),
                    'last_name' => __('Demo'),
                    'user_id' => $user->id,
                    'fiscal_year_id' => $fiscalYearId,
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
