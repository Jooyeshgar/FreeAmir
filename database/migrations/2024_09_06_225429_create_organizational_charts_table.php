<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrganizationalChartsTable extends Migration
{
    public function up()
    {
        Schema::create('organizational_charts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('supervisor')->nullable(); // assuming 'supervisor' is a string, adjust if necessary
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('organizational_charts');
    }
}
