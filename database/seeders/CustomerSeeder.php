<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        Event::fake();

        $customers = Customer::factory()->count(50)->create();
        foreach ($customers as $customer) {
            Subject::create([
                'company_id' => $customer->company_id,
                'subjectable_type' => Customer::class,
                'subjectable_id' => $customer->id,
                'parent_id' => 99,
                'code' => '012002'.$customer->id,
                'name' => $customer->name,
                'type' => 2,
            ]);
        }
    }
}
