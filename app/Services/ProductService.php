<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\InvoiceItem;
use App\Models\Product;

class ProductService
{
    /**
     * Update product quantities based on invoice type and items.
     *
     * @param  array  $invoiceItems  Array of invoice items with product_id and quantity
     * @param  InvoiceType  $invoice_type  Type of invoice (buy/sell/return_buy/return_sell)
     * @param  bool  $deletingInvoiceItem  Whether we're deleting items (reverse operation)
     * @return void
     */
    public static function updateProductQuantities(array $invoiceItems, InvoiceType $invoice_type, bool $deletingInvoiceItem = false)
    {
        foreach ($invoiceItems as $invoiceItem) {
            $product = Product::find($invoiceItem['product_id']);
            if (! $product) {
                continue;
            }

            if (! $deletingInvoiceItem) {
                if ($invoice_type->isBuy()) {
                    $product->quantity += $invoiceItem['quantity'];
                } elseif ($invoice_type->isSell()) {
                    $product->quantity -= $invoiceItem['quantity'];
                }
            } else {
                // Reverse the operation when deleting
                if ($invoice_type->isBuy()) {
                    $product->quantity -= $invoiceItem['quantity'];
                } elseif ($invoice_type->isSell()) {
                    $product->quantity += $invoiceItem['quantity'];
                }
            }
            $product->save();
        }
    }

    /**
     * Update average cost for a product based on invoice item.
     * This method delegates to CostService for actual calculations.
     *
     * @param  Product  $product  The product to update
     * @param  InvoiceItem  $invoiceItem  The invoice item
     * @param  InvoiceType  $invoiceType  Type of invoice
     * @param  bool  $deletingInvoiceItem  Whether we're deleting the item
     * @return void
     *
     * @deprecated Use CostService methods directly instead
     */
    public static function updateAverageCost(
        Product $product,
        InvoiceItem $invoiceItem,
        InvoiceType $invoiceType,
        bool $deletingInvoiceItem = false
    ): void {
        if ($deletingInvoiceItem) {
            CostService::reverseCostUpdate($invoiceItem, $invoiceType);

            return;
        }

        if ($invoiceType->isBuy()) {
            // For buy invoices, update weighted average cost
            CostService::updateWeightedAverageCost(
                $product,
                $invoiceItem->quantity,
                $invoiceItem->unit_price
            );
        } elseif ($invoiceType->isSell()) {
            // For sell invoices, set cost at time of sale
            CostService::setCostAtTimeOfSale($invoiceItem);
        }
    }
}
