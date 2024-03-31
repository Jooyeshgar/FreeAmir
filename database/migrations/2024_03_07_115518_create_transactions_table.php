<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('code');
            $table->date('date');
            $table->unsignedBigInteger('bill')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable(); // Match model's foreign key
            $table->float('addition');
            $table->float('subtraction');
            $table->float('tax');
            $table->float('payable_amount');
            $table->float('cash_payment');
            $table->string('destination', 50)->nullable();
            $table->date('ship_date')->nullable();
            $table->string('ship_via', 100)->nullable();
            $table->boolean('permanent')->nullable();
            $table->string('description', 200)->nullable();
            $table->boolean('sell');
            $table->boolean('activated');
            $table->timestamps();

            $table->foreign('customer_id')->references('Id')->on('customers')->onDelete('set null');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
