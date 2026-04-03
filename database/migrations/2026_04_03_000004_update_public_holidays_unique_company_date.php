<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('public_holidays', function (Blueprint $table) {
            $table->dropUnique(['date']);
            $table->unique(['company_id', 'date'], 'public_holidays_company_id_date_unique');
        });

        Schema::table('work_site_contracts', function (Blueprint $table) {
            $table->dropUnique(['code']);
        });

        Schema::table('work_sites', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['company_id', 'code'], 'work_sites_company_id_code_unique');

        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['company_id', 'code'], 'employees_company_id_code_unique');

            $table->dropUnique(['national_code']);
            $table->unique(['company_id', 'national_code'], 'employees_company_id_national_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('public_holidays', function (Blueprint $table) {
            $table->dropUnique('public_holidays_company_id_date_unique');
            $table->unique('date');
        });

        Schema::table('work_site_contracts', function (Blueprint $table) {
            $table->unique('code');
        });

        Schema::table('work_sites', function (Blueprint $table) {
            $table->dropUnique('work_sites_company_id_code_unique');
            $table->unique('code');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'code'], 'employees_company_id_code_unique');
            $table->unique(['code']);

            $table->dropUnique(['company_id', 'code'], 'employees_company_id_national_code_unique');
            $table->unique(['national_code']);
        });
    }
};
