<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table(config('permission.table_names.permissions'))->insertOrIgnore([
            'name' => 'products.recalculate-quantity',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void {}
};
