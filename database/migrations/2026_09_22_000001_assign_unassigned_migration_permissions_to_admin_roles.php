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

        $roleIds = DB::table($tables['roles'])
            ->where('guard_name', 'web')
            ->whereIn('name', ['Super-Admin', __('Admin')])
            ->pluck('id');

        $permissionIds = DB::table($tables['permissions'])
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'cheques.*',
                'chequebooks.*',
                'budgets.*',
                'report',
                'products.recalculate-quantity',
                'activity-logs.index',
                'activity-logs.details',
                'commercial-ledgers.index',
                'commercial-ledgers.store',
                'commercial-ledgers.show',
                'commercial-ledgers.download',
                'commercial-ledgers.destroy',
                'commercial-ledgers.*',
                'reports.inventory-turnover',
                'reports.inventory-turnover.pdf',
            ])
            ->pluck('id');

        DB::transaction(function () use ($tables, $roleIds, $permissionIds, $permissionColumn, $roleColumn) {
            foreach ($roleIds as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    DB::table($tables['role_has_permissions'])->insertOrIgnore([
                        $permissionColumn => $permissionId,
                        $roleColumn => $roleId,
                    ]);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Preserve assignments that may have existed before this backfill or been customized afterward.
    }
};
