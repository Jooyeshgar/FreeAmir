<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 60);
            // $table->unsignedBigInteger('parent_id');
            $table->enum('type', ['debtor', 'creditor', 'both'])->default('both');
            $table->nestedSet();
            $table->timestamps();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete()->after('parent_id');
            $table->unique(['company_id', 'code']);

            // $table->foreign('parent_id')->references('id')->on('subjects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subjects');
    }
}
