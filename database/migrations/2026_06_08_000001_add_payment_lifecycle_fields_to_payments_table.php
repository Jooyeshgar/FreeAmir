<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->after('amount')->constrained('documents')->nullOnDelete();
            $table->foreignId('creator_id')->nullable()->after('document_id')->constrained('users')->nullOnDelete();
            $table->foreignId('settlement_subject_id')->nullable()->after('document_id')->constrained('subjects')->nullOnDelete();

            $table->decimal('amount', 18, 2)->change();
            $table->date('date');
            $table->string('reference_number', 20)->nullable();

            $table->dropColumn(['transaction_id', 'bill_id', 'bank', 'serial', 'payer_name', 'write_date', 'due_date', 'track_code']);

            $table->dropConstrainedForeignId('company_id');
            $table->dropForeign(['invoice_id']);
            $table->foreignId('invoice_id')->nullable(false)->change();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('bank', 100)->nullable()->after('due_date');
            $table->string('serial', 50)->nullable()->after('bank');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('bill_id')->nullable();
            $table->date('write_date')->nullable();
            $table->text('payer_name')->nullable();

            $table->dropForeign(['invoice_id']);
            $table->foreignId('invoice_id')->nullable()->change();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();

            $table->foreignId('company_id')->after('invoice_id')->nullable()->constrained()->cascadeOnDelete();

            $table->dropConstrainedForeignId('document_id');
            $table->dropConstrainedForeignId('creator_id');
            $table->dropConstrainedForeignId('settlement_subject_id');
            $table->dropColumn(['date', 'reference_number']);

            $table->decimal('amount', 10, 2)->change();
            $table->date('due_date')->nullable(false);
        });
    }
};
