<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    private const BUY_SERVICES_ONLY_MARKER = '[BUY_SERVICES_ONLY]';

    private const SELL_SERVICES_ONLY_MARKER = '[SELL_SERVICES_ONLY]';

    public function definition(): array
    {
        $customer = Customer::withoutGlobalScopes()->inRandomOrder()->first() ?? Customer::factory()->withGroup()->withSubject()->create();
        $creator = User::inRandomOrder()->first() ?? User::factory()->create();

        return [
            'number' => $this->faker->unique()->numerify('#####'),
            'date' => $this->faker->dateTimeBetween(now()->startOfYear(), now()->endOfYear()),
            'invoice_type' => $this->faker->randomElement([InvoiceType::BUY, InvoiceType::SELL]),
            'customer_id' => $customer->id,
            'creator_id' => $creator->id,
            'subtraction' => 0,
            'status' => $this->faker->randomElement([InvoiceStatus::APPROVED, InvoiceStatus::UNAPPROVED]),
            'vat' => 0,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'amount' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Invoice $invoice) {
            $targetApprove = $invoice->status->isApproved();

            $isBuyServicesOnly = Str::startsWith((string) $invoice->title, self::BUY_SERVICES_ONLY_MARKER);
            $isSellServicesOnly = Str::startsWith((string) $invoice->title, self::SELL_SERVICES_ONLY_MARKER);
            $isServicesOnly = $isBuyServicesOnly || $isSellServicesOnly;

            $creator = User::find($invoice->creator_id) ?? User::factory()->create();
            auth()->setUser($creator);

            $customer = Customer::withoutGlobalScopes()->find($invoice->customer_id);
            if (! $customer || ! $customer->subject) {
                $customer = Customer::factory()->withGroup()->withSubject()->create([
                    'company_id' => $invoice->company_id,
                ]);
                $invoice->updateQuietly(['customer_id' => $customer->id]);
            }

            $items = [];

            if (! $isServicesOnly) {
                $products = Product::withoutGlobalScopes()->where('company_id', $invoice->company_id)
                    ->inRandomOrder()->take(random_int(1, 4))->get();

                if ($products->isEmpty()) {
                    $products = collect([
                        Product::factory()->withGroup()->withSubjects()->create([
                            'company_id' => $invoice->company_id,
                        ]),
                    ]);
                }

                $items = $products->map(function (Product $product) {
                    $quantity = random_int(3, 10);
                    $unit = (float) $this->faker->numberBetween(1_000_000, 4_000_000);

                    return [
                        'itemable_type' => 'product',
                        'itemable_id' => $product->id,
                        'quantity' => $quantity,
                        'unit' => $unit,
                        'unit_discount' => 0,
                        'vat' => 0,
                        'vat_is_value' => false,
                    ];
                })->values()->all();
            }

            if ($invoice->invoice_type === InvoiceType::SELL || $isServicesOnly) {
                $services = Service::withoutGlobalScopes()->where('company_id', $invoice->company_id)
                    ->whereNotNull('subject_id')
                    ->whereNotNull('cogs_subject_id')
                    ->inRandomOrder()->take($isServicesOnly ? random_int(2, 6) : random_int(0, 2))->get();

                if ($services->isEmpty()) {
                    $services = collect([
                        Service::factory()->withGroup()->withSubject()->create([
                            'company_id' => $invoice->company_id,
                        ]),
                    ]);
                }

                $serviceItems = $services->map(function (Service $service) {
                    $unit = (float) $this->faker->numberBetween(100_000, 1_500_000);

                    return [
                        'itemable_type' => 'service',
                        'itemable_id' => $service->id,
                        'quantity' => 1,
                        'unit' => $unit,
                        'unit_discount' => 0,
                        'vat' => 0,
                        'vat_is_value' => false,
                    ];
                })->values()->all();

                $items = array_merge($items, $serviceItems);
            }

            $totalVat = 0.0;
            $totalAmount = 0.0;

            foreach ($items as $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit'] ?? 0);
                $unitDiscount = (float) ($item['unit_discount'] ?? 0);
                $vat = (float) ($item['vat'] ?? 0);
                $lineAmount = $quantity * $unitPrice - $unitDiscount + $vat;

                if ($item['itemable_type'] === 'product') {
                    $product = Product::withoutGlobalScopes()->find($item['itemable_id']);
                    if ($product && $invoice->invoice_type === InvoiceType::SELL && $targetApprove) {
                        if ($product->quantity < $quantity) {
                            $product->updateQuietly(['quantity' => $quantity + random_int(1, 5)]);
                        }
                    }
                }

                InvoiceItem::updateOrCreate(
                    [
                        'invoice_id' => $invoice->id,
                        'itemable_id' => $item['itemable_id'],
                        'itemable_type' => $item['itemable_type'] === 'product' ? Product::class : Service::class,
                    ],
                    [
                        'invoice_id' => $invoice->id,
                        'itemable_id' => $item['itemable_id'],
                        'itemable_type' => $item['itemable_type'] === 'product' ? Product::class : Service::class,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'unit_discount' => $unitDiscount,
                        'vat' => $vat,
                        'amount' => $lineAmount,
                        'description' => null,
                        'quantity_at' => 0,
                        'cog_after' => 0,
                    ]
                );

                $totalVat += $vat;
                $totalAmount += $lineAmount;
            }

            $invoice->updateQuietly([
                'status' => InvoiceStatus::UNAPPROVED,
                'vat' => $totalVat,
                'amount' => $totalAmount,
            ]);

            $invoice->refresh();

            $invoiceService = new InvoiceService;

            if ($targetApprove) {
                $decision = InvoiceService::getChangeStatusValidation($invoice);
                if ($decision->canProceed) {
                    $invoiceService->changeInvoiceStatus($invoice, 'approved');
                    $invoice->refresh();
                }
            } elseif ($invoice->status->isApproved()) {
                $decision = InvoiceService::getChangeStatusValidation($invoice);
                if ($decision->canProceed) {
                    $invoiceService->changeInvoiceStatus($invoice, 'unapproved');
                    $invoice->refresh();
                }
            }
        });
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::APPROVED,
        ]);
    }

    public function unapproved(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::UNAPPROVED,
        ]);
    }

    public function buyServicesOnly(): static
    {
        return $this->state(fn () => [
            'invoice_type' => InvoiceType::BUY,
            'title' => self::BUY_SERVICES_ONLY_MARKER.' '.$this->faker->sentence(3),
        ]);
    }

    public function sellServicesOnly(): static
    {
        return $this->state(fn () => [
            'invoice_type' => InvoiceType::SELL,
            'title' => self::SELL_SERVICES_ONLY_MARKER.' '.$this->faker->sentence(3),
        ]);
    }
}
