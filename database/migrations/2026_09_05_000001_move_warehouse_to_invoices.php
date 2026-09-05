<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        DB::table('invoices')->orderBy('id')->each(function ($invoice): void {
            $warehouseId = DB::table('invoice_items')->where('invoice_id', $invoice->id)->whereNotNull('warehouse_id')->value('warehouse_id');
            $warehouseId ??= DB::table('warehouses')->where('company_id', $invoice->company_id)->orderBy('id')->value('id');

            DB::table('invoices')->where('id', $invoice->id)->update(['warehouse_id' => $warehouseId]);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });

        DB::table('invoices')->whereNotNull('warehouse_id')->orderBy('id')->each(function ($invoice): void {
            DB::table('invoice_items')->where('invoice_id', $invoice->id)->update(['warehouse_id' => $invoice->warehouse_id]);
        });

        DB::table('products')->orderBy('id')->each(function ($product): void {
            $warehouseId = DB::table('warehouse_product_stocks')->where('product_id', $product->id)->orderByDesc('quantity')->value('warehouse_id');

            DB::table('products')->where('id', $product->id)->update(['warehouse_id' => $warehouseId]);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
