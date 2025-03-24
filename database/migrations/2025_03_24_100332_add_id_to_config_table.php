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
        // Check if there's a primary key and drop it first
        Schema::table('configs', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::table('configs', function (Blueprint $table) {
            $table->id()->first();
            $table->unique(['key', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->dropUnique(['key', 'company_id']);
            $table->dropColumn('id');

            // Restore the original primary key
            $table->primary('key');
        });
    }
};
