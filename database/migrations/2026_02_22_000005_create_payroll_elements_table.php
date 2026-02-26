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
        Schema::create('payroll_elements', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title', 100);
            $table->enum('system_code', [
                'CHILD_ALLOWANCE',
                'HOUSING_ALLOWANCE',
                'FOOD_ALLOWANCE',
                'MARRIAGE_ALLOWANCE',
                'OVERTIME',
                'FRIDAY_PAY',
                'HOLIDAY_PAY',
                'MISSION_PAY',
                'INSURANCE_EMP',
                'INSURANCE_EMP2',
                'UNEMPLOYMENT_INS',
                'INCOME_TAX',
                'ABSENCE_DEDUCTION',
                'OTHER',
            ])->default('OTHER');
            $table->enum('category', ['earning', 'deduction']);
            $table->enum('calc_type', ['fixed', 'formula', 'percentage']);
            $table->string('formula', 500)->nullable();
            $table->decimal('default_amount', 18, 2)->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_insurable')->default(false);
            $table->boolean('show_in_payslip')->default(true);
            $table->boolean('is_system_locked')->default(false)->comment('Cannot be deleted if 1');
            $table->string('gl_account_code', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_elements');
    }
};
