<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $subjectData = [
            ['name' => 'بانک پارسیان', 'company_id' => 1],
            ['name' => 'بانک دی', 'company_id' => 1],
            ['name' => 'بانک سامان', 'company_id' => 1],
            ['name' => 'بانک سپه', 'company_id' => 1],
            ['name' => 'بانک سرمایه', 'company_id' => 1],
            ['name' => 'بانک صادرات', 'company_id' => 1],
            ['name' => 'بانک کشاورزی', 'company_id' => 1],
            ['name' => 'بانک ملت', 'company_id' => 1],
            ['name' => 'بانک ملی', 'company_id' => 1],
            ['name' => 'بانک صنعت و معدن', 'company_id' => 1],
            ['name' => 'بانک مسکن', 'company_id' => 1],
            ['name' => 'بانک توسعه تعاون', 'company_id' => 1],
            ['name' => 'بانک اقتصاد نوین', 'company_id' => 1],
            ['name' => 'بانک کارآفرین', 'company_id' => 1],
            ['name' => 'بانک سینا', 'company_id' => 1],
            ['name' => 'بانک خاورمیانه', 'company_id' => 1],
            ['name' => 'بانک شهر', 'company_id' => 1],
            ['name' => 'بانک آینده', 'company_id' => 1],
            ['name' => 'بانک گردشگری', 'company_id' => 1],
            ['name' => 'بانک پاسارگاد', 'company_id' => 1],
            ['name' => 'بانک ایران زمین', 'company_id' => 1],
        ];

        foreach ($subjectData as $bank) {
            Bank::firstOrCreate([
                'name' => $bank['name'],
                'company_id' => $bank['company_id'],
            ]);
        }
    }
}
