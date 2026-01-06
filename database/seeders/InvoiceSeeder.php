<?php

namespace Database\Seeders;

use App\Models\Invoice;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        session(['active-company-id' => 1]);
        Invoice::factory()->count(100)->create();
    }
}
