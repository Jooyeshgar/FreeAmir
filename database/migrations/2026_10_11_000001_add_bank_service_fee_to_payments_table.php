<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('bank_service_fee', 10, 2)->default(0)->after('amount');
        });

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
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('bank_service_fee');
        });

        DB::table('configs')->where('key', 'bank_service_fee')->delete();
    }
};
