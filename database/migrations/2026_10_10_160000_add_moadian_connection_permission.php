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
            'name' => 'companies.test-moadian-connection',
            'guard_name' => 'web',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $connectionPermissionId = DB::table($permissionTable)
            ->where('name', 'companies.test-moadian-connection')
            ->where('guard_name', 'web')
            ->value('id');
        $companyPermissionIds = DB::table($permissionTable)
            ->whereIn('name', ['companies.edit', 'companies.*'])
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table($rolePermissionTable)
            ->whereIn($permissionColumn, $companyPermissionIds)
            ->pluck($roleColumn)
            ->unique();

        foreach ($roleIds as $roleId) {
            DB::table($rolePermissionTable)->insertOrIgnore([
                $permissionColumn => $connectionPermissionId,
                $roleColumn => $roleId,
            ]);
        }

        $defaultRoleIds = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->whereIn('name', ['Super-Admin', __('Admin'), __('Accountant')])
            ->pluck('id');

        foreach ($defaultRoleIds as $roleId) {
            DB::table($rolePermissionTable)->insertOrIgnore([
                $permissionColumn => $connectionPermissionId,
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
