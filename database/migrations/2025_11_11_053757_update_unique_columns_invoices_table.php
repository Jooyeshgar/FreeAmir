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

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign('invoice_items_product_id_foreign');
            $table->dropForeign('invoice_items_service_id_foreign');

            $table->dropColumn('product_id', 'service_id');

            $table->nullableMorphs('itemable');

            $table->unique(['invoice_id', 'itemable_id', 'itemable_type'], 'invoice_items_invoice_id_itemable_id_itemable_type_unique');
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

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropUnique('invoice_items_invoice_id_item_id_item_type_unique');

            $table->dropMorphs('itemable');
            $table->unsignedBigInteger('product_id')->after('invoice_id');
            $table->unsignedBigInteger('service_id')->after('product_id');

            $table->foreign('service_id')->references('id')->on('services')->onDelete('set null');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }
};
