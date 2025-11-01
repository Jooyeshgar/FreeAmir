<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop old morph column
            $table->dropForeign('products_sales_subject_id_foreign');
            $table->dropColumn('sales_subject_id');

            $table->foreignId('sales_returns_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('income_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_sales_subject_id');
            $table->dropConstrainedForeignId('income_subject_id');
        });
    }
};
