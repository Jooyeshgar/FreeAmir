<?php

use App\Enums\InvoiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('invoices')->where('invoice_type', InvoiceType::BEGINNING_INVENTORY->value)->exists()) {
            throw new RuntimeException('Remove the beginning inventory before rolling back this migration.');
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable(false)->change();
        });
    }
};
