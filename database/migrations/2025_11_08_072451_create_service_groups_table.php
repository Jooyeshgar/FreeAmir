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
        Schema::create('service_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->decimal('vat')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('sstid')->nullable();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_groups');
    }
};
