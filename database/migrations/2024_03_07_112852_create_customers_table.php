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
            $table->string('phone', 15)->default('')->nullable();
            $table->string('cell', 15)->default('')->nullable();
            $table->string('fax', 15)->default('')->nullable();
            $table->string('address', 100)->default('')->nullable();
            $table->string('postal_code', 15)->default('')->nullable();
            $table->string('email', 64)->unique()->default('')->nullable(); // Unique email address
            $table->string('ecnmcs_code', 20)->default('')->nullable();
            $table->string('personal_code', 15)->default('')->nullable();
            $table->string('web_page', 50)->default('')->nullable();
            $table->string('responsible', 50)->default('')->nullable();
            $table->string('connector', 50)->default('')->nullable();
            $table->unsignedBigInteger('group_id')->nullable(); // Foreign key to groups table
            $table->text('desc')->default('')->nullable();
            $table->decimal('balance', 10, 2)->default(0)->nullable();
            $table->decimal('credit', 10, 2)->default(0)->nullable();
            $table->boolean('rep_via_email')->default(false)->nullable(); // Boolean field for receiving updates via email
            $table->string('acc_name_1', 50)->default('')->nullable();
            $table->string('acc_no_1', 30)->default('')->nullable();
            $table->string('acc_bank_1', 50)->default('')->nullable();
            $table->string('acc_name_2', 50)->default('')->nullable();
            $table->string('acc_no_2', 30)->default('')->nullable();
            $table->string('acc_bank_2', 50)->default('')->nullable();
            $table->boolean('type_buyer')->default(false);
            $table->boolean('type_seller')->default(false);
            $table->boolean('type_mate')->default(false);
            $table->boolean('type_agent')->default(false);
            $table->unsignedBigInteger('introducer_id')->nullable(); // Foreign key to customers table (self-referencing)
            $table->string('commission', 15)->default(0);
            $table->boolean('marked')->default(false);
            $table->string('reason', 200)->default('')->nullable();
            $table->string('disc_rate', 15)->default(0);

            $table->timestamps(); // Enable timestamps for created_at and updated_at

            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null'); // Allow null for subj_id
            $table->foreign('group_id')->references('id')->on('customer_groups')->onDelete('set null'); // Allow null for group_id
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
