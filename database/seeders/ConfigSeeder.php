<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $configs = [
            ['id' => 1, 'type' => 1, 'category' => 0, 'key' => 'co-name', 'value' => 'Enter Company Name', 'desc' => 'Enter Company name here'],
            ['id' => 2, 'type' => 0, 'category' => 0, 'key' => 'co-logo', 'value' => '', 'desc' => 'Select Colpany logo'],
            ['id' => 3, 'type' => 2, 'category' => 1, 'key' => 'custSubject', 'value' => '4', 'desc' => 'Enter here'],
            ['id' => 4, 'type' => 3, 'category' => 1, 'key' => 'bank', 'value' => '1', 'desc' => 'Enter here'],
            ['id' => 5, 'type' => 3, 'category' => 1, 'key' => 'cash', 'value' => '3', 'desc' => 'Enter here'],
            ['id' => 6, 'type' => 3, 'category' => 1, 'key' => 'buy', 'value' => '17', 'desc' => 'Enter here'],
            ['id' => 7, 'type' => 3, 'category' => 1, 'key' => 'sell', 'value' => '18', 'desc' => 'Enter here'],
            ['id' => 8, 'type' => 2, 'category' => 1, 'key' => 'sell-discount', 'value' => '25', 'desc' => 'Enter here'],
            ['id' => 9, 'type' => 3, 'category' => 1, 'key' => 'tax', 'value' => '33', 'desc' => 'Enter here'],
            ['id' => 10, 'type' => 3, 'category' => 1, 'key' => 'partners', 'value' => '8', 'desc' => 'Enter here'],
            ['id' => 11, 'type' => 3, 'category' => 1, 'key' => 'cost', 'value' => '2', 'desc' => 'Enter here'],
            ['id' => 12, 'type' => 2, 'category' => 1, 'key' => 'bank-wage', 'value' => '31', 'desc' => 'Enter here'],
            ['id' => 13, 'type' => 3, 'category' => 1, 'key' => 'our_cheque', 'value' => '22', 'desc' => 'Enter here'],
            ['id' => 14, 'type' => 3, 'category' => 1, 'key' => 'other_cheque', 'value' => '6', 'desc' => 'Enter here'],
            ['id' => 15, 'type' => 3, 'category' => 1, 'key' => 'income', 'value' => '83', 'desc' => 'Enter here'],
            ['id' => 11, 'type' => 3, 'category' => 1, 'key' => 'fund', 'value' => '??', 'desc' => 'Enter here'],
            ['id' => 12, 'type' => 3, 'category' => 1, 'key' => 'acc-receivable', 'value' => '??', 'desc' => 'Enter here'],
            ['id' => 13, 'type' => 3, 'category' => 1, 'key' => 'commission', 'value' => '??', 'desc' => 'Enter here']
        ];

        DB::table('configs')->insert($configs);
    }
}
