<?php

namespace Database\Seeders;

use App\Models\Invoice;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $invoiceCount = 100;

        // make sure the fiscal/company scope will set company_id
        session(['active-company-id' => 1]);

        // create invoices along with items via factories
        Invoice::factory()->count($invoiceCount)->create();
    }
}
