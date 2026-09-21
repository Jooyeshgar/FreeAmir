<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $permissionColumn = config('permission.column_names.permission_pivot_key') ?? 'permission_id';
        $roleColumn = config('permission.column_names.role_pivot_key') ?? 'role_id';
        $permissionName = 'companies.closing-wizard.recalculate';

        DB::table($tables['permissions'])->insertOrIgnore([
            'name' => $permissionName,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table($tables['permissions'])
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->value('id');
        $closingPermissionIds = DB::table($tables['permissions'])
            ->whereIn('name', ['companies.closing-wizard.step3', 'companies.*'])
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table($tables['role_has_permissions'])
            ->whereIn($permissionColumn, $closingPermissionIds)
            ->pluck($roleColumn)
            ->unique();

        foreach ($roleIds as $roleId) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([
                $permissionColumn => $permissionId,
                $roleColumn => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Keep the permission because deployed role assignments may have been customized.
    }
};
