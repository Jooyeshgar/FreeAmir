<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->unsignedBigInteger('buyId')->nullable();
            $table->unsignedBigInteger('sellId')->nullable();

            $table->foreign('buyId')->references('id')->on('subjects')->onDelete('set null');
            $table->foreign('sellId')->references('id')->on('subjects')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_groups');
    }
}
