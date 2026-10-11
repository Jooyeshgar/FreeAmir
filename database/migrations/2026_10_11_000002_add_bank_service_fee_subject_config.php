<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fiscal_years')->orderBy('id')->pluck('id')->each(function (int $fiscalYearId): void {
            $subjectId = DB::table('subjects')->where('fiscal_year_id', $fiscalYearId)->where('code', '040010')->value('id');

            if (! $subjectId || DB::table('configs')->where('fiscal_year_id', $fiscalYearId)->where('key', 'bank_service_fee')->exists()) {
                return;
            }

            DB::table('configs')->insert([
                'key' => 'bank_service_fee',
                'fiscal_year_id' => $fiscalYearId,
                'value' => (string) $subjectId,
                'desc' => 'کارمزد بانک',
                'type' => 3,
                'category' => 1,
            ]);
        });
    }

    public function down(): void
    {
        DB::table('configs')->where('key', 'bank_service_fee')->delete();
    }
};
