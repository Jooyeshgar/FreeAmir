<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedInteger('organization_unit_id')->nullable()->after('org_chart_id');

            $table->foreign('organization_unit_id')
                ->references('id')->on('organization_units')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['organization_unit_id']);
            $table->dropColumn('organization_unit_id');
        });
    }
};
