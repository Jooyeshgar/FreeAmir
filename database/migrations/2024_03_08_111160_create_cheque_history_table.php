<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cheque_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cheque_id');
            $table->decimal('amount', 10, 2);
            $table->date('write_date'); // Assuming WrtDate refers to "writing date"
            $table->date('due_date');
            $table->string('serial', 50);
            $table->enum('status', [1, 2, 3, 4, 5]); // Assuming these represent status values
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('transaction_id');
            $table->text('desc')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->foreign('cheque_id')->references('id')->on('cheques')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('bank_accounts')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cheque_histories');
    }
};