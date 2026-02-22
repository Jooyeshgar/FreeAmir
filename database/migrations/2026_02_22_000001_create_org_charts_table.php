<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('org_charts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 200);
            $table->unsignedInteger('parent_id')->nullable()->comment('NULL = root node');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')->on('org_charts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_charts');
    }
};
