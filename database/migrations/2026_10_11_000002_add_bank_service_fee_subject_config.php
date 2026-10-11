<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->orderBy('id')->pluck('id')->each(function (int $companyId): void {
            $subjectId = DB::table('subjects')->where('company_id', $companyId)->where('code', '040010')->value('id');

            if (! $subjectId || DB::table('configs')->where('company_id', $companyId)->where('key', 'bank_service_fee')->exists()) {
                return;
            }

            DB::table('configs')->insert([
                'key' => 'bank_service_fee',
                'company_id' => $companyId,
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
