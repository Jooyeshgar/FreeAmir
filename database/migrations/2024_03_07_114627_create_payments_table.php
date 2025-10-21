<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->date('due_date');
            $table->string('bank', 100)->nullable();
            $table->string('serial', 50)->nullable();
            $table->decimal('amount', 10, 2); // Use decimal for monetary value
            $table->unsignedBigInteger('payer_id')->nullable();
            $table->date('write_date')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->string('track_code')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->text('payer_name')->nullable();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('payer_id')->references('id')->on('customers')->onDelete('set null');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
