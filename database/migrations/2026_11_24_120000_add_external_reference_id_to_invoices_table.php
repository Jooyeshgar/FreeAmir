<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('external_reference_id', 100)->nullable()->after('number');
            $table->index(['fiscal_year_id', 'external_reference_id'], 'invoices_fiscal_year_external_ref_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_fiscal_year_external_ref_index');
            $table->dropColumn('external_reference_id');
        });
    }
};
