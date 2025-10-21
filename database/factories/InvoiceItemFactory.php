<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(0, 1, 10);
        $unit_price = $this->faker->randomFloat(0, 100, 1000);
        $unit_discount = $this->faker->randomFloat(0, 0, 10);
        $product = Product::inRandomOrder()->first();
        $invoice = Invoice::inRandomOrder()->first();
        $transaction = Transaction::inRandomOrder()->first();
        $total = $quantity * $unit_price - $unit_discount;
        $vat = $total * 0.1;
        $amount = $total + $vat;

        return [
            'invoice_id' => $invoice->id,
            'description' => $this->faker->persianSentence(),
            'product_id' => $product->id,
            'transaction_id' => $transaction->id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'unit_discount' => $unit_discount,
            'vat' => $vat,
            'amount' => $amount,
        ];
    }
}
