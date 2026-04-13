<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    public function run()
    {
        CustomerGroup::factory()
            ->withSubject()
            ->create([
                'name' => 'عمومی',
                'description' => 'گروه مشتریان عمومی',
                'company_id' => 1,
            ]);
    }
}
