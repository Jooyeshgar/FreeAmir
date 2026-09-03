<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $permissionTable = $tables['permissions'];
        $rolePermissionTable = $tables['role_has_permissions'];
        $permissionColumn = config('permission.column_names.permission_pivot_key') ?? 'permission_id';
        $roleColumn = config('permission.column_names.role_pivot_key') ?? 'role_id';
        $timestamp = now();

        DB::table($permissionTable)->insertOrIgnore([
            'name' => 'invoices.dashboard',
            'guard_name' => 'web',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $dashboardPermissionId = DB::table($permissionTable)
            ->where('name', 'invoices.dashboard')
            ->where('guard_name', 'web')
            ->value('id');
        $invoicePermissionIds = DB::table($permissionTable)
            ->whereIn('name', ['invoices.index', 'invoices.*'])
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table($rolePermissionTable)
            ->whereIn($permissionColumn, $invoicePermissionIds)
            ->pluck($roleColumn)
            ->unique();

        foreach ($roleIds as $roleId) {
            DB::table($rolePermissionTable)->insertOrIgnore([
                $permissionColumn => $dashboardPermissionId,
                $roleColumn => $roleId,
            ]);
        }

        $defaultRoleIds = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->whereIn('name', ['Super-Admin', __('Admin'), __('Accountant'), __('Seller')])
            ->pluck('id');

        foreach ($defaultRoleIds as $roleId) {
            DB::table($rolePermissionTable)->insertOrIgnore([
                $permissionColumn => $dashboardPermissionId,
                $roleColumn => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep the permission and role assignments because they may have been customized after deployment.
    }
};
