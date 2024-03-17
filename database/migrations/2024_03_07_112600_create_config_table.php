<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('configs', function (Blueprint $table) {
            $table->string('key')->primary(); // Set key as primary key (assuming unique)
            $table->text('value');
            $table->text('desc')->nullable(); // Allow description to be null
            $table->string('type');
            $table->string('category');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('configs');
    }
};