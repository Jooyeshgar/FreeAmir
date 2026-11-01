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
        $ids = DB::table($tables['permissions'])->where('name', 'access-super-admin-panel')->pluck('id');

        DB::transaction(function () use ($tables, $permissionColumn, $ids): void {
            DB::table($tables['model_has_permissions'])->whereIn($permissionColumn, $ids)->delete();
            DB::table($tables['role_has_permissions'])->whereIn($permissionColumn, $ids)->delete();
            DB::table($tables['permissions'])->whereIn('id', $ids)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retired platform access must not be restored by a rollback.
    }
};
