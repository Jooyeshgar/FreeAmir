<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop old morph column
            if (Schema::hasColumn('products', 'subject_id')) {
                $table->dropColumn('subject_id');
            }

            // Add new subject relations
            $table->foreignId('sales_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('cogs_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('inventory_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_subject_id');
            $table->dropConstrainedForeignId('cogs_subject_id');
            $table->dropConstrainedForeignId('inventory_subject_id');
            $table->unsignedBigInteger('subject_id')->nullable();
        });
    }
};
