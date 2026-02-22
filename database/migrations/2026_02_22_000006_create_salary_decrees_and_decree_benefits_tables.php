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
        Schema::create('salary_decrees', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('org_chart_id');
            $table->string('name', 200)->nullable()->comment('Decree name or number');
            $table->date('start_date');
            $table->date('end_date')->nullable()->comment('NULL = currently active');
            $table->enum('contract_type', ['full_time', 'part_time', 'hourly', 'shift'])->nullable();

            // Base financials — snapshotted at decree issuance for audit integrity
            $table->decimal('daily_wage', 18, 2)->nullable()->comment('مزد روزانه (base_salary / 30)');

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['start_date', 'end_date'], 'idx_decrees_dates');

            $table->foreign('employee_id')
                ->references('id')->on('employees');

            $table->foreign('org_chart_id')
                ->references('id')->on('org_charts');
        });

        Schema::create('decree_benefits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('decree_id');
            $table->unsignedInteger('element_id');
            $table->decimal('element_value', 18, 2)->nullable()->comment('Override amount or percentage');
            $table->timestamps();

            $table->unique(['decree_id', 'element_id'], 'uq_decree_element');

            $table->foreign('decree_id')
                ->references('id')->on('salary_decrees')
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
        Schema::dropIfExists('decree_benefits');
        Schema::dropIfExists('salary_decrees');
    }
};
