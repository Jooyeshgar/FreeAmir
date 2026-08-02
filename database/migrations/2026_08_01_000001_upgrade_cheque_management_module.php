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
        Schema::dropIfExists('checkbooks');

        Schema::create('checkbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('title', 100);
            $table->string('serial_prefix', 20)->nullable();
            $table->unsignedBigInteger('start_leaf_number');
            $table->unsignedBigInteger('end_leaf_number');
            $table->unsignedBigInteger('next_leaf_number');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('write_date');
            $table->date('due_date');
            $table->string('serial', 50)->nullable();
            $table->string('sayad_number', 16)->unique();
            $table->unsignedSmallInteger('direction');
            $table->unsignedSmallInteger('purpose');
            $table->unsignedSmallInteger('status');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('endorsed_to_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('checkbook_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('checkbook_leaf_number')->nullable();
            $table->text('desc')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['checkbook_id', 'checkbook_leaf_number']);
            $table->index(['company_id', 'direction', 'status', 'due_date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreignId('invoice_id')->nullable()->change();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreignId('cheque_id')->nullable()->after('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payee_id')->nullable()->after('payer_id')->constrained('customers')->nullOnDelete();
            $table->string('method', 20)->default('cash')->after('reference_number');
            $table->string('direction', 20)->nullable()->after('method');
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
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['cheque_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_histories');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropConstrainedForeignId('payee_id');
            $table->dropConstrainedForeignId('cheque_id');
            $table->dropColumn(['method', 'direction']);
            $table->foreignId('invoice_id')->nullable(false)->change();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });

        Schema::dropIfExists('cheques');
        Schema::dropIfExists('checkbooks');
    }
};
