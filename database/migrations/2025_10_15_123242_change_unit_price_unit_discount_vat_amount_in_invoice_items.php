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
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_price', 18, 2)->change();
            $table->decimal('unit_discount', 18, 2)->change();
            $table->decimal('vat', 16, 2)->change();
            $table->decimal('amount', 18, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
            $table->decimal('unit_discount', 10, 2)->change();
            $table->decimal('vat', 10, 2)->change();
            $table->decimal('amount', 10, 2)->change();
        });
    }
};
