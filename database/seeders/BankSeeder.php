<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(?int $companyId = null): void
    {
        $companyId ??= (int) getActiveCompany();
        $bankNames = [
            'بانک پارسیان',
            'بانک دی',
            'بانک سامان',
            'بانک سپه',
            'بانک سرمایه',
            'بانک صادرات',
            'بانک کشاورزی',
            'بانک ملت',
            'بانک ملی',
            'بانک صنعت و معدن',
            'بانک مسکن',
            'بانک توسعه تعاون',
            'بانک اقتصاد نوین',
            'بانک کارآفرین',
            'بانک سینا',
            'بانک خاورمیانه',
            'بانک شهر',
            'بانک آینده',
            'بانک گردشگری',
            'بانک پاسارگاد',
            'بانک ایران زمین',
        ];

        foreach ($bankNames as $bankName) {
            Bank::withoutGlobalScopes()->firstOrCreate([
                'name' => $bankName,
                'company_id' => $companyId,
            ]);
        }
    }
}
