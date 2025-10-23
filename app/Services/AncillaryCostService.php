<?php

namespace App\Services;

use App\Enums\AncillaryCostType;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
     * Create an ancillary cost and distribute it across invoice items.
     *
     * @param  array  $data  Ancillary cost data including invoice_id, amount, description, date
     * @return AncillaryCost The created ancillary cost
     *
     * @throws \Exception
     */
    public static function createAncillaryCost(array $data): AncillaryCost
    {
        // Validate data
        self::validateAncillaryCostData($data);

        $invoice = Invoice::findOrFail($data['invoice_id']);

        // Only sell invoices can have ancillary costs
        if (! $invoice->invoice_type->isSell()) {
            throw new \Exception(__('Ancillary costs can only be added to sales invoices'));
        }

        $ancillaryCost = null;

        DB::transaction(function () use ($data, &$ancillaryCost) {
            // Create the ancillary cost record
            $ancillaryCost = AncillaryCost::create([
                'invoice_id' => $data['invoice_id'],
                'product_id' => $data['product_id'] ?? null,
                'description' => $data['description'],
                'amount' => $data['amount'],
                'date' => $data['date'] ?? now()->toDateString(),
            ]);

            // Distribute the cost across invoice items
            CostService::distributeAncillaryCost($ancillaryCost);
        });

        return $ancillaryCost;
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
        $ancillaryCost = AncillaryCost::findOrFail($ancillaryCostId);

        DB::transaction(function () use ($ancillaryCost) {
            // Reverse the distribution
            self::reverseAncillaryCostDistribution($ancillaryCost);

            // Delete the ancillary cost
            $ancillaryCost->delete();
        });
    }

    /**
     * Reverse the distribution of an ancillary cost.
     * This subtracts the distributed cost from product average costs.
     *
     * @param  AncillaryCost  $ancillaryCost  The ancillary cost to reverse
     */
    private static function reverseAncillaryCostDistribution(AncillaryCost $ancillaryCost): void
    {
        $invoice = $ancillaryCost->invoice;
        $invoiceItems = $invoice->items;

        // Calculate total invoice value
        $totalInvoiceValue = $invoiceItems->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });

        if ($totalInvoiceValue == 0) {
            return;
        }

        // Reverse distribution for each item
        foreach ($invoiceItems as $invoiceItem) {
            $itemValue = $invoiceItem->quantity * $invoiceItem->unit_price;
            $costShareRatio = $itemValue / $totalInvoiceValue;
            $ancillaryCostShare = $ancillaryCost->amount * $costShareRatio;

            // Subtract the ancillary cost from average
            $product = $invoiceItem->product;
            $currentStock = $product->quantity;
            $currentAverageCost = $product->average_cost ?? 0;

            if ($currentStock > 0) {
                $currentTotalValue = $currentStock * $currentAverageCost;
                $newTotalValue = $currentTotalValue - $ancillaryCostShare;
                $newAverageCost = $newTotalValue / $currentStock;

                $product->average_cost = max(0, $newAverageCost); // Don't allow negative
                $product->save();
            }
        }
    }

    /**
     * Get all ancillary costs for an invoice.
     *
     * @param  int  $invoiceId  The invoice ID
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getInvoiceAncillaryCosts(int $invoiceId)
    {
        return AncillaryCost::where('invoice_id', $invoiceId)->get();
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

    /**
     * Validate ancillary cost data.
     *
     * @param  array  $data  Data to validate
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private static function validateAncillaryCostData(array $data): void
    {
        $validator = Validator::make($data, [
            'invoice_id' => 'required|integer|exists:invoices,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'description' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0',
            'date' => 'nullable|date',
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
