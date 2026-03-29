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

        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('tax_base_amount', 18, 2)->default(0)->after('total_deductions');    // Taxable income for this month
            $table->decimal('income_tax_amount', 18, 2)->default(0)->after('tax_base_amount');   // Calculated income tax for this month
        });

        Schema::table('payroll_elements', function (Blueprint $table) {
            $table->enum('calc_type', ['fixed', 'formula', 'percentage', 'daily'])->change();
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

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['tax_base_amount', 'income_tax_amount']);
        });

        Schema::table('payroll_elements', function (Blueprint $table) {
            $table->enum('calc_type', ['fixed', 'formula', 'percentage'])->change();
        });
    }
};
