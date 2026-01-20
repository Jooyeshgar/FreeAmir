<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    public function run()
    {
        $customers = Customer::withoutGlobalScopes()->get();

        foreach ($customers as $customer) {
            Comment::factory()->count(5)->withCustomer($customer)->create();
        }
    }
}
