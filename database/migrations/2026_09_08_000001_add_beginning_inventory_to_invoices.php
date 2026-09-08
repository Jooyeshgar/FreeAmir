<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_number_invoice_type_company_id_unique');
            $table->boolean('is_beginning_inventory')->default(false)->after('invoice_type');
            $table->unsignedTinyInteger('status')->nullable()->default(null)->change();
            $table->foreignId('customer_id')->nullable()->change();
            $table->unique(
                ['number', 'invoice_type', 'is_beginning_inventory', 'company_id'],
                'invoices_number_type_beginning_company_unique'
            );
        });
    }

    public function down(): void
    {
        if (DB::table('invoices')->where('is_beginning_inventory', true)->exists()) {
            throw new RuntimeException('Remove beginning inventory records before rolling back this migration.');
        }

        DB::table('invoices')->whereNull('status')->update(['status' => InvoiceStatus::PENDING->value]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_number_type_beginning_company_unique');
            $table->unsignedTinyInteger('status')->nullable(false)->default(InvoiceStatus::PENDING->value)->change();
            $table->foreignId('customer_id')->nullable(false)->change();
            $table->dropColumn('is_beginning_inventory');
            $table->unique(['number', 'invoice_type', 'company_id'], 'invoices_number_invoice_type_company_id_unique');
        });
    }
};
