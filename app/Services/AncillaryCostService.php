<?php

namespace App\Services;

use App\Enums\AncillaryCostType;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Service for handling ancillary costs.
 * Ancillary costs are additional costs related to purchasing goods such as:
 * - Transportation costs
 * - Insurance
 * - Customs fees
 * - Loading/unloading costs
 *
 * These costs must be distributed across invoice items and added to the average cost.
 */
class AncillaryCostService
{
    /**
     * Create an ancillary cost with its items and distribute the amount across the related products.
     */
    public static function createAncillaryCost(array $data): AncillaryCost
    {
        self::validateAncillaryCostData($data);

        $invoice = Invoice::findOrFail($data['invoice_id']);

        if (! $invoice->invoice_type->isBuy()) {
            throw new \Exception(__('Ancillary costs can only be added to buy invoices'));
        }

        return DB::transaction(function () use ($data, $invoice) {
            $ancillaryCost = AncillaryCost::create([
                'invoice_id' => $invoice->id,
                'company_id' => $data['company_id'],
                'date' => $data['date'] ?? now()->toDateString(),
                'type' => AncillaryCostType::from($data['type']),
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
            ]);

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);

            $ancillaryCost->load(['items.product', 'invoice.items.product']);

            CostOfGoodsService::updateProductAverageCostOnAddingAncillaryCost($ancillaryCost);

            return $ancillaryCost;
        });
    }

    public static function updateAncillaryCost(AncillaryCost $ancillaryCost, array $data): AncillaryCost
    {
        self::validateAncillaryCostData($data);

        $invoice = Invoice::findOrFail($data['invoice_id']);

        if (! $invoice->invoice_type->isBuy()) {
            throw new \Exception(__('Ancillary costs can only be added to buy invoices'));
        }

        return DB::transaction(function () use ($ancillaryCost, $data, $invoice) {
            $ancillaryCost->loadMissing(['items.product', 'invoice.items.product']);

            CostOfGoodsService::reverseUpdateProductAverageCostForAncillaryCost($ancillaryCost);

            $ancillaryCost->items()->delete();

            $ancillaryCost->update([
                'company_id' => $ancillaryCost['company_id'],
                'invoice_id' => $invoice->id,
                'date' => $data['date'] ?? now()->toDateString(),
                'type' => AncillaryCostType::from($data['type']),
                'amount' => $data['amount'],
                'vat' => $data['vatPrice'] ?? 0,
            ]);

            self::syncAncillaryCostItems($ancillaryCost, $data['ancillaryCosts'] ?? []);

            $ancillaryCost->load(['items.product', 'invoice.items.product']);

            CostOfGoodsService::updateProductAverageCostOnAddingAncillaryCost($ancillaryCost);

            return $ancillaryCost;
        });
    }

    /**
     * Delete an ancillary cost and reverse its distribution.
     *
     * @param  int  $ancillaryCostId  The ID of the ancillary cost
     *
     * @throws \Exception
     */
    public static function deleteAncillaryCost(int $ancillaryCostId): void
    {
        $ancillaryCost = AncillaryCost::with(['items.product', 'invoice.items.product'])->findOrFail($ancillaryCostId);

        DB::transaction(function () use ($ancillaryCost) {
            CostOfGoodsService::reverseUpdateProductAverageCostForAncillaryCost($ancillaryCost);

            $ancillaryCost->items()->delete();

            $ancillaryCost->delete();
        });
    }

    /**
     * Calculate the total ancillary costs for an invoice.
     *
     * @param  int  $invoiceId  The invoice ID
     * @return float Total ancillary costs
     */
    public static function getTotalAncillaryCosts(int $invoiceId): float
    {
        return AncillaryCost::where('invoice_id', $invoiceId)->sum('amount');
    }

    private static function syncAncillaryCostItems(AncillaryCost $ancillaryCost, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $payload = collect($items)->map(function (array $item) use ($ancillaryCost) {
            return [
                'company_id' => $ancillaryCost->company_id,
                'product_id' => $item['product_id'],
                'type' => $ancillaryCost->type,
                'amount' => $item['amount'],
            ];
        })->all();

        $ancillaryCost->items()->createMany($payload);
    }

    /**
     * Validate ancillary cost data.
     *
     * @param  array  $data  Data to validate
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private static function validateAncillaryCostData(array $data): void
    {
        $allowedTypes = collect(AncillaryCostType::cases())->map->value->all();

        $validator = Validator::make($data, [
            'invoice_id' => 'required|integer|exists:invoices,id',
            'vatPrice' => 'nullable|numeric|min:0',
            'vatPercentage' => 'nullable|numeric|min:0|max:100',
            'date' => 'required|date',
            'type' => ['required', Rule::in($allowedTypes)],
            'amount' => 'required|numeric|min:0',
            'ancillaryCosts' => 'required|array',
            'ancillaryCosts.*.product_id' => 'required_with:ancillaryCosts|integer|exists:products,id',
            'ancillaryCosts.*.amount' => 'required_with:ancillaryCosts|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }
    }

    /**
     * Get available ancillary cost types.
     *
     * @return array Array of cost types with their labels
     */
    public static function getAncillaryCostTypes(): array
    {
        return collect(AncillaryCostType::cases())->map(function ($type) {
            return [
                'value' => $type->value,
                'label' => $type->label(),
            ];
        })->toArray();
    }
}
