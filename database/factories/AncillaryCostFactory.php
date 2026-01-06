<?php

namespace Database\Factories;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\AncillaryCostItem;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Subject;
use App\Models\Transaction;
use App\Services\AncillaryCostService;
use Illuminate\Database\Eloquent\Factories\Factory;

class AncillaryCostFactory extends Factory
{
    protected $model = AncillaryCost::class;

    public function definition(): array
    {
        $invoice = Invoice::withoutGlobalScopes()->where('invoice_type', InvoiceType::BUY)->inRandomOrder()->first();

        return [
            'number' => $this->faker->unique()->numerify('#####'),
            'type' => $this->faker->randomElement(AncillaryCostType::cases()),
            'date' => $invoice->date,
            'company_id' => session('active-company-id') ?? 1,
            'customer_id' => Customer::withoutGlobalScopes()->inRandomOrder()->first()->id,
            'status' => $this->faker->randomElement([InvoiceStatus::APPROVED, InvoiceStatus::UNAPPROVED]),
            'vat' => 0,
            'invoice_id' => $invoice->id,
            'amount' => 0,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (AncillaryCost $ancillaryCost) {
            foreach ($ancillaryCost->invoice->items as $item) {
                if ($item->itemable_type === Product::class) {
                    AncillaryCostItem::factory()->create([
                        'ancillary_cost_id' => $ancillaryCost->id,
                        'product_id' => $item->itemable_id,
                        'type' => $ancillaryCost->type,
                        'amount' => $this->faker->randomFloat(2, 100000, 1000000),
                    ]);
                }
            }

            $amount = $ancillaryCost->items->sum('amount');
            $ancillaryCost->update(['amount' => $amount]);

            if ($ancillaryCost->status->isApproved()) {
                $allow = AncillaryCostService::getChangeStatusValidation($ancillaryCost);

                if ($allow['allowed'] !== true) {
                    $ancillaryCost->update(['status' => InvoiceStatus::UNAPPROVED]);

                    return;
                }

                $document = Document::factory()->create([
                    'number' => (Document::withoutGlobalScopes()->max('number') ?? 0) + 1,
                    'company_id' => session('active-company-id'),
                    'date' => $ancillaryCost->date,
                    'title' => "Invoice Document #{$ancillaryCost->number}",
                    'documentable_type' => AncillaryCost::class,
                    'documentable_id' => $ancillaryCost->id,
                ]);

                $ancillaryCost->update(['document_id' => $document->id]);

                foreach ($ancillaryCost->items as $item) {
                    // item transaction
                    $product = Product::withoutGlobalScopes()->find($item->product_id);

                    Transaction::factory()->create([
                        'document_id' => $document->id,
                        'subject_id' => $product->inventory_subject_id,
                        'desc' => __('Ancillary Cost for :item', ['item' => $product->name]).' '.__('On Invoice').' '.$ancillaryCost->invoice->invoice_type->label().' '.formatDocumentNumber($ancillaryCost->invoice->number),
                        'value' => $item->amount,
                    ]);

                    // customer transaction
                    $subject_id = Subject::withoutGlobalScopes()
                        ->where('subjectable_type', Customer::class)
                        ->where('subjectable_id', $ancillaryCost->customer_id)
                        ->first()
                        ->id;

                    Transaction::factory()->create([
                        'document_id' => $document->id,
                        'subject_id' => $subject_id,
                        'desc' => __('Invoice').' '.$ancillaryCost->invoice->invoice_type->label().' '.__(' with number ').' '.formatDocumentNumber($ancillaryCost->invoice->number),
                        'value' => -$item->amount,
                    ]);
                }
            }
        });
    }
}
