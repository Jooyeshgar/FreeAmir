<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropUnique('bank_accounts_iban_unique');
            $table->unique(['company_id', 'iban'], 'bank_accounts_company_id_iban_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropUnique('bank_accounts_company_id_iban_unique');
            $table->unique('iban', 'bank_accounts_iban_unique');
        });
    }
};
