<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(0, 1, 10);
        $unit_price = $this->faker->randomFloat(0, 100, 1000);

        return [
            'invoice_id' => Invoice::inRandomOrder()->first()->id,
            'description' => $this->faker->paragraph(2),
            'product_id' => Product::inRandomOrder()->first()->id,
            'transaction_id' => Transaction::factory()->create()->id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => $quantity * $unit_price,
        ];
    }
}
