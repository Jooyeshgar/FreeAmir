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

        DB::table($tables['permissions'])->insertOrIgnore([
            'name' => 'invoices.sort-numbers',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table($tables['permissions'])
            ->where('name', 'invoices.sort-numbers')->where('guard_name', 'web')->value('id');
        // Preserve custom read-only roles; inherit existing sorting or full invoice access.
        $existingIds = DB::table($tables['permissions'])
            ->whereIn('name', ['documents.sort-numbers', 'invoices.*'])
            ->where('guard_name', 'web')->pluck('id');
        $roleIds = DB::table($tables['role_has_permissions'])
            ->whereIn($permissionColumn, $existingIds)->pluck($roleColumn);
        $defaultRoleIds = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->whereIn('name', ['Super-Admin', __('Admin'), __('Accountant'), __('Seller')])
            ->pluck('id');

        foreach ($roleIds->merge($defaultRoleIds)->unique() as $roleId) {
            DB::table($tables['role_has_permissions'])->insertOrIgnore([
                $permissionColumn => $permissionId,
                $roleColumn => $roleId,
            ]);
        }

        $directAssignments = DB::table($tables['model_has_permissions'])
            ->whereIn($permissionColumn, $existingIds)->get();

        foreach ($directAssignments as $assignment) {
            $attributes = (array) $assignment;
            $attributes[$permissionColumn] = $permissionId;
            DB::table($tables['model_has_permissions'])->insertOrIgnore($attributes);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve permissions and assignments that may have been customized after deployment.
    }
};
