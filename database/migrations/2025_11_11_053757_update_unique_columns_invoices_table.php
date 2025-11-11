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
            $table->dropUnique('invoices_number_company_id_unique');

            $table->unique(['number', 'invoice_type', 'company_id'], 'invoices_number_invoice_type_company_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_number_invoice_type_company_id_unique');

            $table->unique(['number', 'company_id'], 'invoices_number_company_id_unique');
        });
    }
};
