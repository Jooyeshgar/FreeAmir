<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustGroupsTable extends Migration
{
    public function up()
    {
        // Create the custGroups table
        Schema::create('custGroups', function (Blueprint $table) {
            $table->id();
            $table->string('Code', 20);
            $table->string('Name', 50);
            $table->text('Desc');
            $table->primary('Id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('custGroups');
    }
}