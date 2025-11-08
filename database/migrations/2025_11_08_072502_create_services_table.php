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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 60);
            $table->unsignedBigInteger('group')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->decimal('selling_price', 10, 2);
            $table->decimal('vat')->nullable();
            $table->string('description', 200)->nullable();

            $table->foreign('group')->references('id')->on('service_groups')->onDelete('set null');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->unique(['company_id', 'code']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
