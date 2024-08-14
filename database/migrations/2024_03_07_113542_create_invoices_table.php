<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('date');
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->decimal('addition', 16, 2);
            $table->decimal('subtraction', 16, 2);
            $table->decimal('vat', 16, 2);
            $table->decimal('cash_payment', 16, 2);
            $table->date('ship_date')->nullable();
            $table->string('ship_via', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_sell');
            $table->boolean('active')->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();

            $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('document_id')->references('id')->on('documents')->onDelete('set null'); // Assuming foreign key reference
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');

            // Remove redundant checks for boolean fields
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
}
