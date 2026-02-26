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
        Schema::create('tax_slabs', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('year')->unsigned();
            $table->tinyInteger('slab_order')->unsigned();
            $table->decimal('income_from', 18, 2);
            $table->decimal('income_to', 18, 2)->nullable()->comment('NULL = unlimited');
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('annual_exemption', 18, 2)->nullable();
            $table->timestamps();

            $table->unique(['year', 'slab_order'], 'uq_tax_slab');
            $table->index('year', 'idx_tax_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_slabs');
    }
};
