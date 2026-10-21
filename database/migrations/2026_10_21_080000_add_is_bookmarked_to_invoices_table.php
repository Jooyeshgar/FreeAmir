<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_bookmarked')->default(false);
        });

        $tables = config('permission.table_names');
        $permissionColumn = config('permission.column_names.permission_pivot_key') ?? 'permission_id';
        $roleColumn = config('permission.column_names.role_pivot_key') ?? 'role_id';
        $permissionName = 'invoices.bookmark';

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
        $invoicePermissionIds = DB::table($tables['permissions'])
            ->whereIn('name', ['invoices.index', 'invoices.show', 'invoices.*'])
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table($tables['role_has_permissions'])
            ->whereIn($permissionColumn, $invoicePermissionIds)
            ->pluck($roleColumn)
            ->merge(DB::table($tables['roles'])
                ->where('guard_name', 'web')
                ->whereIn('name', ['Super-Admin', __('Admin'), __('Accountant'), __('Seller')])
                ->pluck('id'))
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
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_bookmarked');
        });
    }
};
