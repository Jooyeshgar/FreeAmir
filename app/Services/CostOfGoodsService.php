<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;

class CostOfGoodsService
{
    /**
     * Update the average cost of products based on invoice items and ancillary costs.
     *
     * This method calculates and updates the average cost for each product in an invoice by considering:
     * - The base cost from the invoice item (excluding VAT)
     * - Associated ancillary costs allocated to the product
     * - The previous cost of goods (COG) from the last invoice containing the product
     *
     * The average cost is calculated using the weighted average formula:
     * Average Cost = (Total Costs + Previous COG * Available Quantity) / (Available Quantity + New Quantity)
     *
     * @param  Invoice  $invoice  The invoice containing the items whose products need cost updates
     * @return void
     *
     * @throws \Exception May throw exceptions related to database operations during save
     */
    public static function updateProductsAverageCost(Invoice $invoice)
    {
        $ancillaryCosts = $invoice->ancillaryCosts;
        if(!is_null($ancillaryCosts)) $ancillaryCosts->loadMissing('items');

        foreach ($invoice->items as $invoiceItem) {
            $product = $invoiceItem->product;
            $availableQuantity = (float) $invoiceItem->quantity_at;
            $totalCosts = $invoiceItem->amount - ($invoiceItem->vat ?? 0); // total cost per product excluding VAT
            $totalCosts += $ancillaryCosts ? $ancillaryCosts->flatMap->items->where('product_id', $product->id)->sum('amount') : 0; // without VAT
            $previousInvoice = self::getPreviousInvoice($invoice, $product->id);

            if ($previousInvoice) {
                $previousInvoiceItem = $previousInvoice->items->where('product_id', $product->id)->first();
                if ($previousInvoiceItem) {
                    $totalCosts += $previousInvoiceItem->cog_after * $availableQuantity;
                }
            }
            $product->average_cost = $totalCosts / ($availableQuantity + $invoiceItem->quantity);
            $product->save();
        }
    }

    /**
     * Calculate gross profit for a sale invoice item.
     */
    public static function calculateGrossProfit(InvoiceItem $invoiceItem)
    {
        $sellingPrice = (float) $invoiceItem->unit_price;
        $cost = (float) ($invoiceItem->cog_after ?? 0);
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

    private static function getPreviousInvoice(Invoice $invoice, $productId)
    {
        return Invoice::where('number', '<', $invoice->number)
            ->where('invoice_type', $invoice->invoice_type)
            ->whereHas('items', fn ($query) => $query->where('product_id', $productId))
            ->orderByDesc('number')->first();
    }

    private static function getNextInvoice(Invoice $invoice, $productId)
    {
        return Invoice::where('number', '>', $invoice->number)
            ->where('invoice_type', $invoice->invoice_type)
            ->whereHas('items', fn ($query) => $query->where('product_id', $productId))
            ->orderByDesc('number')->first();
    }
}
