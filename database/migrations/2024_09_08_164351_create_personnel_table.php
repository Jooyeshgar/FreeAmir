<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('personnel', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('personnel_code')->unique();
            $table->string('father_name');
            $table->enum('nationality', ['iranian', 'non_iranian']);
            $table->string('identity_number');
            $table->string('national_code');
            $table->string('passport_number');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed']);
            $table->enum('gender', ['female', 'male', 'other']);
            $table->string('contact_number');
            $table->string('address');
            $table->string('insurance_number');
            $table->enum('insurance_type', ['social_security', 'other']);
            $table->integer('children_count')->nullable();
            $table->unsignedBigInteger('bank_id'); // Foreign key to Bank
            $table->string('account_number');
            $table->string('card_number');
            $table->string('iban');
            $table->string('detailed_code');
            $table->date('contract_start_date')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract']);
            $table->enum('contract_type', ['official', 'contract', 'other']);
            $table->string('birth_place')->nullable();
            $table->unsignedBigInteger('organizational_chart_id'); // Foreign key to OrganizationalChart
            $table->enum('military_status', ['not_subject', 'completed', 'in_progress']);
            $table->unsignedBigInteger('workhouse_id'); // Foreign key to Workhouse
            $table->timestamps();

            // Add foreign key constraints
            $table->foreign('bank_id')->references('id')->on('banks');
            $table->foreign('organizational_chart_id')->references('id')->on('organizational_charts');
            $table->foreign('workhouse_id')->references('id')->on('workhouses');
        });
    }

    public function down()
    {
        Schema::dropIfExists('personnel');
    }

};
