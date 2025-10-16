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
        Schema::create('ancillary_costs', function (Blueprint $table) {
            $table->id();
            $table->string('description', 200)->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('date');
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ancillary_costs');
    }
};
