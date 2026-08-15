<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
        $permissions = array_map(fn (string $permission): array => [
            'name' => $permission,
            'guard_name' => 'web',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $this->permissions());

        DB::table(config('permission.table_names.permissions'))->insertOrIgnore($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally irreversible: some or all permissions may have existed before this backfill, so deleting them would also remove role links.
    }

    private function permissions(): array
    {
        return [
            'cheques.*',
            'chequebooks.*',
        ];
    }
};
