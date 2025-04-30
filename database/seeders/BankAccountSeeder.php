<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run()
    {
        $banks = Bank::withoutGlobalScopes()->where('company_id', 1)->get();

        foreach ($banks as $bank) {
            BankAccount::create([
                'name' => 'Account Name',
                'number' => rand(1000000000, 9999999999),
                'type' => 1,
                'owner' => 'John Doe',
                'bank_id' => $bank->id,
                'bank_branch' => 'Main Branch',
                'bank_address' => '123 Bank St',
                'bank_phone' => '123-456-7890',
                'bank_web_page' => 'www.bankwebsite.com',
                'desc' => 'Main bank account',
                'company_id' => 1
            ]);
        }

        $banks = [
            ['name' => 'ملی', 'code' => '010001', 'parent_id' => 1, 'type' => 'both', 'company_id' => 1],
            ['name' => 'ملت', 'code' => '010002', 'parent_id' => 1, 'type' => 'both', 'company_id' => 1],
            ['name' => 'شهر', 'code' => '010003', 'parent_id' => 1, 'type' => 'both', 'company_id' => 1],
        ];

        Subject::insert($banks);

        $cashBooks = [
            ['name' => 'صندوق ۱', 'code' => '011001001', 'parent_id' => 14, 'type' => 'both', 'company_id' => 1],
            ['name' => 'صندوق ۲', 'code' => '011001002', 'parent_id' => 14, 'type' => 'both', 'company_id' => 1],
        ];

        Subject::insert($cashBooks);
    }
}
