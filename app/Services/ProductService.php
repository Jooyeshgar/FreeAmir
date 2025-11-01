<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Product;
use App\Models\ProductWebsite;

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

    public static function create(array $validatedData)
    {
        $product = Product::create($validatedData);

        if (isset($validatedData['websites'])) {
            foreach ($validatedData['websites'] as $website) {
                ProductWebsite::create([
                    'link' => $website['link'],
                    'product_id' => $product->id,
                ]);
            }
        }
    }

    public static function update(Product $product, array $validatedData)
    {
        $product->productWebsites()->delete();

        if (isset($validatedData['websites'])) {
            foreach ($validatedData['websites'] as $website) {
                ProductWebsite::create([
                    'link' => $website['link'],
                    'product_id' => $product->id,
                ]);
            }
        }

        $product->update($validatedData);
    }

    public static function delete(Product $product)
    {
        $product->productWebsites()->delete();
        $product->delete();
    }
}
