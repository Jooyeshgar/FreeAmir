<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->decimal('auto_overtime_coefficient', 4, 2)->default(1)->after('overtime_coefficient');
            $table->unsignedSmallInteger('max_auto_overtime')->default(120)->after('auto_overtime_coefficient');
        });
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn('auto_overtime_coefficient');
            $table->dropColumn('max_auto_overtime');
        });
    }
};
