<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('title', 100);
            $table->string('serial_prefix', 20)->nullable();
            $table->unsignedBigInteger('start_leaf_number');
            $table->unsignedBigInteger('end_leaf_number');
            $table->unsignedBigInteger('next_leaf_number');
            $table->json('print_settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'bank_account_id', 'title']);
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->decimal('amount', 18, 2)->change();
            $table->unsignedSmallInteger('status')->change();
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            $table->unsignedBigInteger('account_id')->nullable()->change();
            $table->unsignedBigInteger('transaction_id')->nullable()->change();
            $table->unsignedBigInteger('history_id')->nullable()->change();
            $table->unsignedBigInteger('bill_id')->nullable()->change();
            $table->integer('order')->nullable()->change();
            $table->text('desc')->nullable()->change();

            $table->string('sayad_number', 16)->nullable()->after('serial');
            $table->unsignedSmallInteger('direction')->default(101)->after('sayad_number');
            $table->unsignedSmallInteger('purpose')->default(201)->after('direction');
            $table->foreignId('bank_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->after('bank_id')->constrained()->restrictOnDelete();
            $table->foreignId('endorsed_to_id')->nullable()->after('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('checkbook_id')->nullable()->after('bank_account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('checkbook_leaf_number')->nullable()->after('checkbook_id');
            $table->string('branch_name', 100)->nullable()->after('bank_account_id');
            $table->string('branch_city', 100)->nullable()->after('branch_name');
            $table->foreignId('created_by')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique('sayad_number');
            $table->index(['company_id', 'direction', 'status', 'due_date'], 'cheques_company_lifecycle_due_index');
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

        Schema::table('cheque_histories', function (Blueprint $table) {
            $table->decimal('amount', 18, 2)->nullable()->change();
            $table->date('write_date')->nullable()->change();
            $table->date('due_date')->nullable()->change();
            $table->string('serial', 50)->nullable()->change();
            $table->unsignedSmallInteger('status')->nullable()->change();
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            $table->unsignedBigInteger('account_id')->nullable()->change();
            $table->unsignedBigInteger('transaction_id')->nullable()->change();
            $table->date('date')->nullable()->change();

            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('event', 30)->nullable()->after('cheque_id');
            $table->unsignedSmallInteger('from_status')->nullable()->after('event');
            $table->unsignedSmallInteger('to_status')->nullable()->after('from_status');
            $table->foreignId('actor_id')->nullable()->after('to_status')->constrained('users')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->after('actor_id')->constrained('documents')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->after('document_id')->constrained('payments')->nullOnDelete();
            $table->json('metadata')->nullable()->after('payment_id');
            $table->timestamp('occurred_at')->nullable()->after('metadata');
            $table->timestamp('reverted_at')->nullable()->after('occurred_at');
            $table->foreignId('reverted_by')->nullable()->after('reverted_at')->constrained('users')->nullOnDelete();

            $table->index(['company_id', 'cheque_id', 'occurred_at'], 'cheque_history_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::table('cheque_histories', function (Blueprint $table) {
            $table->dropIndex('cheque_history_timeline_index');
            $table->dropConstrainedForeignId('reverted_by');
            $table->dropConstrainedForeignId('payment_id');
            $table->dropConstrainedForeignId('document_id');
            $table->dropConstrainedForeignId('actor_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn(['event', 'from_status', 'to_status', 'metadata', 'occurred_at', 'reverted_at']);

            $table->decimal('amount', 10, 2)->nullable(false)->change();
            $table->date('write_date')->nullable(false)->change();
            $table->date('due_date')->nullable(false)->change();
            $table->string('serial', 50)->nullable(false)->change();
            $table->enum('status', [1, 2, 3, 4, 5])->nullable(false)->change();
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
            $table->unsignedBigInteger('account_id')->nullable(false)->change();
            $table->unsignedBigInteger('transaction_id')->nullable(false)->change();
            $table->date('date')->nullable(false)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropConstrainedForeignId('payee_id');
            $table->dropConstrainedForeignId('cheque_id');
            $table->dropColumn(['method', 'direction']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });

        Schema::table('cheques', function (Blueprint $table) {
            $table->dropIndex('cheques_company_lifecycle_due_index');
            $table->dropUnique(['sayad_number']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('checkbook_id');
            $table->dropConstrainedForeignId('endorsed_to_id');
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropConstrainedForeignId('bank_id');
            $table->dropColumn([
                'sayad_number', 'direction', 'purpose', 'checkbook_leaf_number', 'branch_name',
                'branch_city', 'version', 'created_at', 'updated_at',
            ]);

            $table->float('amount')->nullable(false)->change();
            $table->enum('status', [1, 2, 3, 4, 5])->nullable(false)->change();
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
            $table->unsignedBigInteger('account_id')->nullable(false)->change();
            $table->unsignedBigInteger('transaction_id')->nullable(false)->change();
            $table->unsignedBigInteger('history_id')->nullable(false)->change();
            $table->unsignedBigInteger('bill_id')->nullable(false)->change();
            $table->string('desc', 200)->nullable()->change();
            $table->integer('order')->nullable(false)->change();
        });

        Schema::dropIfExists('checkbooks');
    }
};
