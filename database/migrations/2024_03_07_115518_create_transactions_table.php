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
            $table->id('Id');  // Use 'Id' as primary key
            $table->integer('Code');
            $table->date('Date');
            $table->unsignedBigInteger('Bill')->nullable();
            $table->unsignedBigInteger('Cust')->nullable();
            $table->float('Addition');
            $table->float('Subtraction');
            $table->float('Tax');
            $table->float('PayableAmnt');
            $table->float('CashPayment');
            $table->date('ShipDate')->nullable();
            $table->string('FOB', 50)->nullable();
            $table->string('ShipVia', 100)->nullable();
            $table->boolean('Permanent')->nullable();
            $table->string('Desc', 200)->nullable();
            $table->boolean('Sell');
            $table->date('LastEdit')->nullable();
            $table->boolean('Acivated');

            $table->primary(['Id']);
            $table->foreign('Cust')->references('custId')->on('customers')->onDelete('set null');
            $table->check('Permanent IN (0, 1)');
            $table->check('Sell IN (0, 1)');
            $table->check('Acivated IN (0, 1)');
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
