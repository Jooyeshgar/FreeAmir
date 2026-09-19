<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['reports.inventory-turnover', 'reports.inventory-turnover.pdf'] as $permission) {
            DB::table(config('permission.table_names.permissions'))->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        foreach (['reports.inventory-turnover', 'reports.inventory-turnover.pdf'] as $permission) {
            DB::table(config('permission.table_names.permissions'))->where('name', $permission)->delete();
        }
    }
};
