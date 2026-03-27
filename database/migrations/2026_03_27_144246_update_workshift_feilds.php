<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->decimal('mission_coefficient', 4, 2)->default(1.40)->after('holiday_coefficient');
            $table->decimal('undertime_coefficient', 4, 2)->default(2.0)->after('mission_coefficient');
        });
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn('mission_coefficient');
            $table->dropColumn('undertime_coefficient');
        });
    }
};
