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
            $table->dropColumn('transaction_id');
            $table->decimal('cog_after', 18, 2)->default(0)->after('vat');
            $table->decimal('quantity_at', 10, 2)->default(0)->after('vat');
        });

        Schema::table('ancillary_cost_items', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('cost_at_time_of_sale', 18, 2)->nullable()->after('vat');
            $table->unsignedBigInteger('transaction_id');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');

            $table->dropColumn('cog_after');
            $table->dropColumn('quantity_at');
        });

        Schema::table('ancillary_cost_items', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
        });
    }
};
