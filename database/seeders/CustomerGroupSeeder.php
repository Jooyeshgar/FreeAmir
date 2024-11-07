<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    public function run()
    {
        $customerGroups = [
            ['code' => 'general', 'name' => 'عمومی', 'description' => 'گروه مشتریان عمومی', 'company_id' => 1],
        ];

        CustomerGroup::insert($customerGroups);
    }
}
