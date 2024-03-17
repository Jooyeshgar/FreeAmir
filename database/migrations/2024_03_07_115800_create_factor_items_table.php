<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFactorItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('factor_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factor_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->string('unit_discount', 30);
            $table->decimal('vat', 10, 2);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('factor_id')->references('id')->on('factors')->onDelete('set null'); // Assuming factors model is named FactorTable
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null'); // Assuming factors model is named FactorTable
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('factor_items');
    }
}
