<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->orderBy('id')->pluck('id')->each(function (int $companyId): void {
            $subjectId = DB::table('subjects')->where('company_id', $companyId)->where('code', '067001')->value('id');

            if (! $subjectId) {
                return;
            }

            DB::table('configs')->updateOrInsert(
                ['key' => 'beginning_inventory', 'company_id' => $companyId],
                [
                    'value' => (string) $subjectId,
                    'desc' => 'تراز افتتاحیه',
                    'type' => 3,
                    'category' => 1,
                ],
            );
        });
    }

    public function down(): void
    {
        DB::table('configs')->where('key', 'beginning_inventory')->delete();
    }
};
