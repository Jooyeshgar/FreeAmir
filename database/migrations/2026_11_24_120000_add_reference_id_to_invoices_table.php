<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('reference_id', 100)->nullable()->after('number');
            $table->index(['fiscal_year_id', 'reference_id'], 'invoices_fiscal_year_reference_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_fiscal_year_reference_id_index');
            $table->dropColumn('reference_id');
        });
    }
};
