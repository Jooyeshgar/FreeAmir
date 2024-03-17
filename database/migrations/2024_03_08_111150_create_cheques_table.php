<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChequesTable extends Migration
{

    public function up()
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->float('amount')->notNullable(); 
            $table->date('write_date')->notNullable();
            $table->date('due_date')->notNullable();
            $table->string('serial', 50)->notNullable();
            $table->enum('status', [1, 2, 3, 4, 5])->notNullable(); 
            $table->unsignedBigInteger('customer_id')->notNullable();
            $table->unsignedBigInteger('account_id')->notNullable();
            $table->unsignedBigInteger('transaction_id')->notNullable(); 
            $table->unsignedBigInteger('notebook_id')->notNullable();
            $table->unsignedBigInteger('history_id')->notNullable();
            $table->unsignedBigInteger('bill_id')->notNullable();
            $table->string('desc', 200)->nullable();
            $table->integer('order')->notNullable();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('bank_accounts')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
            $table->foreign('notebook_id')->references('id')->on('notebooks')->onDelete('cascade');
            // $table->foreign('history_id')->references('id')->on('notebooks')->onDelete('cascade');
            $table->foreign('bill_id')->references('id')->on('bills')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cheques');
    }
}