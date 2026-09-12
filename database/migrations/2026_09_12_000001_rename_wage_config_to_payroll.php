<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configs')->where('key', 'wage')->update([
            'key' => 'payroll',
            'desc' => 'حقوق و دستمزد',
        ]);
    }

    public function down(): void
    {
        DB::table('configs')->where('key', 'payroll')->update([
            'key' => 'wage',
            'desc' => 'حقوق پرسنل',
        ]);
    }
};
