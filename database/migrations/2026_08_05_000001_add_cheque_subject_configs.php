<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            'cheque_documents_receivable' => ['code' => '013001', 'desc' => 'اسناد دریافتنی'],
            'cheque_documents_in_collection' => ['code' => '014001', 'desc' => 'اسناد در جریان وصول'],
            'cheque_documents_payable' => ['code' => '020001', 'desc' => 'اسناد پرداختنی'],
        ];

        DB::table('companies')->orderBy('id')->pluck('id')->each(function (int $companyId) use ($definitions): void {
            foreach ($definitions as $key => $definition) {
                $subjectId = DB::table('subjects')->where('company_id', $companyId)->where('code', $definition['code'])->value('id');

                if (! $subjectId) {
                    continue;
                }

                DB::table('configs')->updateOrInsert(
                    ['key' => $key, 'company_id' => $companyId],
                    [
                        'value' => (string) $subjectId,
                        'desc' => $definition['desc'],
                        'type' => 3,
                        'category' => 1,
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('configs')->whereIn('key', ['cheque_documents_receivable', 'cheque_documents_in_collection', 'cheque_documents_payable'])->delete();
    }
};
