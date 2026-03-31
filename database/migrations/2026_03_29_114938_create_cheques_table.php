<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 18, 2);
            $table->date('written_at')->notNullable();
            $table->date('due_date')->notNullable();
            $table->string('serial', 50)->notNullable();
            $table->string('cheque_number', 100);
            $table->boolean('is_received');
            $table->string('sayad_number', 100)->nullable();
            $table->text('desc')->nullable();
            $table->enum('status', ['draft', 'issued', 'returned', 'cancelled', 'checkout'])->default('draft');

            $table->foreignId('cheque_book_id')->nullable()->constrained('cheque_books')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->timestamps();
            $table->unique(['cheque_book_id', 'cheque_number'], 'uniq_cheque_book_cheque_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
