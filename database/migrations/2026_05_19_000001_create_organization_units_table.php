<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);

            $table->foreign('parent_id')
                ->references('id')->on('organization_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};
