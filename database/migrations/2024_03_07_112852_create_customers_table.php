<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id(); // Use auto-incrementing primary key

            $table->string('code', 15)->unique(); // Unique customer code
            $table->string('name', 100);
            $table->unsignedBigInteger('subject_id')->nullable(); // Foreign key to subjects table
            $table->string('phone', 15);
            $table->string('cell', 15);
            $table->string('fax', 15);
            $table->string('address', 100);
            $table->string('postal_code', 15);
            $table->string('email', 15)->unique(); // Unique email address
            $table->string('ecnmcs_code', 20);
            $table->string('personal_code', 15);
            $table->string('web_page', 50);
            $table->string('responsible', 50);
            $table->string('connector', 50);
            $table->unsignedBigInteger('group_id')->nullable(); // Foreign key to groups table
            $table->text('desc');
            $table->decimal('balance', 10, 2);
            $table->decimal('credit', 10, 2);
            $table->boolean('rep_via_email'); // Boolean field for receiving updates via email
            $table->string('acc_name_1', 50);
            $table->string('acc_no_1', 30);
            $table->string('acc_bank_1', 50);
            $table->string('acc_name_2', 50);
            $table->string('acc_no_2', 30);
            $table->string('acc_bank_2', 50);
            $table->boolean('type_buyer');
            $table->boolean('type_seller');
            $table->boolean('type_mate');
            $table->boolean('type_agent');
            $table->unsignedBigInteger('introducer_id')->nullable(); // Foreign key to customers table (self-referencing)
            $table->string('commission', 15);
            $table->boolean('marked');
            $table->string('reason', 200);
            $table->string('disc_rate', 15);

            $table->timestamps(); // Enable timestamps for created_at and updated_at

            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null'); // Allow null for subj_id
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null'); // Allow null for group_id
            $table->foreign('introducer_id')->references('id')->on('customers')->onDelete('set null'); // Allow null for introducer_id

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customers');
    }
}
