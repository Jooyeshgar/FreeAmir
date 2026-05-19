<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('taxID', 128)->nullable()->after('company_id');
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
            $table->dropColumn('taxID');
        });
    }
};
