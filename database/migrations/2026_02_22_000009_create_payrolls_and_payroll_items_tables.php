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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('decree_id')->nullable();
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('month')->unsigned();
            $table->decimal('total_earnings', 18, 2)->default(0.00);
            $table->decimal('total_deductions', 18, 2)->default(0.00);
            $table->decimal('net_payment', 18, 2)->default(0.00);
            $table->decimal('employer_insurance', 18, 2)->default(0.00)
                ->comment('Employer share of social insurance (not in net, but stored for reporting)');
            $table->dateTime('issue_date')->useCurrent();
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->unsignedInteger('accounting_voucher_id')->nullable()->comment('FK to external accounting module');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'year', 'month'], 'uq_payroll_employee_month');
            $table->index(['year', 'month'], 'idx_payrolls_period');

            $table->foreign('employee_id')
                ->references('id')->on('employees');

            $table->foreign('decree_id')
                ->references('id')->on('salary_decrees')
                ->nullOnDelete();
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('payroll_id');
            $table->unsignedInteger('element_id');
            $table->decimal('calculated_amount', 18, 2);
            $table->decimal('unit_count', 8, 2)->nullable()->comment('e.g. overtime hours, absent days — for traceability');
            $table->decimal('unit_rate', 18, 2)->nullable()->comment('Rate applied per unit — for traceability');
            $table->string('description', 300)->nullable();
            $table->timestamps();

            $table->foreign('payroll_id')
                ->references('id')->on('payrolls')
                ->cascadeOnDelete();

            $table->foreign('element_id')
                ->references('id')->on('payroll_elements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
    }
};
