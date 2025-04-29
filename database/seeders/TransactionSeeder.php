<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\Bank;
use App\Models\Subject;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Fetch all banks
        $banks = Subject::where('parent_id', config('amir.bank'))->get();

        // Create transactions for each bank
        foreach ($banks as $bank) {
            Transaction::factory()->count(10)->create([
                'bank_id' => $bank->id,
                'amount' => fake()->randomFloat(2, 100, 10000), // Random amount between 100 and 10,000
                'type' => fake()->randomElement(['credit', 'debit']), // Randomly choose credit or debit
                'description' => fake()->sentence(),
            ]);
        }
    }
}