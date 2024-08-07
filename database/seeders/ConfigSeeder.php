<?php

namespace Database\Seeders;

use App\Models\Config;
use Illuminate\Database\Seeder;

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
            ['type' => 1, 'category' => 0, 'key' => 'company-name', 'value' => 'Enter Company Name', 'desc' => 'Enter Company name here'],
            ['type' => 0, 'category' => 0, 'key' => 'logo', 'value' => '', 'desc' => 'Select Company logo'],
            ['type' => 2, 'category' => 1, 'key' => 'custSubject', 'value' => '4', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'bank', 'value' => '1', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'cash', 'value' => '3', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'buy', 'value' => '17', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'sell', 'value' => '18', 'desc' => 'Enter here'],
            ['type' => 2, 'category' => 1, 'key' => 'sell-discount', 'value' => '25', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'tax', 'value' => '33', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'partners', 'value' => '8', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'cost', 'value' => '2', 'desc' => 'Enter here'],
            ['type' => 2, 'category' => 1, 'key' => 'bank-wage', 'value' => '31', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'our_cheque', 'value' => '22', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'other_cheque', 'value' => '6', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'income', 'value' => '83', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'fund', 'value' => '??', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'acc-receivable', 'value' => '??', 'desc' => 'Enter here'],
            ['type' => 3, 'category' => 1, 'key' => 'commission', 'value' => '??', 'desc' => 'Enter here'],
        ];

        Config::insert($configs);
    }
}
