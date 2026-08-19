<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->where('name', 'send-to-email')->delete();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'report', 'guard_name' => 'web'],
            ['updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'report')->delete();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'send-to-email', 'guard_name' => 'web'],
            ['updated_at' => now(), 'created_at' => now()]
        );
    }
};
