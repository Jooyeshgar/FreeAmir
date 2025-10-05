<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Add invoice_type column as enum with default value
            $table->enum('invoice_type', ['buy', 'sell', 'return_buy', 'return_sell'])
                  ->default('sell')
                  ->after('is_sell');
        });

        // Migrate existing data from is_sell to invoice_type
        // is_sell = true (1) -> 'sell'
        // is_sell = false (0) -> 'buy'
        DB::table('invoices')
            ->where('is_sell', true)
            ->update(['invoice_type' => 'sell']);

        DB::table('invoices')
            ->where('is_sell', false)
            ->update(['invoice_type' => 'buy']);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_sell');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Re-add is_sell column for rollback
            $table->boolean('is_sell')->default(true)->after('description');
        });

        // Restore data from invoice_type to is_sell
        DB::table('invoices')
            ->whereIn('invoice_type', ['sell', 'return_sell'])
            ->update(['is_sell' => true]);

        DB::table('invoices')
            ->whereIn('invoice_type', ['buy', 'return_buy'])
            ->update(['is_sell' => false]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_type');
        });
    }
};
