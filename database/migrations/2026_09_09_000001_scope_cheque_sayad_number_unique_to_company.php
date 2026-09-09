<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropUnique('cheques_sayad_number_unique');
            $table->unique(['company_id', 'sayad_number'], 'cheques_company_id_sayad_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cheques', function (Blueprint $table) {
            $table->dropUnique('cheques_company_id_sayad_number_unique');
            $table->unique('sayad_number', 'cheques_sayad_number_unique');
        });
    }
};
