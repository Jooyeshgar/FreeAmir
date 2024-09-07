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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('logo')->nullable();
            $table->string('address', 150)->nullable();
            $table->string('economical_code', 15)->nullable();
            $table->string('national_code', 12)->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone_number', 11)->nullable();
            $table->unsignedInteger('fiscal_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
