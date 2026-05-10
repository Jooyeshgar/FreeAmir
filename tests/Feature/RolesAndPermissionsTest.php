<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Helpers\RolesAndPermissionsHelper;
use Tests\TestCase;

class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase, RolesAndPermissionsHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;
        $this->importRolesAndPermissions($companyId);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function assertHas(User $user, array $perms): void
    {
        foreach ($perms as $perm) {
            $this->assertTrue($user->hasPermissionTo($perm), "Should have: $perm");
        }
    }

    private function assertLacks(User $user, array $perms): void
    {
        foreach ($perms as $perm) {
            $this->assertFalse($user->hasPermissionTo($perm), "Should NOT have: $perm");
        }
    }

    public function test_all_roles_are_created(): void
    {
        foreach (['Super-Admin', 'Accountant', 'Seller', 'Warehousekeeper', 'Employee'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
    }

    public function test_core_permissions_exist(): void
    {
        $perms = [
            // home
            'home', 'home.cash-banks', 'home.bank-account', 'home.seed-demo-data', 'home.refresh-database',
            // invoices
            'invoices.index', 'invoices.create', 'invoices.store', 'invoices.show',
            'invoices.edit', 'invoices.update', 'invoices.destroy', 'invoices.print',
            'invoices.search', 'invoices.get-items', 'invoices.search-customer',
            'invoices.search-product-service', 'invoices.approve', 'invoices.change-status',
            'invoices.conflicts', 'invoices.conflicts.more', 'invoices.group-action',
            'invoices.inactive', 'invoices.inactive.approve',
            // ancillary-costs
            'ancillary-costs.index', 'ancillary-costs.change-status',
            'ancillary-costs.search-customer', 'ancillary-costs.search-invoice',
            'ancillary-costs.get-products',
            'invoices.ancillary-costs.create', 'invoices.ancillary-costs.store',
            'invoices.ancillary-costs.show', 'invoices.ancillary-costs.edit',
            'invoices.ancillary-costs.update', 'invoices.ancillary-costs.destroy',
            // documents
            'documents.index', 'documents.create', 'documents.store', 'documents.show',
            'documents.edit', 'documents.update', 'documents.destroy', 'documents.print',
            'documents.duplicate', 'documents.change-status', 'documents.approve-all',
            'documents.search-account-balance', 'documents.sort-numbers',
            'documents.sort-numbers.start', 'documents.sort-numbers.process',
            // products & services
            'products.index', 'products.create', 'products.store', 'products.show',
            'products.edit', 'products.update', 'products.destroy',
            'products.search-product-group',
            'product-groups.index', 'product-groups.create', 'product-groups.store',
            'product-groups.show', 'product-groups.edit', 'product-groups.update',
            'product-groups.destroy',
            'services.index', 'service-groups.index',
            // customers
            'customers.index', 'customers.create', 'customers.store', 'customers.show',
            'customers.edit', 'customers.update', 'customers.destroy',
            'customer-groups.index',
            // management
            'users.index', 'users.create', 'users.store', 'users.show',
            'users.edit', 'users.update', 'users.destroy', 'users.create-employee',
            'roles.index', 'roles.create', 'roles.store',
            'roles.edit', 'roles.update', 'roles.destroy',
            'permissions.index', 'permissions.create', 'permissions.store',
            'permissions.edit', 'permissions.update', 'permissions.destroy',
            'configs.index', 'configs.create', 'configs.store',
            'configs.edit', 'configs.update', 'configs.destroy',
            // hr
            'hr.employees.index', 'hr.employees.create', 'hr.employees.store',
            'hr.employees.show', 'hr.employees.edit', 'hr.employees.update',
            'hr.employees.destroy',
            'hr.personnel-requests.index', 'hr.personnel-requests.approve',
            'hr.personnel-requests.reject',
            // attendance
            'attendance.attendance-logs.index', 'attendance.attendance-logs.show',
            'attendance.monthly-attendances.index',
            // salary
            'salary.payrolls.index', 'salary.payrolls.show', 'salary.payrolls.destroy',
            'salary.payroll-items.edit', 'salary.payroll-items.update',
            // reports
            'reports.ledger', 'reports.journal', 'reports.sub-ledger',
            'reports.trial-balance', 'reports.documents', 'reports.result',
            // employee portal
            'employee-portal.dashboard', 'employee-portal.employee.show',
            'employee-portal.attendance-logs', 'employee-portal.payrolls',
            'employee-portal.personnel-requests.index',
            // other
            'change-company', 'subjects.index', 'transactions.index',
            'bank-accounts.index', 'banks.index', 'companies.index',
            'backups.create', 'backups.export', 'backups.import', ];

        foreach ($perms as $perm) {
            $this->assertDatabaseHas('permissions', ['name' => $perm]);
        }
    }

    public function test_no_duplicate_permissions_exist(): void
    {
        $names = Permission::pluck('name');
        $this->assertEquals($names->count(), $names->unique()->count());
    }

    // ─── Super-Admin ─────────────────────────────────────────────────────────

    public function test_super_admin_has_all_permissions(): void
    {
        $role = Role::findByName('Super-Admin');
        $this->assertEquals(Permission::count(), $role->permissions()->count());
    }

    public function test_super_admin_user_has_all_permissions(): void
    {
        $user = $this->user('admin@example.com');

        $this->assertHas($user, [
            'users.index', 'roles.index', 'permissions.index', 'configs.index',
            'invoices.approve', 'invoices.change-status', 'invoices.inactive.approve',
            'hr.employees.index', 'salary.payrolls.index', 'reports.ledger',
            'backups.create', 'backups.export', 'change-company',
            'employee-portal.dashboard', ]);
    }

    // ─── Accountant ──────────────────────────────────────────────────────────

    public function test_accountant_has_correct_permissions(): void
    {
        $user = $this->user('accountant@example.com');

        $this->assertHas($user, [
            'home', 'home.cash-banks', 'home.bank-account',
            'documents.index', 'documents.create', 'documents.store', 'documents.show',
            'documents.edit', 'documents.update', 'documents.destroy', 'documents.print',
            'documents.duplicate', 'documents.change-status', 'documents.approve-all',
            'documents.search-account-balance', 'documents.sort-numbers',
            'documents.sort-numbers.start', 'documents.sort-numbers.process',
            'invoices.index', 'invoices.show', 'invoices.print', 'invoices.approve',
            'invoices.change-status', 'invoices.search', 'invoices.get-items',
            'invoices.search-customer', 'invoices.search-product-service',
            'invoices.conflicts', 'invoices.conflicts.more',
            'invoices.inactive', 'invoices.inactive.approve',
            'ancillary-costs.index', 'ancillary-costs.change-status',
            'invoices.ancillary-costs.show',
            'subjects.index', 'subjects.search', 'subjects.search-code',
            'transactions.index', 'transactions.show',
            'bank-accounts.index', 'bank-accounts.show',
            'banks.index', 'banks.show',
            'customers.index', 'customers.show',
            'customer-groups.index',
            'products.index', 'products.show', 'product-groups.index',
            'services.index', 'service-groups.index',
            'companies.index', 'companies.show',
            'hr.employees.index', 'hr.employees.show',
            'hr.personnel-requests.index', 'hr.personnel-requests.approve',
            'hr.personnel-requests.reject',
            'attendance.attendance-logs.index', 'attendance.attendance-logs.show',
            'attendance.monthly-attendances.index',
            'salary.payrolls.index', 'salary.payrolls.show',
            'salary.payroll-items.edit', 'salary.payroll-items.update',
            'reports.ledger', 'reports.journal', 'reports.sub-ledger',
            'reports.trial-balance', 'reports.documents', 'reports.result',
            'change-company', 'employee-portal.dashboard',
        ]);
    }

    public function test_accountant_lacks_management_write_permissions(): void
    {
        $user = $this->user('accountant@example.com');

        $this->assertLacks($user, [
            'users.index', 'users.create', 'users.store', 'users.edit',
            'users.update', 'users.destroy', 'users.create-employee',
            'roles.index', 'roles.create', 'roles.store',
            'permissions.index', 'permissions.create',
            'configs.index', 'configs.create',
        ]);
    }

    // ─── Seller ──────────────────────────────────────────────────────────────

    public function test_seller_has_correct_permissions(): void
    {
        $user = $this->user('seller@example.com');

        $this->assertHas($user, [
            'home',
            'invoices.index', 'invoices.create', 'invoices.store', 'invoices.show',
            'invoices.edit', 'invoices.update', 'invoices.destroy', 'invoices.print',
            'invoices.search', 'invoices.get-items',
            'invoices.search-customer', 'invoices.search-product-service',
            'ancillary-costs.index', 'ancillary-costs.search-customer',
            'ancillary-costs.search-invoice', 'ancillary-costs.get-products',
            'invoices.ancillary-costs.create', 'invoices.ancillary-costs.store',
            'invoices.ancillary-costs.show', 'invoices.ancillary-costs.edit',
            'invoices.ancillary-costs.update', 'invoices.ancillary-costs.destroy',
            'customers.index', 'customers.create', 'customers.store',
            'customers.show', 'customers.edit', 'customers.update', 'customers.destroy',
            'customer-groups.index',
            'change-company', 'employee-portal.dashboard',
        ]);
    }

    public function test_seller_lacks_approval_and_admin_permissions(): void
    {
        $user = $this->user('seller@example.com');

        $this->assertLacks($user, [
            // approval/status management
            'invoices.approve', 'invoices.change-status',
            'invoices.conflicts', 'invoices.conflicts.more',
            'invoices.group-action', 'invoices.inactive', 'invoices.inactive.approve',
            'ancillary-costs.change-status',
            // accounting
            'documents.index', 'subjects.index', 'transactions.index',
            'bank-accounts.index', 'reports.ledger',
            // hr/salary
            'hr.employees.index', 'salary.payrolls.index',
            'attendance.attendance-logs.index',
            // management
            'users.index', 'roles.index', 'permissions.index', 'configs.index',
            'backups.create',
        ]);
    }

    // ─── Warehousekeeper ─────────────────────────────────────────────────────

    public function test_warehousekeeper_has_correct_permissions(): void
    {
        $user = $this->user('warehouse@example.com');

        $this->assertHas($user, [
            'home', 'home.cash-banks', 'home.bank-account',
            'products.index', 'products.create', 'products.store', 'products.show',
            'products.edit', 'products.update', 'products.destroy',
            'products.search-product-group',
            'product-groups.index', 'product-groups.create', 'product-groups.store',
            'product-groups.show', 'product-groups.edit', 'product-groups.update',
            'product-groups.destroy', 'change-company', 'employee-portal.dashboard',
        ]);
    }

    public function test_warehousekeeper_lacks_non_product_permissions(): void
    {
        $user = $this->user('warehouse@example.com');

        $this->assertLacks($user, [
            'invoices.index', 'documents.index', 'customers.index',
            'subjects.index', 'transactions.index', 'bank-accounts.index',
            'hr.employees.index', 'salary.payrolls.index',
            'attendance.attendance-logs.index', 'reports.ledger', 'services.create', 'service-groups.create',
            'users.index', 'roles.index', 'permissions.index', 'configs.index',
            'backups.create',
        ]);
    }

    public function test_warehousekeeper_role_only_has_product_service_and_home_permissions(): void
    {
        $role = Role::findByName('Warehousekeeper');
        $invalid = $role->permissions()
            ->where('name', 'NOT LIKE', 'products.%')
            ->where('name', 'NOT LIKE', 'product-groups.%')
            ->where('name', 'NOT LIKE', 'services.%')
            ->where('name', 'NOT LIKE', 'service-groups.%')
            ->where('name', 'NOT LIKE', 'home%')
            ->where('name', '!=', 'change-company')
            ->where('name', '!=', 'employee-portal.dashboard')
            ->count();

        $this->assertEquals(0, $invalid);
    }

    // ─── Employee ────────────────────────────────────────────────────────────

    public function test_employee_has_correct_permissions(): void
    {
        $user = $this->user('employee@example.com');

        $this->assertHas($user, [
            'home',
            'employee-portal.dashboard',
            'employee-portal.employee.show',
            'employee-portal.change-employee-information',
            'employee-portal.update-employee-information',
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
        ]);
    }

    public function test_employee_lacks_all_non_portal_permissions(): void
    {
        $user = $this->user('employee@example.com');

        $this->assertLacks($user, [
            'invoices.index', 'documents.index', 'products.index',
            'customers.index', 'subjects.index', 'transactions.index',
            'hr.employees.index', 'salary.payrolls.index',
            'attendance.attendance-logs.index', 'reports.ledger',
            'users.index', 'roles.index', 'permissions.index', 'configs.index',
            'backups.create',
        ]);
    }

    public function test_employee_role_only_has_portal_and_home_permissions(): void
    {
        $role = Role::findByName('Employee');
        $invalid = $role->permissions()
            ->where('name', 'NOT LIKE', 'employee-portal.%')
            ->where('name', '!=', 'home')
            ->where('name', '!=', 'change-company')
            ->count();

        $this->assertEquals(0, $invalid);
    }

    // ─── Multi-Role Users ────────────────────────────────────────────────────

    public function test_seller_warehousekeeper_has_combined_permissions(): void
    {
        $user = $this->user('seller-warehouse@example.com');

        // from Seller
        $this->assertHas($user, ['invoices.index', 'invoices.create', 'customers.index']);
        // from Warehousekeeper
        $this->assertHas($user, ['products.index', 'products.create']);
        // from Employee
        $this->assertHas($user, ['employee-portal.dashboard', 'employee-portal.payrolls']);

        // still denied
        $this->assertLacks($user, [
            'invoices.approve', 'invoices.change-status', 'services.index',
            'documents.index', 'users.index', 'hr.employees.index',
        ]);
    }

    public function test_accountant_seller_warehousekeeper_has_broad_permissions(): void
    {
        $user = $this->user('accountant-seller-warehouse@example.com');

        // from Accountant
        $this->assertHas($user, ['documents.index', 'invoices.approve', 'reports.ledger']);
        // from Seller
        $this->assertHas($user, ['invoices.create', 'customers.create']);
        // from Warehousekeeper
        $this->assertHas($user, ['products.create', 'services.create']);
        // from Employee
        $this->assertHas($user, ['employee-portal.dashboard']);

        // still denied (no management role)
        $this->assertLacks($user, ['users.index', 'roles.index', 'permissions.index', 'configs.index']);
    }

    // ─── User-Role Assignments ───────────────────────────────────────────────

    public function test_all_demo_users_have_correct_roles(): void
    {
        $map = [
            'admin@example.com' => ['Super-Admin', 'Employee'],
            'accountant@example.com' => ['Accountant', 'Employee'],
            'seller@example.com' => ['Seller', 'Employee'],
            'warehouse@example.com' => ['Warehousekeeper', 'Employee'],
            'seller-warehouse@example.com' => ['Seller', 'Warehousekeeper', 'Employee'],
            'accountant-seller-warehouse@example.com' => ['Accountant', 'Seller', 'Warehousekeeper', 'Employee'],
            'employee@example.com' => ['Employee'],
        ];

        foreach ($map as $email => $roles) {
            $user = $this->user($email);
            foreach ($roles as $role) {
                $this->assertTrue($user->hasRole($role), "$email missing role: $role");
            }
            $this->assertEquals(count($roles), $user->roles()->count(), "$email role count mismatch");
        }
    }
}
