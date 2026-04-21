<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('remote_work')->default(0)->after('paid_leave')
                ->comment('Remote-work minutes approved for this day');
        });

        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('remote_work')->default(0)->after('paid_leave')
                ->comment('Total remote-work minutes in the period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn('remote_work');
        });

        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->dropColumn('remote_work');
        });
    }
};
