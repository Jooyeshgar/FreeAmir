<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('service_fee', 18, 2)->default(0)->after('amount');
            $table->foreignId('fee_subject_id')->nullable()->after('settlement_subject_id')->constrained('subjects')->nullOnDelete();
        });

        $existing = DB::table('configs')->where('key', 'sundry_cost')->get();
        foreach ($existing as $config) {
            DB::table('configs')->updateOrInsert(
                ['key' => 'service_fee_expense', 'fiscal_year_id' => $config->fiscal_year_id],
                ['value' => $config->value, 'desc' => 'هزینه کارمزد خدمات', 'type' => 3, 'category' => 1],
            );
        }
    }

    public function down(): void
    {
        DB::table('configs')->where('key', 'service_fee_expense')->delete();

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fee_subject_id');
            $table->dropColumn('service_fee');
        });
    }
};
