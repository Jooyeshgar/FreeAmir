<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cheque_histories');
        Schema::dropIfExists('cheques');

        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('write_date');
            $table->date('due_date');
            $table->string('serial', 50)->nullable();
            $table->string('cheque_number', 50)->nullable();
            $table->string('sayad_number', 16)->unique();
            $table->unsignedSmallInteger('direction');
            $table->unsignedSmallInteger('purpose');
            $table->unsignedSmallInteger('status');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('endorsed_to_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('desc')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['company_id', 'direction', 'status', 'due_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreignId('invoice_id')->nullable()->change();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreignId('cheque_id')->nullable()->after('invoice_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('cheque_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheque_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('from_status')->nullable();
            $table->unsignedSmallInteger('to_status');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->text('desc')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_histories');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropConstrainedForeignId('cheque_id');
            $table->foreignId('invoice_id')->nullable(false)->change();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });

        Schema::dropIfExists('cheques');
    }
};
