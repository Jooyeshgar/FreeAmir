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
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('sales_returns_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
        });

        Schema::table('service_groups', function (Blueprint $table) {
            $table->foreignId('sales_returns_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_returns_subject_id');
        });

        Schema::table('service_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_returns_subject_id');
        });
    }
};
