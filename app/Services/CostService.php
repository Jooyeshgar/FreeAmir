<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;

/**
 * Service for handling product cost calculations including:
 * - Weighted average cost (moving average)
 * - Cost at time of sale
 * - Ancillary cost distribution
 */
class CostService
{
    /**
     * Update the weighted average cost for a product after a purchase.
     *
     * Formula:
     * new_average_cost = (previous_stock * previous_average_cost + new_quantity * new_unit_cost) / total_stock
     *
     * @param  Product  $product  The product to update
     * @param  float  $newQuantity  The quantity being purchased
     * @param  float  $newUnitCost  The unit cost of the new purchase (including any ancillary costs)
     */
    public static function updateWeightedAverageCost(Product $product, float $newQuantity, float $newUnitCost): void
    {
        $previousStock = $product->quantity;
        $previousAverageCost = $product->average_cost ?? 0;

        // If this is the first purchase or stock is zero, use the new cost directly
        if ($previousStock == 0 || $previousAverageCost == 0) {
            $product->average_cost = $newUnitCost;
            $product->save();

            return;
        }

        // Calculate new weighted average
        $previousTotalValue = $previousStock * $previousAverageCost;
        $newPurchaseValue = $newQuantity * $newUnitCost;

        $totalStock = $previousStock + $newQuantity;
        $totalValue = $previousTotalValue + $newPurchaseValue;

        $newAverageCost = $totalValue / $totalStock;

        $product->average_cost = $newAverageCost;
        $product->save();
    }

    /**
     * Update cost_at_time_of_sale for an invoice item (used for sell invoices).
     * This captures the product's average cost at the moment of sale.
     *
     * @param  InvoiceItem  $invoiceItem  The invoice item to update
     */
    public static function setCostAtTimeOfSale(InvoiceItem $invoiceItem): void
    {
        $product = $invoiceItem->product;
        $invoiceItem->cost_at_time_of_sale = $product->average_cost ?? 0;
        $invoiceItem->save();
    }

    /**
     * Process invoice items to update product costs based on invoice type.
     * - For buy invoices: Update weighted average cost
     * - For sell invoices: Set cost_at_time_of_sale
     *
     * @param  Invoice  $invoice  The invoice being processed
     * @param  InvoiceType  $invoiceType  The type of invoice
     */
    public static function processInvoiceCosts(Invoice $invoice, InvoiceType $invoiceType): void
    {
        foreach ($invoice->items as $invoiceItem) {
            $product = $invoiceItem->product;

            if ($invoiceType->isBuy()) {
                // For buy invoices, update weighted average cost
                $unitCost = $invoiceItem->unit_price;
                self::updateWeightedAverageCost($product, $invoiceItem->quantity, $unitCost);
            } elseif ($invoiceType->isSell()) {
                // For sell invoices, capture cost at time of sale
                self::setCostAtTimeOfSale($invoiceItem);
            }
        }
    }

    /**
     * Distribute ancillary cost across invoice items based on their value ratio.
     * The distribution formula:
     * item_share = (item_value / total_invoice_value) * ancillary_cost_amount
     *
     * @param  AncillaryCost  $ancillaryCost  The ancillary cost to distribute
     */
    public static function distributeAncillaryCost(AncillaryCost $ancillaryCost): void
    {
        $invoice = $ancillaryCost->invoice;

        // Only distribute for buy invoices
        if (! $invoice->invoice_type->isBuy()) {
            return;
        }

        $invoiceItems = $invoice->items;

        // Calculate total invoice value (sum of item values)
        $totalInvoiceValue = $invoiceItems->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });

        // Avoid division by zero
        if ($totalInvoiceValue == 0) {
            return;
        }

        // Distribute cost across items
        foreach ($invoiceItems as $invoiceItem) {
            $itemValue = $invoiceItem->quantity * $invoiceItem->unit_price;
            $costShareRatio = $itemValue / $totalInvoiceValue;
            $ancillaryCostShare = $ancillaryCost->amount * $costShareRatio;

            // Calculate new unit cost including ancillary cost share
            $newUnitCost = $invoiceItem->unit_price + ($ancillaryCostShare / $invoiceItem->quantity);

            // Recalculate average cost for the product
            self::recalculateAverageCostWithAncillary(
                $invoiceItem->product,
                $invoiceItem->quantity,
                $ancillaryCostShare
            );
        }
    }

    /**
     * Recalculate product average cost when ancillary cost is added.
     * This adjusts the existing average cost by spreading the additional cost.
     *
     * Formula:
     * new_average = (current_stock * current_average + additional_cost) / current_stock
     *
     * @param  Product  $product  The product to update
     * @param  float  $itemQuantity  The quantity that was purchased (for reference)
     * @param  float  $ancillaryCostShare  The share of ancillary cost for this product
     */
    public static function recalculateAverageCostWithAncillary(
        Product $product,
        float $itemQuantity,
        float $ancillaryCostShare
    ): void {
        $currentStock = $product->quantity;
        $currentAverageCost = $product->average_cost ?? 0;

        if ($currentStock == 0) {
            return;
        }

        // Add the ancillary cost to the total value
        $currentTotalValue = $currentStock * $currentAverageCost;
        $newTotalValue = $currentTotalValue + $ancillaryCostShare;

        $newAverageCost = $newTotalValue / $currentStock;

        $product->average_cost = $newAverageCost;
        $product->save();
    }

    /**
     * Reverse cost updates when deleting an invoice item (for buy invoices).
     * This recalculates the average cost by removing the item's contribution.
     *
     * @param  InvoiceItem  $invoiceItem  The invoice item being deleted
     * @param  InvoiceType  $invoiceType  The type of invoice
     */
    public static function reverseCostUpdate(InvoiceItem $invoiceItem, InvoiceType $invoiceType): void
    {
        if (! $invoiceType->isBuy()) {
            return;
        }

        $product = $invoiceItem->product;
        $currentStock = $product->quantity;
        $currentAverageCost = $product->average_cost ?? 0;

        // If removing this would leave zero stock, reset to zero
        if ($currentStock == 0) {
            $product->average_cost = 0;
            $product->save();

            return;
        }

        // Calculate what the total value would be without this purchase
        $currentTotalValue = $currentStock * $currentAverageCost;
        $itemValue = $invoiceItem->quantity * $invoiceItem->unit_price;
        $newTotalValue = $currentTotalValue - $itemValue;

        // Recalculate average (will be done after quantity is updated)
        // We don't update here because quantity hasn't been adjusted yet
        // This is a placeholder for potential future use
    }

    /**
     * Calculate gross profit for a sale invoice item.
     * Profit = (selling_price - cost_at_time_of_sale) * quantity
     *
     * @param  InvoiceItem  $invoiceItem  The invoice item
     * @return float The gross profit
     */
    public static function calculateGrossProfit(InvoiceItem $invoiceItem): float
    {
        $sellingPrice = $invoiceItem->unit_price;
        $cost = $invoiceItem->cost_at_time_of_sale ?? 0;
        $quantity = $invoiceItem->quantity;

        return ($sellingPrice - $cost) * $quantity;
    }

    /**
     * Get the total cost value of current inventory for a product.
     *
     * @param  Product  $product  The product
     * @return float Total inventory value
     */
    public static function getInventoryValue(Product $product): float
    {
        return $product->quantity * ($product->average_cost ?? 0);
    }
}
