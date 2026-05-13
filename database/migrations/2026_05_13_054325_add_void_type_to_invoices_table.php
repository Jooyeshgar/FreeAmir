<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('invoice_type', ['buy', 'sell', 'return_buy', 'return_sell', 'void'])->default('sell')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('invoice_type', ['buy', 'sell', 'return_buy', 'return_sell'])->default('sell')->change();
        });
    }
};
