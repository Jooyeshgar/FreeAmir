<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentTable extends Migration
{
    public function up()
    {
        // Create the bill table
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->nullable();
            $table->date('date')->nullable();
            $table->boolean('permanent')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('documents');
    }
}