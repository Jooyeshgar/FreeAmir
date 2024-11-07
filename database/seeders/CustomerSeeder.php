<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

class CustomerSeeder extends Seeder
{
    public function run()
    {
        Event::fake();

        Customer::factory()->count(50)->create();
    }
}
