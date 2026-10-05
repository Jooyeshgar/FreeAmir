<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\BankAccount;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run()
    {
        $banks = Bank::withoutGlobalScopes()->where('fiscal_year_id', getActiveFiscalYear())->take(5)->get();

        foreach ($banks as $bank) {
            BankAccount::factory()->count(1)->withBank($bank)->withSubject()->create();
        }
    }
}
