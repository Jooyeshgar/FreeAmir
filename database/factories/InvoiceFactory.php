<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CostOfGoodsService;
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
            'customer_id' => Customer::withoutGlobalScopes()->inRandomOrder()->first()->id,
            'creator_id' => User::inRandomOrder()->first()->id,
            'subtraction' => 0,
            'status' => $this->faker->randomElement([InvoiceStatus::APPROVED, InvoiceStatus::UNAPPROVED]),
            'vat' => 0,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'amount' => 0,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Invoice $invoice) {
            $products = Product::withoutGlobalScopes()->inRandomOrder()->take(random_int(1, 4))->get();

            $productItems = $products->map(function ($product) use ($invoice) {
                return InvoiceItem::factory()->create([
                    'invoice_id' => $invoice->id,
                    'itemable_id' => $product->id,
                    'itemable_type' => Product::class,
                ]);
            });

            $serviceItems = collect();
            if ($invoice->invoice_type === InvoiceType::SELL) {
                $services = Service::withoutGlobalScopes()->inRandomOrder()->take(random_int(0, 2))->get();

                $serviceItems = $services->map(function ($service) use ($invoice) {
                    return InvoiceItem::factory()->create([
                        'invoice_id' => $invoice->id,
                        'itemable_id' => $service->id,
                        'itemable_type' => Service::class,
                    ]);
                });
            }

            $items = $productItems->concat($serviceItems);

            if ($invoice->invoice_type === InvoiceType::BUY) {
                AncillaryCost::factory()->count(rand(1, 3))->create([
                    'invoice_id' => $invoice->id,
                    'customer_id' => $invoice->customer_id,
                    'status' => $invoice->status,
                ]);
            }

            $amount = $items->sum('amount') + $invoice->ancillaryCosts->sum('amount');
            $invoice->update(['amount' => $amount]);

            if ($invoice->status->isApproved()) {
                $document = Document::factory()->create([
                    'number' => (Document::withoutGlobalScopes()->max('number') ?? 0) + 1,
                    'company_id' => $invoice->company_id,
                    'date' => $invoice->date,
                    'title' => "Invoice Document #{$invoice->number}",
                    'documentable_type' => Invoice::class,
                    'documentable_id' => $invoice->id,
                ]);
                $invoice->update(['document_id' => $document->id]);

                foreach ($invoice->items as $item) {
                    // item transaction
                    $product = null;
                    $service = null;

                    if ($item->itemable_type === Product::class) {
                        $product = Product::withoutGlobalScopes()->find($item->itemable_id);
                    } else {
                        $service = Service::withoutGlobalScopes()->find($item->itemable_id);
                    }

                    if ($product) {
                        if ($invoice->invoice_type === InvoiceType::SELL) {
                            $product->quantity = $product->quantity - $item->quantity < 0 ? 0 : $product->quantity - $item->quantity;
                            $product->save();
                        } else {
                            $product->quantity = $product->quantity + $item->quantity;
                            $product->save();
                            $item->save(['quantity_at' => $product->quantity]);
                            CostOfGoodsService::updateProductsAverageCost($invoice);
                            $item->save(['cog_after' => $product->average_cost]);

                            foreach ($invoice->ancillaryCosts as $cost) {
                                if ($cost->status->isApproved()) {
                                    CostOfGoodsService::updateProductsAverageCost($invoice);
                                    foreach ($invoice->items as $item) {
                                        $item->cog_after = $product->average_cost;
                                        $item->update();
                                    }
                                }
                            }
                        }
                    }

                    if ($invoice->invoice_type === InvoiceType::SELL) {
                        Transaction::factory()->create([
                            'document_id' => $invoice->document->id,
                            'subject_id' => $product->income_subject_id ?? $service->subject_id,
                            'desc' => __('Invoice').' '.$invoice->invoice_type->label().' '.__(' with number ').' '.formatNumber($invoice->number).' ('.formatNumber($item->quantity).' '.__('Number').')',
                            'value' => $item->unit_price * $item->quantity,
                        ]);
                    }

                    if ($item->itemable_type === Product::class) {
                        Transaction::factory()->create([
                            'document_id' => $invoice->document->id,
                            'subject_id' => $product->inventory_subject_id,
                            'desc' => __('Invoice').' '.$invoice->invoice_type->label().' '.__(' with number ').' '.formatNumber($invoice->number).' ('.formatNumber($item->quantity).' '.__('Number').')',
                            'value' => $invoice->invoice_type === InvoiceType::SELL ? ($product->average_cost * $item->quantity) : -($item->unit_price * $item->quantity),
                        ]);
                    }

                    // cogs transaction
                    if ($item->itemable_type === Product::class && $invoice->invoice_type === InvoiceType::BUY) {
                        Transaction::factory()->create([
                            'document_id' => $invoice->document->id,
                            'subject_id' => $product->cogs_subject_id,
                            'desc' => __('Cost of Goods Sold').' '.__('Invoice').' '.$invoice->invoice_type->label().' '.__(' with number ').' '.formatNumber($invoice->number),
                            'value' => -$product->average_cost * $item->quantity,
                        ]);
                    }
                }

                // customer transaction
                $subject_id = Subject::withoutGlobalScopes()
                    ->where('subjectable_type', Customer::class)
                    ->where('subjectable_id', $invoice->customer_id)
                    ->first()
                    ->id;

                Transaction::factory()->create([
                    'document_id' => $invoice->document->id,
                    'subject_id' => $subject_id,
                    'desc' => __('Invoice').' '.$invoice->invoice_type->label().' '.__(' with number ').' '.formatNumber($invoice->number),
                    'value' => $invoice->invoice_type === InvoiceType::SELL ? -$invoice->amount : $invoice->amount,
                ]);
            }
        });
    }
}
