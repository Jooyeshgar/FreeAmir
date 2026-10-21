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
        $existingIds = DB::table($tables['permissions'])->where('guard_name', 'web')->whereIn('name', ['invoices.payments.store', 'invoices.payments.*'])->pluck('id');

        $roleIds = DB::table($tables['role_has_permissions'])->whereIn($permissionColumn, $existingIds)->pluck($roleColumn)->unique();

        $userPermissions = DB::table($tables['model_has_permissions'])->whereIn($permissionColumn, $existingIds)->get();

        DB::table($tables['permissions'])->insertOrIgnore([
            'name' => 'invoices.payments.update', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $id = DB::table($tables['permissions'])->where('name', 'invoices.payments.update')->where('guard_name', 'web')->value('id');

        foreach ($roleIds as $roleId) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([
                $permissionColumn => $id, $roleColumn => $roleId,
            ]);
        }

        foreach ($userPermissions as $assignment) {
            $attributes = (array) $assignment;
            $attributes[$permissionColumn] = $id;
            DB::table($tables['model_has_permissions'])->insertOrIgnore($attributes);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve permissions and assignments that may have been customized.
    }
};
