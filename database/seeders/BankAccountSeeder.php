<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $subjectData = [
            ['Id' => 1, 'name' => 'Account 1', 'number' => '123456789', 'type' => 1, 'owner' => 'John Doe', 'bank_id' => DB::table('banks')->where('Name', 'پارسیان')->first()->id, 'bank_branch' => 'Main Branch', 'bank_address' => '123 Main St', 'bank_phone' => '123-456-7890', 'bank_web_page' => 'www.example.com', 'desc' => 'Main checking account', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['Id' => 2, 'name' => 'Account 2', 'number' => '987654321', 'type' => 2, 'owner' => 'Jane Doe', 'bank_id' => DB::table('banks')->where('Name', 'دی')->first()->id, 'bank_branch' => 'Branch 2', 'bank_address' => '456 Secondary St', 'bank_phone' => '098-765-4321', 'bank_web_page' => 'www.example2.com', 'desc' => 'Secondary savings account', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            // ...
        ];

        DB::table('bank_accounts')->insert($subjectData);
    }
}
