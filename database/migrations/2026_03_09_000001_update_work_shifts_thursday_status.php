<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn('crosses_midnight');
            $table->enum('thursday_status', ['holiday', 'full_day', 'half_day'])->default('half_day')->after('break');
            $table->time('thursday_exit_time')->nullable()->after('thursday_status');
        });
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn('thursday_exit_time');
            $table->dropColumn('thursday_status');
            $table->boolean('crosses_midnight')->default(false);
        });
    }
};
