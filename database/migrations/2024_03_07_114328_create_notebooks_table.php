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
        Schema::create('notebooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('desc')->nullable();
            $table->integer('value');
            $table->timestamps();

            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
            $table->foreign('bill_id')->references('id')->on('bills')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notebook');
    }
};