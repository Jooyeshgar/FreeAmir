<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configs')->where('key', 'cash')->delete();
    }

    public function down(): void
    {
        // Removed values cannot be restored because they differed by company.
    }
};
