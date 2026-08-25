<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['activity-logs.index', 'activity-logs.details'] as $permission) {
            DB::table(config('permission.table_names.permissions'))->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))
            ->whereIn('name', ['activity-logs.index', 'activity-logs.details'])
            ->delete();
    }
};
