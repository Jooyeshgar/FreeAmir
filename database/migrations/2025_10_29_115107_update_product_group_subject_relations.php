<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            // Drop old morph column
            $table->dropForeign('product_groups_subject_id_foreign');
            $table->dropColumn('subject_id');

            // Add new subject relations
            $table->foreignId('return_sales_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('cogs_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('inventory_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('income_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_sales_subject_id');
            $table->dropConstrainedForeignId('cogs_subject_id');
            $table->dropConstrainedForeignId('inventory_subject_id');
            $table->dropConstrainedForeignId('income_subject_id');

            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
        });
    }
};
