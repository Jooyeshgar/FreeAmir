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
        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->renameColumn('mission_days', 'mission');
            $table->renameColumn('paid_leave_days', 'paid_leave');
            $table->renameColumn('unpaid_leave_days', 'unpaid_leave');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('leave_remain')->default(0)->after('device_id')
                ->comment('Remaining annual leave balance (days)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->renameColumn('mission', 'mission_days');
            $table->renameColumn('paid_leave', 'paid_leave_days');
            $table->renameColumn('unpaid_leave', 'unpaid_leave_days');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('leave_remain');
        });
    }
};
