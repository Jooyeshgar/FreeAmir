<?php

namespace Database\Factories;

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
        $total = $quantity * $unit_price - $unit_discount;
        $vat = $total * 0.1;
        $amount = $total + $vat;

        return [
            'description' => $this->faker->persianSentence(),
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'unit_discount' => $unit_discount,
            'vat' => $vat,
            'amount' => $amount,
        ];
    }
}
