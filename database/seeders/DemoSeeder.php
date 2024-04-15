<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            BankAccountSeeder::class,
            CustomerSeeder::class,
            ProductSeeder::class,
            InvoiceSeeder::class,
        ]);
    }
}
