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
        Schema::create('employees', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique()->comment('Personnel code');

            // Identity
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('father_name', 100)->nullable();
            $table->char('national_code', 10)->nullable()->unique();
            $table->string('passport_number', 20)->nullable();

            $table->enum('nationality', ['iranian', 'foreign'])->default('iranian');
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->tinyInteger('children_count')->unsigned()->default(0);
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->enum('duty_status', ['liable', 'completed', 'in_progress', 'exempt'])->nullable();

            // Contact
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();

            // Insurance
            $table->string('insurance_number', 20)->nullable();
            $table->enum('insurance_type', ['social_security', 'other'])->nullable();

            // Banking
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 50)->nullable();
            $table->string('card_number', 20)->nullable();
            $table->string('shaba_number', 30)->nullable();

            // Education
            $table->enum('education_level', ['below_diploma', 'diploma', 'associate', 'bachelor', 'master', 'phd'])->nullable();
            $table->string('field_of_study', 100)->nullable();

            // Employment
            $table->enum('employment_type', ['permanent', 'contract', 'other'])->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();

            // Org Relations
            $table->unsignedInteger('org_chart_id')->nullable();
            $table->unsignedInteger('work_site_id');
            $table->unsignedInteger('contract_framework_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('org_chart_id')
                ->references('id')->on('org_charts')
                ->nullOnDelete();

            $table->foreign('work_site_id')
                ->references('id')->on('work_sites');

            $table->foreign('contract_framework_id')
                ->references('id')->on('work_site_contracts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
