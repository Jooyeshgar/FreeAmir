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
        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('fiscal_year');
            $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
            // Step 1: Closing temporary accounts (Income Summary / P&L document)
            $table->unsignedBigInteger('pl_document_id')->nullable()->after('closed_by');
            // Step 3: Closing permanent accounts (final closing document)
            $table->unsignedBigInteger('closing_document_id')->nullable()->after('pl_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['closed_at', 'closed_by', 'pl_document_id', 'closing_document_id']);
        });
    }
};
