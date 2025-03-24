<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 60);
            $table->unsignedBigInteger('group')->nullable();
            $table->string('location', 50)->nullable();
            $table->float('quantity');
            $table->float('quantity_warning')->nullable();
            $table->boolean('oversell')->default(false);
            $table->decimal('purchace_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->string('discount_formula', 100)->nullable();
            $table->string('description', 200)->nullable();

            $table->foreign('group')->references('id')->on('product_groups')->onDelete('set null');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->unique(['company_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}
