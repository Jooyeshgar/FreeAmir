<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
            'name' => 'budgets.*',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally irreversible: a permission may have existed before this migration, so deleting it could remove valid role links.
    }
};
