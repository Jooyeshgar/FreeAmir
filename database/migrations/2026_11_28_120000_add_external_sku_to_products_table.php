<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('external_sku', 100)->nullable()->after('code');
            $table->index(['fiscal_year_id', 'external_sku'], 'products_fiscal_year_external_sku_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_fiscal_year_external_sku_index');
            $table->dropColumn('external_sku');
        });
    }
};
