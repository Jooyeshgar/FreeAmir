<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\AncillaryCostItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;

class CostOfGoodsService
{
    /**
     * Update the weighted average cost for a product after a purchase.
     */
    public static function updateWeightedAverageCost(Product $product, float $newQuantity, float $newUnitCost): void
    {
        $previousStock = (float) $product->quantity;
        $previousAverageCost = (float) ($product->average_cost ?? 0);

        if ($previousStock == 0 || $previousAverageCost == 0) {
            $product->average_cost = $newUnitCost;
            $product->save();

            return;
        }

        $previousTotalValue = $previousStock * $previousAverageCost;
        $newPurchaseValue = $newQuantity * $newUnitCost;

        $totalStock = $previousStock + $newQuantity;

        if ($totalStock <= 0) {
            $product->average_cost = 0;
            $product->save();

            return;
        }

        $totalValue = $previousTotalValue + $newPurchaseValue;
        $newAverageCost = $totalValue / $totalStock;

        $product->average_cost = $newAverageCost;
        $product->save();
    }

    /**
     * Capture the product's average cost at the moment of sale.
     */
    public static function setCostAtTimeOfSale(InvoiceItem $invoiceItem): void
    {
        $product = $invoiceItem->product;
        $invoiceItem->cost_at_time_of_sale = $product->average_cost ?? 0;
        $invoiceItem->save();
    }

    /**
     * Process invoice items to update product costs based on invoice type.
     */
    public static function processInvoiceCosts(Invoice $invoice, InvoiceType $invoiceType): void
    {
        foreach ($invoice->items as $invoiceItem) {
            $product = $invoiceItem->product;
            $unitCost = $invoiceItem->unit_price;

            if ($invoiceType->isBuy()) {
                self::updateWeightedAverageCost($product, $invoiceItem->quantity, $unitCost);
            } elseif ($invoiceType->isSell()) {
                self::updateWeightedAverageCost($product, -1 * $invoiceItem->quantity, $unitCost);
                self::setCostAtTimeOfSale($invoiceItem);
            }
        }
    }

    /**
     * Apply ancillary cost to products based on specific cost items.
     */
    public static function distributeAncillaryCost(AncillaryCost $ancillaryCost): void
    {
        $invoice = $ancillaryCost->invoice;

        if (! $invoice || ! $invoice->invoice_type->isBuy()) {
            return;
        }

        $ancillaryCost->loadMissing(['items.product']);

        $ancillaryCost->items
            ->groupBy('product_id')
            ->each(function ($items) {
                $first = $items->first();
                $product = $first?->product;

                if (! $product) {
                    return;
                }

                $totalShare = $items->sum(function (AncillaryCostItem $item) {
                    return (float) $item->amount + (float) ($item->vat ?? 0);
                });

                self::applyAncillaryShare($product, $totalShare);
            });
    }

    /**
     * Reverse ancillary cost distribution by removing applied shares.
     */
    public static function reverseAncillaryCostDistribution(AncillaryCost $ancillaryCost): void
    {
        $ancillaryCost->loadMissing(['items.product']);

        $ancillaryCost->items
            ->groupBy('product_id')
            ->each(function ($items) {
                $product = $items->first()?->product;

                if (! $product) {
                    return;
                }

                $totalShare = $items->sum(function (AncillaryCostItem $item) {
                    return (float) $item->amount + (float) ($item->vat ?? 0);
                });

                self::removeAncillaryShare($product, $totalShare);
            });
    }

    /**
     * Apply ancillary cost share to the product's average cost.
     */
    public static function applyAncillaryShare(?Product $product, float $share): void
    {
        if (! $product || $share <= 0) {
            return;
        }

        $currentStock = (float) $product->quantity;

        if ($currentStock <= 0) {
            return;
        }

        $currentAverageCost = (float) ($product->average_cost ?? 0);
        $currentTotalValue = $currentStock * $currentAverageCost;
        $newTotalValue = $currentTotalValue + $share;
        $newAverageCost = $newTotalValue / $currentStock;

        $product->average_cost = $newAverageCost;
        $product->save();
    }

    /**
     * Remove ancillary cost share from the product's average cost.
     */
    public static function removeAncillaryShare(?Product $product, float $share): void
    {
        if (! $product || $share <= 0) {
            return;
        }

        $currentStock = (float) $product->quantity;

        if ($currentStock <= 0) {
            return;
        }

        $currentAverageCost = (float) ($product->average_cost ?? 0);
        $currentTotalValue = $currentStock * $currentAverageCost;
        $newTotalValue = max(0, $currentTotalValue - $share);
        $newAverageCost = $currentStock > 0 ? $newTotalValue / $currentStock : 0;

        $product->average_cost = max(0, $newAverageCost);
        $product->save();
    }

    /**
     * Reverse cost updates when deleting an invoice item (for buy and sell invoices).
     */
    public static function reverseCostUpdate(InvoiceItem $invoiceItem, InvoiceType $invoiceType): void
    {
        if (! $invoiceType->isBuy() && ! $invoiceType->isSell()) {
            return;
        }

        $product = $invoiceItem->product;

        if (! $product) {
            return;
        }

        $currentStock = (float) $product->quantity;
        $currentAverageCost = (float) ($product->average_cost ?? 0);

        if ($currentStock == 0) {
            $product->average_cost = 0;
            $product->save();

            return;
        }

        $currentTotalValue = $currentStock * $currentAverageCost;
        $itemValue = (float) $invoiceItem->quantity * (float) $invoiceItem->unit_price;
        $newTotalValue = $currentTotalValue - $itemValue;
        $newAverageCost = $newTotalValue / $currentStock;

        $product->average_cost = $newAverageCost;
        $product->save();
    }

    /**
     * Calculate gross profit for a sale invoice item.
     */
    public static function calculateGrossProfit(InvoiceItem $invoiceItem): float
    {
        $sellingPrice = (float) $invoiceItem->unit_price;
        $cost = (float) ($invoiceItem->cost_at_time_of_sale ?? 0);
        $quantity = (float) $invoiceItem->quantity;

        return ($sellingPrice - $cost) * $quantity;
    }

    /**
     * Get the total cost value of current inventory for a product.
     */
    public static function getInventoryValue(Product $product): float
    {
        return (float) $product->quantity * (float) ($product->average_cost ?? 0);
    }
}