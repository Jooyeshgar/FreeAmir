<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'budgets.index',
        'budgets.store',
        'budgets.rollover',
        'budgets.calculate-expenses',
        'budgets.destroy',
        'budgets.search-subjects',
    ];

    public function up(): void
    {
        $timestamp = now();
        $permissions = array_map(fn (string $permission): array => [
            'name' => $permission,
            'guard_name' => 'web',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], self::PERMISSIONS);

        DB::table(config('permission.table_names.permissions'))->insertOrIgnore($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally irreversible: a permission may have existed before this migration, so deleting it could remove valid role links.
    }
};
