<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Document;
use App\Models\InvoiceItem;
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
                'date' => $date
            ],
            []
        );

        return [
            'code' => $this->faker->unique()->numerify('INV-####'),
            'date' => $date,
            'document_id' => $document->id,
            'customer_id' => Customer::factory(),
            'user_id' => $user->id,
            'addition' => $this->faker->randomFloat(2, 0, 1000),
            'subtraction' => $this->faker->randomFloat(2, 0, 1000),
            'tax' => $this->faker->randomFloat(2, 0, 10),
            'cash_payment' => $this->faker->boolean,
            'ship_date' => $this->faker->optional()->dateTime(),
            'ship_via' => $this->faker->company(),
            'permanent' => $this->faker->boolean,
            'description' => $this->faker->persianSentence(),
            'is_sell' => $this->faker->boolean,
            'active' => true,
            'vat' => $this->faker->randomNumber(5),
            'amount' => $amount,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function ($invoice) {
            $description = $this->faker->persianSentence();

            $invoiceItem = InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'amount' => $invoice->amount,
            ]);

            DocumentService::createTransaction(
                $invoice->document,
                [
                    'value' => $invoiceItem->amount,
                    'subject_id' => Subject::inRandomOrder()->first()->id,
                    'user_id' => $invoice->user_id,
                    'desc' => $description
                ]
            );

            DocumentService::createTransaction(
                $invoice->document,
                [
                    'value' => -1 * $invoiceItem->amount,
                    'subject_id' => Subject::inRandomOrder()->first()->id,
                    'user_id' => $invoice->user_id,
                    'desc' => $description
                ],
            );
        });
    }
}
