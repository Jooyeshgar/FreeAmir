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
            $table->unsignedSmallInteger('undertime')->default(0);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedSmallInteger('leave_remain')->default(0)->after('device_id')
                ->comment('Remaining annual leave balance (days)');
        });

        Schema::table('payroll_elements', function (Blueprint $table) {
            $table->enum('system_code', [
                'CHILD_ALLOWANCE',
                'HOUSING_ALLOWANCE',
                'FOOD_ALLOWANCE',
                'MARRIAGE_ALLOWANCE',
                'OVERTIME',
                'UNDERTIME',
                'FRIDAY_PAY',
                'HOLIDAY_PAY',
                'MISSION_PAY',
                'INSURANCE_EMP',
                'INSURANCE_EMP2',
                'UNEMPLOYMENT_INS',
                'INCOME_TAX',
                'ABSENCE_DEDUCTION',
                'OTHER',
            ])->default('OTHER')->change();
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
            $table->dropColumn('early_leave');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('leave_remain');
        });
    }
};
