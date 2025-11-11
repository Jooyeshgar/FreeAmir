<?php

namespace Database\Seeders;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoiceCount = 100;

        $faker = Faker::create();
        session(['active-company-id' => 1]);

        for ($i = 0; $i < $invoiceCount; $i++) {
            $user = User::inRandomOrder()->first();

            $itemQuantity = $faker->randomFloat(1, 1, 5);
            $itemUnit = $faker->randomFloat(1, 100, 1000);

            $invoiceData = [
                'date' => $faker->date,
                'invoice_type' => $faker->randomElement([InvoiceType::BUY, InvoiceType::SELL]),
                'customer_id' => Customer::inRandomOrder()->first()->id,
                'number' => $faker->unique()->numerify('####'),
                'subtraction' => 0,
                'description' => $faker->paragraph(2),
            ];

            $items = [
                'transaction_index' => 1,
                'product_id' => Product::inRandomOrder()->first()->id,
                'quantity' => $itemQuantity,
                'description' => $faker->sentence,
                'unit_discount' => 0,
                'vat' => 0,
                'unit' => $itemUnit,
                'total' => $itemQuantity * $itemUnit,
            ];
            $invoiceService->createInvoice($user, $invoiceData, [$items]);
        }
    }
}
