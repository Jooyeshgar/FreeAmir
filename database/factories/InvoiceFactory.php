<?php

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numerify('INV-####'),
            'date' => $this->faker->date(),
            'invoice_type' => $this->faker->randomElement([InvoiceType::BUY, InvoiceType::SELL]),
            'customer_id' => Customer::inRandomOrder()->first()->id,
            'creator_id' => User::inRandomOrder()->first()->id,
            'subtraction' => 0,
            'tax' => 0,
            'vat' => 0,
            'description' => $this->faker->paragraph(),
            'amount' => 0,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Invoice $invoice) {
            // create 1-4 items and update invoice amount based on items
            $items = \App\Models\InvoiceItem::factory()->count(rand(1, 4))->create(['invoice_id' => $invoice->id]);
            $amount = $items->sum('amount');
            $invoice->update(['amount' => $amount]);
        });
    }
}
