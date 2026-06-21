<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('iban')->unique()->nullable();
            $table->enum('type', ['current', 'savings', 'qarz_al_hasanah', 'other'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('iban');
            $table->integer('type')->change();
        });
    }
};
