<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;

class CostOfGoodsService
{
    /**
     * Update the weighted average cost for a product after a purchase.
     */
    private static function updateWeightedAverageCost(Product $product, float $newQuantity, float $newUnitCost)
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
    private static function setCostAtTimeOfSale(InvoiceItem $invoiceItem)
    {
        $product = $invoiceItem->product;
        $invoiceItem->cost_at_time_of_sale = $product->average_cost ?? 0;
        $invoiceItem->save();
    }

    /**
     * Process invoice items to update product costs based on invoice type.
     */
    public static function processInvoiceCosts(Invoice $invoice, InvoiceType $invoiceType)
    {
        foreach ($invoice->items as $invoiceItem) {
            $product = $invoiceItem->product;
            $unitCost = $invoiceItem->unit_price;

            if ($invoiceType === InvoiceType::BUY) {
                self::updateWeightedAverageCost($product, $invoiceItem->quantity, $unitCost);
            } elseif ($invoiceType === InvoiceType::SELL) {
                self::setCostAtTimeOfSale($invoiceItem);
            }
        }
    }

    /**
     * Update products average cost when ancillary costs added.
     */
    public static function updateProductAverageCostOnAddingAncillaryCost(AncillaryCost $ancillaryCost)
    {
        $invoice = $ancillaryCost->invoice;

        if (! $invoice || $invoice->invoice_type !== InvoiceType::BUY) {
            return;
        }

        $ancillaryCost->loadMissing(['items.product']);

        // Change products average cost based on ancillary cost items
        foreach ($ancillaryCost->items as $item) {
            $product = $item->product;

            if (! $product) {
                return;
            }

            $currentStock = (float) $product->quantity;
            $currentAverageCost = (float) ($product->average_cost ?? 0);
            $currentTotalValue = $currentStock * $currentAverageCost;

            $newTotalValue = $currentTotalValue + $item->amount;
            $newAverageCost = $newTotalValue / $currentStock;

            $product->average_cost = $newAverageCost;
            $product->save();
        }
    }

    /**
     * Reverse update products average cost when ancillary costs edited or deleted.
     */
    public static function reverseUpdateProductAverageCostForAncillaryCost(AncillaryCost $ancillaryCost)
    {
        $ancillaryCost->loadMissing(['items.product']);

        // Revert products average cost based on ancillary cost items
        foreach ($ancillaryCost->items as $item) {
            $product = $item->product;

            if (! $product) {
                return;
            }

            $currentStock = (float) $product->quantity;
            $currentAverageCost = (float) ($product->average_cost ?? 0);
            $currentTotalValue = $currentStock * $currentAverageCost;

            $newTotalValue = max(0, $currentTotalValue - $item->amount);

            $newAverageCost = $newTotalValue / $currentStock;

            $product->average_cost = $newAverageCost;
            $product->save();
        }
    }

    /**
     * Reverse cost updates when deleting an invoice item (for buy invoices).
     */
    public static function reverseCostUpdate(InvoiceItem $invoiceItem)
    {
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
    public static function calculateGrossProfit(InvoiceItem $invoiceItem)
    {
        $sellingPrice = (float) $invoiceItem->unit_price;
        $cost = (float) ($invoiceItem->cost_at_time_of_sale ?? 0);
        $quantity = (float) $invoiceItem->quantity;

        return ($sellingPrice - $cost) * $quantity;
    }

    /**
     * Get the total cost value of current inventory for a product.
     */
    public static function getInventoryValue(Product $product)
    {
        return (float) $product->quantity * (float) ($product->average_cost ?? 0);
    }
}
