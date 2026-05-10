<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('transactions_subject_id_foreign');
            $table->dropForeign('transactions_document_id_foreign');

            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('transactions_subject_id_foreign');
            $table->dropForeign('transactions_document_id_foreign');

            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
        });
    }
};
