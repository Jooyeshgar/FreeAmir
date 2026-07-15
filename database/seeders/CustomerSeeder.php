<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        $groups = CustomerGroup::withoutGlobalScopes()->where('company_id', getActiveCompany())->get();

        foreach ($groups as $group) {
            Customer::factory()->count(10)->withGroup($group)->withSubject()->create();
        }
    }
}
