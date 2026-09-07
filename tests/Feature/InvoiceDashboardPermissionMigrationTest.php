<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceDashboardPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_migration_grants_dashboard_to_existing_invoice_roles_and_default_roles(): void
    {
        $invoicePermission = Permission::create(['name' => 'invoices.index']);
        $customInvoiceRole = Role::create(['name' => 'Invoice Analyst']);
        $customInvoiceRole->givePermissionTo($invoicePermission);
        $seller = Role::create(['name' => __('Seller')]);

        $migration = require database_path('migrations/2026_09_03_120000_add_invoice_dashboard_permission.php');
        $migration->up();

        $dashboardPermission = Permission::findByName('invoices.dashboard');
        $rolePermissionTable = config('permission.table_names.role_has_permissions');

        $this->assertTrue(DB::table($rolePermissionTable)
            ->where('role_id', $customInvoiceRole->id)
            ->where('permission_id', $dashboardPermission->id)
            ->exists());
        $this->assertTrue(DB::table($rolePermissionTable)
            ->where('role_id', $seller->id)
            ->where('permission_id', $dashboardPermission->id)
            ->exists());
    }
}
