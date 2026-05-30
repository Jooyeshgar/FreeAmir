<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('taxID')->nullable()->after('company_id');
            $table->date('pay_date')->nullable()->after('taxID');
            $table->string('pay_reference_number')->nullable()->after('pay_date');
        });

        Schema::create('moadian_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->json('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moadian_histories');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['taxID', 'pay_date', 'pay_reference_number']);
        });
    }
};
