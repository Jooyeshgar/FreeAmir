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
            $table->dropColumn('cost_at_time_of_sale');
            $table->decimal('cog_after', 18, 2)->default(0)->after('vat');
            $table->decimal('quantity_at', 10, 2)->default(0)->after('vat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('cost_at_time_of_sale', 18, 2)->nullable()->after('vat');
            $table->dropColumn('cog_after');
            $table->dropColumn('quantity_at');
        });
    }
};
