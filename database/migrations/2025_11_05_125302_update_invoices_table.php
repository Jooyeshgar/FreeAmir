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
        Schema::table('invoices', function (Blueprint $table) {
            // Drop the existing unique constraint on number
            $table->dropUnique(['number']);

            // Change number column to decimal
            $table->decimal('number', 16, 2)->change();

            // Add composite unique constraint on number and company_id
            $table->unique(['number', 'invoice_type', 'company_id'], 'invoices_number_invoice_type_company_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('invoices_number_company_id_unique');

            // Change number back to string
            $table->string('number')->change();

            // Add back the simple unique constraint
            $table->unique('number');
        });
    }
};
