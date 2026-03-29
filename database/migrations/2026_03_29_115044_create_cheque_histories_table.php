<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheque_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cheque_id')->nullable()->constrained('cheques')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('action_type', ['create', 'issue', 'receive', 'deliver', 'deposit',
                'transfer', 'clear', 'bounce', 'return', 'cancel', 'block', 'unblock', 'edit', 'legal_followup']);

            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->dateTime('action_at');
            $table->decimal('amount', 18, 2)->nullable();
            $table->text('desc')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_histories');
    }
};
