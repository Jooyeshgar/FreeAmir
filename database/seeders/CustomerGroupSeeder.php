<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerGroupSeeder extends Seeder
{
    public function run()
    {
        $customerGroups = [
            ['id' => 1, 'code' => 'general', 'name' => 'عمومی', 'description' => 'گروه مشتریان عمومی'],
        ];

        DB::table('customer_groups')->insert($customerGroups);
    }
}
