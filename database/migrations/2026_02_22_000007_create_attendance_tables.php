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
        Schema::create('monthly_attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('employee_id');
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('month')->unsigned()->comment('1-12');
            $table->tinyInteger('work_days')->unsigned()->default(30);
            $table->tinyInteger('present_days')->unsigned()->default(0);
            $table->tinyInteger('absent_days')->unsigned()->default(0);
            $table->unsignedSmallInteger('overtime')->default(0);
            $table->tinyInteger('mission_days')->unsigned()->default(0);
            $table->tinyInteger('paid_leave_days')->unsigned()->default(0);
            $table->tinyInteger('unpaid_leave_days')->unsigned()->default(0);
            $table->unsignedSmallInteger('friday')->default(0);
            $table->unsignedSmallInteger('holiday')->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'year', 'month'], 'uq_monthly_attendance');
            $table->index(['year', 'month'], 'idx_ma_year_month');
            $table->foreign('employee_id')
                ->references('id')->on('employees')
                ->cascadeOnDelete();
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('monthly_attendance_id')->nullable()->comment('NULL = هنوز محاسبه نشده');
            $table->date('log_date');
            $table->time('entry_time')->nullable();
            $table->time('exit_time')->nullable();
            $table->unsignedSmallInteger('worked')->default(0);
            $table->unsignedSmallInteger('delay')->default(0);
            $table->unsignedSmallInteger('early_leave')->default(0);
            $table->unsignedSmallInteger('overtime')->default(0);
            $table->unsignedSmallInteger('mission')->default(0);
            $table->unsignedSmallInteger('paid_leave')->default(0);
            $table->unsignedSmallInteger('unpaid_leave')->default(0);
            $table->boolean('is_friday')->default(false);
            $table->boolean('is_holiday')->default(false);
            $table->boolean('is_manual')->default(false)->comment('Manually corrected by operator');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('log_date', 'idx_logs_date');
            $table->foreign('employee_id')
                ->references('id')->on('employees')
                ->cascadeOnDelete();
            $table->foreign('monthly_attendance_id')
                ->references('id')->on('monthly_attendances')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('monthly_attendances');
    }
};
