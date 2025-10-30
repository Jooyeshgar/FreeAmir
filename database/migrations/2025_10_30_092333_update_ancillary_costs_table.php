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
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->constrained('documents')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->dropColumn('document_id');
        });
    }
};
