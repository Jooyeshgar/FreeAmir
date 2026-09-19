<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table(config('permission.table_names.permissions'))->updateOrInsert(
            ['name' => 'commercial-ledgers.*', 'guard_name' => 'web'],
            ['updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table(config('permission.table_names.permissions'))->where('name', 'commercial-ledgers.*')->delete();
    }
};
