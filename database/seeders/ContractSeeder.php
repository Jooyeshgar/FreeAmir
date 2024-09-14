<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contract;

class ContractSeeder extends Seeder
{
    /**
     * Seed the database with contract data.
     *
     * @return void
     */
    public function run()
    {
        Contract::factory()->count(10)->create();
    }
}
