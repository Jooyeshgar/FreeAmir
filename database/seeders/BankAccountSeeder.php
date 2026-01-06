<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankAccount;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run()
    {
        $banks = Bank::withoutGlobalScopes()->get();

        foreach ($banks as $bank) {
            BankAccount::factory()->count(5)->withBank($bank)->withSubject()->create();
        }
    }
}
