<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    public function run()
    {
        $customerGroup = [
            'subject_id' => 4,
            'name' => 'عمومی',
            'description' => 'گروه مشتریان عمومی',
            'company_id' => 1,
        ];

        CustomerGroup::create($customerGroup);
    }
}
