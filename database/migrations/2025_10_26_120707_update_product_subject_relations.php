<?php

use App\Support\SqliteSchemaHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            SqliteSchemaHelper::dropFkColumn('products', 'subject_id');
        } else {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign('products_subject_id_foreign');
                $table->dropColumn('subject_id');
            });
        }

        Schema::table('products', function (Blueprint $table) {
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

            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('set null');
        });
    }
};
