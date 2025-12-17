<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(1, 1, 10);
        $unit_price = $this->faker->randomFloat(2, 100, 1000);

        return [
            'description' => $this->faker->paragraph(2),
            'item_id' => Product::inRandomOrder()->first()->id,
            'item_type' => 'product',
            'transaction_id' => Transaction::factory(),
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => $quantity * $unit_price,
        ];
    }
}
