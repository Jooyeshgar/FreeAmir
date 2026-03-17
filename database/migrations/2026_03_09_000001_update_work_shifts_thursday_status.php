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
            $table->dropColumn('float_after');
            $table->renameColumn('float_before', 'float');

            $table->enum('thursday_status', ['holiday', 'full_day', 'half_day'])->default('half_day')->after('break');
            $table->time('thursday_exit_time')->nullable()->after('thursday_status');
            $table->decimal('holiday_coefficient', 4, 2)->default(1.40)->after('thursday_exit_time');
            $table->decimal('overtime_coefficient', 4, 2)->default(1.40)->after('holiday_coefficient');
        });
    }

    public function down(): void
    {
        Schema::table('work_shifts', function (Blueprint $table) {
            $table->dropColumn('overtime_coefficient');
            $table->dropColumn('holiday_coefficient');
            $table->dropColumn('thursday_exit_time');
            $table->dropColumn('thursday_status');

            $table->renameColumn('float', 'float_before');
            $table->unsignedSmallInteger('float_after')->default(0);

            $table->boolean('crosses_midnight')->default(false);
        });
    }
};
