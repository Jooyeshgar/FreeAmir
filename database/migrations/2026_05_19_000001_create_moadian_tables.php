<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('certificate_path')->nullable()->after('logo');
            $table->string('private_key_path')->nullable()->after('certificate_path');
            $table->string('moadian_username', 20)->nullable()->after('private_key_path');
            $table->string('tax_id', 20)->nullable()->after('moadian_username');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('taxID')->nullable()->after('company_id');
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

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['certificate_path', 'private_key_path', 'moadian_username', 'tax_id']);
        });
    }
};
