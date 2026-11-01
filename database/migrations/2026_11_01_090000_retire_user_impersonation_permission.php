<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $permissionKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';

        DB::transaction(function () use ($tables, $permissionKey): void {
            $permissionIds = DB::table($tables['permissions'])->where('name', 'users.impersonate')->pluck('id');

            DB::table($tables['role_has_permissions'])->whereIn($permissionKey, $permissionIds)->delete();
            DB::table($tables['model_has_permissions'])->whereIn($permissionKey, $permissionIds)->delete();
            DB::table($tables['permissions'])->whereIn('id', $permissionIds)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retired permissions and their old grants must not be restored.
    }
};
