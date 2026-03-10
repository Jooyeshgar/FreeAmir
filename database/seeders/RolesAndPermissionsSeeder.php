<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $permissions = [
            'home',
            'home.view',
            'home.subject-detail',

            'subjects.index',
            'subjects.create',
            'subjects.store',
            'subjects.show',
            'subjects.edit',
            'subjects.update',
            'subjects.destroy',
            'subjects.search',

            'documents.index',
            'documents.create',
            'documents.store',
            'documents.show',
            'documents.edit',
            'documents.update',
            'documents.destroy',
            'documents.duplicate',

            'transactions.index',
            'transactions.show',

            'products.index',
            'products.create',
            'products.store',
            'products.show',
            'products.edit',
            'products.update',
            'products.destroy',

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
            'change-company',
            'companies.close-fiscal-year',

            'bank-accounts.index',
            'bank-accounts.create',
            'bank-accounts.store',
            'bank-accounts.show',
            'bank-accounts.edit',
            'bank-accounts.update',
            'bank-accounts.destroy',

            'banks.index',
            'banks.create',
            'banks.store',
            'banks.show',
            'banks.edit',
            'banks.update',
            'banks.destroy',

            'invoices',
            'invoices.index',
            'invoices.create',
            'invoices.store',
            'invoices.show',
            'invoices.edit',
            'invoices.update',
            'invoices.destroy',
            'invoices.print',
            'invoices.change-status',
            'invoices.search-customer',
            'invoices.search-product-service',
            'invoices.approve',

            'ancillary-costs.index',
            'ancillary-costs.create',
            'ancillary-costs.store',
            'ancillary-costs.edit',
            'ancillary-costs.update',
            'ancillary-costs.destroy',
            'ancillary-costs.change-status',
            'ancillary-costs.get-products',
            'ancillary-costs.approve',

            'management.users.index',
            'management.users.create',
            'management.users.store',
            'management.users.show',
            'management.users.edit',
            'management.users.update',
            'management.users.destroy',

            'management.permissions.index',
            'management.permissions.create',
            'management.permissions.store',
            'management.permissions.edit',
            'management.permissions.update',
            'management.permissions.destroy',

            'management.roles.index',
            'management.roles.create',
            'management.roles.store',
            'management.roles.edit',
            'management.roles.update',
            'management.roles.destroy',

            'management.configs.index',
            'management.configs.create',
            'management.configs.store',
            'management.configs.show',
            'management.configs.edit',
            'management.configs.update',
            'management.configs.destroy',

            'reports.ledger',
            'reports.journal',
            'reports.sub-ledger',
            'reports.trial-balance',
            'reports.documents',
            'reports.result',

            'org-charts.index',
            'org-charts.create',
            'org-charts.store',
            'org-charts.show',
            'org-charts.edit',
            'org-charts.update',
            'org-charts.destroy',

            'employee-portal.dashboard',
            'employee-portal.attendance-logs',
            'employee-portal.monthly-attendances',
            'employee-portal.monthly-attendances.show',
            'employee-portal.payrolls',
            'employee-portal.personnel-requests.index',
            'employee-portal.personnel-requests.create',
            'employee-portal.personnel-requests.store',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'Super-Admin']);
        $superAdmin->syncPermissions(Permission::all());

        $accountant = Role::firstOrCreate(['name' => 'Accountant']);

        $accountantPermissions = Permission::where('name', 'NOT LIKE', 'management.%')->pluck('name')->toArray();

        $accountant->syncPermissions($accountantPermissions);

        $warehouse = Role::firstOrCreate(['name' => 'Warehousekeeper']);

        $warehousePermissions = Permission::where(function ($q) {
            $q->where('name', 'LIKE', 'products.%')
                ->orWhere('name', 'LIKE', 'product-groups.%')
                ->orWhere('name', 'LIKE', 'home%');
        })
            ->pluck('name')
            ->toArray();

        $warehouse->syncPermissions($warehousePermissions);

        $seller = Role::firstOrCreate(['name' => 'Seller']);

        $sellerPermissions = Permission::where(function ($q) {
            $q->where('name', 'LIKE', 'invoices.%')
                ->orWhere('name', 'LIKE', 'invoices')
                ->Where('name', 'NOT LIKE', 'invoices.approve')
                ->orWhere('name', 'LIKE', 'ancillary-costs.%')
                ->Where('name', 'NOT LIKE', 'ancillary-costs.approve')
                ->orWhere('name', 'LIKE', 'customers.%')
                ->orWhere('name', 'LIKE', 'home%');
        })->pluck('name')->toArray();

        $seller->syncPermissions($sellerPermissions);

        $employeeRole = Role::firstOrCreate(['name' => 'Employee']);
        $employeeRole->syncPermissions(
            Permission::where('name', 'LIKE', 'employee-portal.%')->pluck('name')->toArray()
        );

        $users = [
            'admin' => 'Super-Admin',
            'accountant' => 'Accountant',
            'seller' => 'Seller',
            'warehouse' => 'Warehousekeeper',
            'seller-warehouse' => ['Seller', 'Warehousekeeper'],
            'accountant-seller-warehouse' => ['Accountant', 'Seller', 'Warehousekeeper'],
            'employee' => 'Employee',
        ];

        foreach ($users as $name => $role) {
            $user = User::firstOrCreate(
                ['email' => $name.'@example.com'],
                [
                    'name' => $name,
                    'password' => bcrypt('password'),
                ]
            );
            $user->companies()->sync([1]);
            $user->assignRole($role);
        }

        // Create a demo employee record linked to the employee user
        $employeeUser = User::where('email', 'employee@example.com')->first();
        if ($employeeUser && ! $employeeUser->employee) {
            Employee::create([
                'first_name' => 'Demo',
                'last_name' => 'Employee',
                'national_code' => '0000000001',
                'user_id' => $employeeUser->id,
                'company_id' => 1,
                'is_active' => true,
            ]);
        }
    }
}
