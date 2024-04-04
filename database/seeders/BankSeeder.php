<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            ['Id' => 1, 'Name' => 'پارسیان', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 2, 'Name' => 'دی', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 3, 'Name' => 'سامان', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 4, 'Name' => 'سپه', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 5, 'Name' => 'سرمایه', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 6, 'Name' => 'صادرات', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 7, 'Name' => 'کشاورزی', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 8, 'Name' => 'ملت', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 9, 'Name' => 'ملی', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 10, 'Name' => 'بانک ملّی ایران', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 11, 'Name' => 'بانک سپه', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 12, 'Name' => 'بانک صنعت و معدن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 13, 'Name' => 'بانک کشاورزی', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 14, 'Name' => 'بانک مسکن', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 15, 'Name' => 'بانک توسعه صادرات ایران', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 16, 'Name' => 'بانک توسعه تعاون', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 17, 'Name' => 'پست بانک ایران', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 18, 'Name' => 'بانک اقتصاد نوین', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 19, 'Name' => 'بانک پارسیان', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 20, 'Name' => 'بانک کارآفرین', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 21, 'Name' => 'بانک سامان', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 22, 'Name' => 'بانک سینا', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 23, 'Name' => 'بانک خاورمیانه', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 24, 'Name' => 'بانک شهر', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 25, 'Name' => 'بانک آینده', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 26, 'Name' => 'بانک گردشگری', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 27, 'Name' => 'بانک سرمایه', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 28, 'Name' => 'بانک پاسارگاد', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 29, 'Name' => 'بانک ایران زمین', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ];

        DB::table('banks')->insert($subjectData);
    }
}
