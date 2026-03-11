<?php

namespace Database\Factories;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\AncillaryCostService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AncillaryCostFactory extends Factory
{
    protected $model = AncillaryCost::class;

    public function definition(): array
    {
        $invoice = Invoice::withoutGlobalScopes()
            ->where('invoice_type', InvoiceType::BUY)
            ->where('status', InvoiceStatus::APPROVED)
            ->whereHas('items', fn ($q) => $q->where('itemable_type', Product::class))
            ->inRandomOrder()
            ->first();

        if (! $invoice) {
            $invoice = Invoice::factory()->create([
                'invoice_type' => InvoiceType::BUY,
                'status' => InvoiceStatus::APPROVED,
            ]);
            $invoice->refresh();
        }

        return [
            'number' => $this->faker->unique()->numerify('#####'),
            'type' => $this->faker->randomElement(AncillaryCostType::cases()),
            'date' => $invoice->date,
            'company_id' => $invoice->company_id,
            'customer_id' => $invoice->customer_id,
            'status' => $this->faker->randomElement([InvoiceStatus::APPROVED, InvoiceStatus::UNAPPROVED]),
            'vat' => 0,
            'invoice_id' => $invoice->id,
            'amount' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (AncillaryCost $ancillaryCost) {
            $targetApprove = $ancillaryCost->status->isApproved();

            $invoice = $ancillaryCost->invoice;
            $productItems = $invoice->items->where('itemable_type', Product::class)->values();

            if ($productItems->isEmpty()) {
                return;
            }

            $ancillaryCosts = $productItems->map(function ($item) {
                return [
                    'product_id' => $item->itemable_id,
                    'amount' => (float) random_int(50_000, 500_000),
                ];
            })->values()->all();

            $amount = (float) collect($ancillaryCosts)->sum('amount');

            $payload = [
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'company_id' => $invoice->company_id,
                'date' => Carbon::parse($ancillaryCost->date ?? now())->toDateString(),
                'type' => $ancillaryCost->type->value,
                'amount' => $amount,
                'vatPrice' => (float) ($ancillaryCost->vat ?? 0),
                'ancillaryCosts' => $ancillaryCosts,
            ];

            $user = User::find($invoice->creator_id) ?? User::factory()->create();
            auth()->setUser($user);

            $ancillaryCost->updateQuietly(['status' => InvoiceStatus::UNAPPROVED]);

            AncillaryCostService::updateAncillaryCost(
                $user,
                $ancillaryCost,
                $payload,
                false
            );

            $ancillaryCost->refresh();

            $ancillaryCostService = new AncillaryCostService;

            if ($targetApprove) {
                $validation = AncillaryCostService::getChangeStatusValidation($ancillaryCost);
                if ($validation['allowed'] ?? false) {
                    $ancillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'approve');
                    $ancillaryCost->refresh();
                }
            } elseif ($ancillaryCost->status->isApproved()) {
                $validation = AncillaryCostService::getChangeStatusValidation($ancillaryCost);
                if ($validation['allowed'] ?? false) {
                    $ancillaryCostService->changeAncillaryCostStatus($ancillaryCost, 'unapprove');
                    $ancillaryCost->refresh();
                }
            }
        });
    }
}
