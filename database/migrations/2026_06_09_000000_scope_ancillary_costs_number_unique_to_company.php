<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->dropUnique('ancillary_costs_number_unique');
            $table->unique(['number', 'company_id'], 'ancillary_costs_number_company_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ancillary_costs', function (Blueprint $table) {
            $table->dropUnique('ancillary_costs_number_company_id_unique');
            $table->unique('number', 'ancillary_costs_number_unique');
        });
    }
};
