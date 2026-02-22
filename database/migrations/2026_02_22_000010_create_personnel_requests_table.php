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
        Schema::create('personnel_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('employee_id');

            $table->enum('request_type', [
                'LEAVE_HOURLY',
                'LEAVE_DAILY',
                'SICK_LEAVE',
                'LEAVE_WITHOUT_PAY',
                'MISSION_HOURLY',
                'MISSION_DAILY',
                'OVERTIME_ORDER',
                'REMOTE_WORK',
                'OTHER',
            ]);

            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->unsignedInteger('duration_minutes')->default(0);

            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedInteger('approved_by')->nullable()->comment('FK به employees');

            $table->unsignedInteger('payroll_id')->nullable()->comment('NULL = هنوز در فیش حساب نشده');

            $table->timestamps();

            $table->foreign('employee_id')
                ->references('id')->on('employees');

            $table->foreign('approved_by')
                ->references('id')->on('employees')
                ->nullOnDelete();

            $table->foreign('payroll_id')
                ->references('id')->on('payrolls')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personnel_requests');
    }
};
