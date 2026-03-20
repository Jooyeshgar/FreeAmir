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
        Schema::table('tax_slabs', function (Blueprint $table) {
            $table->dropUnique('uq_tax_slab');
            $table->dropIndex('idx_tax_year');
            $table->dropColumn(['year', 'slab_order', 'income_from', 'annual_exemption']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_slabs', function (Blueprint $table) {
            $table->smallInteger('year')->unsigned()->after('company_id');
            $table->tinyInteger('slab_order')->unsigned()->after('year');
            $table->decimal('income_from', 18, 2)->after('slab_order');
            $table->decimal('annual_exemption', 18, 2)->nullable()->after('tax_rate');

            $table->unique(['year', 'slab_order'], 'uq_tax_slab');
            $table->index('year', 'idx_tax_year');
        });
    }
};
