<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBankNamesTable extends Migration
{
    public function up()
    {
        Schema::create('bank_names', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->nullable(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bank_names');
    }
}