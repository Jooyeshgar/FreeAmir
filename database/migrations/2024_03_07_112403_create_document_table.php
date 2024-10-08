<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentTable extends Migration
{
    public function up()
    {
        // Create the bill table
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->decimal('number', 16, 2)->nullable();
            $table->string('title')->nullable();
            $table->date('date')->nullable();
            $table->date('approved_at')->nullable();

            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('documents');
    }
}
