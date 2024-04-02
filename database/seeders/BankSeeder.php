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
            ['Id' => 9, 'Name' => 'ملی', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]
        ];

        DB::table('banks')->insert($subjectData);
    }
}
