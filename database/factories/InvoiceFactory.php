<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = $this->faker->date();
        $amount = $this->faker->randomFloat(2, 1000, 10000);
        $user = User::inRandomOrder()->first();

        $document = DocumentService::createDocument(
            $user,
            [
                'date' => $date,
            ],
            []
        );

        return [
            'number' => $this->faker->unique()->numerify('#####'),
            'date' => $date,
            'document_id' => $document->id,
            'customer_id' => Customer::factory(),
            'creator_id' => $user->id,
            'company_id' => 1,
            'approver_id' => $this->faker->randomElement([null, $user->id]),
            'subtraction' => $this->faker->randomFloat(2, 0, 1000),
            'ship_date' => $this->faker->optional()->dateTime(),
            'ship_via' => $this->faker->company(),
            'description' => $this->faker->persianSentence(),
            'invoice_type' => $this->faker->randomElement(['buy', 'sell']),
            'active' => true,
            'vat' => $this->faker->randomNumber(5),
            'amount' => $amount,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function ($invoice) {
            $description = $this->faker->persianSentence();

            $quantity = $this->faker->randomFloat(0, 1, 10);
            $unit_price = $this->faker->randomFloat(0, 100, 1000);
            $unit_discount = $this->faker->randomFloat(0, 0, 10);
            $product = Product::inRandomOrder()->first();
            $transaction = Transaction::factory()->create();
            $total = $quantity * $unit_price - $unit_discount;
            $vat = $total * 0.1;
            $amount = $total + $vat;

            $invoiceItem = InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'product_id' => $product->id,
                'transaction_id' => $transaction->id,
                'quantity' => $this->faker->randomFloat(0, 1, 10),
                'unit_price' => $unit_price,
                'unit_discount' => $unit_discount,
                'vat' => $vat,
                'amount' => $amount,
            ]);

            DocumentService::createTransaction(
                $invoice->document,
                [
                    'value' => $invoiceItem->amount,
                    'subject_id' => Subject::whereNotIn('parent_id', [1, 14])->inRandomOrder()->first()->id,
                    'user_id' => $invoice->creator_id,
                    'desc' => $description,
                ]
            );

            DocumentService::createTransaction(
                $invoice->document,
                [
                    'value' => -1 * $invoiceItem->amount,
                    'subject_id' => Subject::whereNotIn('parent_id', [1, 14])->inRandomOrder()->first()->id,
                    'user_id' => $invoice->creator_id,
                    'desc' => $description,
                ],
            );
        });
    }
}
