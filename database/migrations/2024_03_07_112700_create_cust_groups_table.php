<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->text('description')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_groups');
    }
}