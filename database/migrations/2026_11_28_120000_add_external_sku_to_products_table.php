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
            $table->unique(['fiscal_year_id', 'external_sku'], 'products_fiscal_year_external_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_fiscal_year_external_sku_unique');
            $table->dropColumn('external_sku');
        });
    }
};
