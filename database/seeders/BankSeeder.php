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
            ['name' => 'بانک پارسیان'],
            ['name' => 'بانک دی'],
            ['name' => 'بانک سامان'],
            ['name' => 'بانک سپه'],
            ['name' => 'بانک سرمایه'],
            ['name' => 'بانک صادرات'],
            ['name' => 'بانک کشاورزی'],
            ['name' => 'بانک ملت'],
            ['name' => 'بانک ملی'],
            ['name' => 'بانک صنعت و معدن'],
            ['name' => 'بانک مسکن'],
            ['name' => 'بانک توسعه تعاون'],
            ['name' => 'بانک اقتصاد نوین'],
            ['name' => 'بانک کارآفرین'],
            ['name' => 'بانک سینا'],
            ['name' => 'بانک خاورمیانه'],
            ['name' => 'بانک شهر'],
            ['name' => 'بانک آینده'],
            ['name' => 'بانک گردشگری'],
            ['name' => 'بانک پاسارگاد'],
            ['name' => 'بانک ایران زمین'],
        ];

        Bank::insert($subjectData);
    }
}
