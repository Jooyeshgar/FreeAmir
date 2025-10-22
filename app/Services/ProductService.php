<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\InvoiceItem;
use App\Models\Product;

class ProductService
{
    public static function updateProductQuantities(array $invoiceItems, InvoiceType $invoice_type, $deletingInvoiceItem = false)
    {
        foreach ($invoiceItems as $invoiceItem) {
            $product = Product::find($invoiceItem['product_id']);
            if (! $deletingInvoiceItem) {
                if ($invoice_type->isBuy()) {
                    $product->quantity += $invoiceItem['quantity'];
                } elseif ($invoice_type->isSell()) {
                    $product->quantity -= $invoiceItem['quantity'];
                }
            } else {
                if ($invoice_type->isBuy()) {
                    $product->quantity -= $invoiceItem['quantity'];
                } elseif ($invoice_type->isSell()) {
                    $product->quantity += $invoiceItem['quantity'];
                }
            }
            $product->update();
        }
    }

    // public static function updateAverageCost(array $products, InvoiceItem $invoiceItem)
    // {
    //     foreach ($products as $product) {
    //         $product = Product::find($product->id);
    //         $previous_total_value = $product->quantity * $product->average_cost;
    //         $new_purchase_value = $invoiceItem->quantity * $invoiceItem->unit_price;

    //         $total_quantity = $product->quantity + $invoiceItem->quantity;
    //         $total_value = $previous_total_value + $new_purchase_value;

    //         $product->quantity = $total_quantity;
    //         $product->average_cost = $total_value / $total_quantity;
    //         dd($product);
    //         $product->update();
    //     }
    // }
}
