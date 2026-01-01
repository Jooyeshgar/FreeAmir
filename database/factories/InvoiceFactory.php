<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numerify('#####'),
            'date' => now()->subDays($this->faker->numberBetween(0, 160)),
            'invoice_type' => $this->faker->randomElement([InvoiceType::BUY, InvoiceType::SELL]),
            'customer_id' => Customer::inRandomOrder()->first()->id,
            'creator_id' => User::inRandomOrder()->first()->id,
            'subtraction' => 0,
            'status' => $this->faker->randomElement([InvoiceStatus::APPROVED, InvoiceStatus::UNAPPROVED]),
            'vat' => 0,
            'description' => $this->faker->paragraph(),
            'amount' => 0,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Invoice $invoice) {
            $products = Product::inRandomOrder()->take(random_int(1, 4))->get();

            $items = $products->map(function ($product) use ($invoice) {
                return \App\Models\InvoiceItem::factory()->create([
                    'invoice_id' => $invoice->id,
                    'itemable_id' => $product->id,
                    'itemable_type' => Product::class,
                ]);
            });

            $amount = $items->sum('amount');
            $invoice->update(['amount' => $amount]);

            if ($invoice->status->isApproved()) {
                $document = Document::factory()->create([
                    'number' => (Document::max('number') ?? 0) + 1,
                    'company_id' => session('active-company-id'),
                    'date' => $invoice->date,
                    'title' => "Invoice Document #{$invoice->number}",
                    'documentable_type' => Invoice::class,
                    'documentable_id' => $invoice->id,
                ]);
                $invoice->update(['document_id' => $document->id]);

                foreach ($invoice->items as $item) {
                    $subjectsArray = [$item->itemable->income_subject_id, $item->itemable->sales_returns_subject_id, $item->itemable->cogs_subject_id, $item->itemable->inventory_subject_id];
                    Transaction::factory()->create([
                        'document_id' => $document->id,
                        'subject_id' => $this->faker->randomElement($subjectsArray),
                        'desc' => $item->description,
                        'value' => $item->amount,
                    ]);

                    Transaction::factory()->create([
                        'document_id' => $document->id,
                        'subject_id' => $this->faker->randomElement(Subject::whereIsRoot()->pluck('id')->toArray()),
                        'desc' => $item->description,
                        'value' => -$item->amount,
                    ]);
                }

            }

        });
    }
}
