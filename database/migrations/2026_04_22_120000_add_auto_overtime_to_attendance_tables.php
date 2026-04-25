<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('auto_overtime')->default(0)->after('overtime');
        });

        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('auto_overtime')->default(0)->after('overtime');
        });

        Schema::table('payroll_elements', function (Blueprint $table) {
            $table->enum('system_code', [
                'CHILD_ALLOWANCE',
                'HOUSING_ALLOWANCE',
                'FOOD_ALLOWANCE',
                'MARRIAGE_ALLOWANCE',
                'OVERTIME',
                'AUTO_OVERTIME',
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

    public function down(): void
    {
        Schema::table('monthly_attendances', function (Blueprint $table) {
            $table->dropColumn('auto_overtime');
        });
    }
};
