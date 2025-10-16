<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\Product;

class ProductService
{
    public static function sellable(Product $product, InvoiceItem $invoiceItem)
    {
        if ($product->quantity >= $invoiceItem->quantity) {
            return true;
        }

        return false;
    }

    public static function editQuantity(Product $product, InvoiceItem $invoiceItem)
    {
        $invoice_type = $invoiceItem->invoice_type;
        if ($invoice_type->value == 'buy') {
            $product->quantity += $invoiceItem->quantity;
        } elseif ($invoice_type->value == 'sell') {
            $product->quantity -= $invoiceItem->quantity;
        }
        $product->update();
    }

    public static function updateAverageCost(Product $product, InvoiceItem $invoiceItem)
    {
        $previous_total_value = $product->quantity * $product->average_cost;
        $new_purchase_value = $invoiceItem->quantity * $invoiceItem->unit_price;

        $total_quantity = $product->quantity + $invoiceItem->quantity;
        $total_value = $previous_total_value + $new_purchase_value;

        $product->quantity = $total_quantity;
        $product->average_cost = $total_value / $total_quantity;
        $product->save();
    }
}
