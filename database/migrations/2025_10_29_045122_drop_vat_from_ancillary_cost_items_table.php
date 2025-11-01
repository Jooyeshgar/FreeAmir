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
        Schema::table('ancillary_cost_items', function (Blueprint $table) {
            $table->dropColumn('vat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ancillary_cost_items', function (Blueprint $table) {
            $table->decimal('vat', 18, 2)->default(0)->after('amount');
        });
    }
};
