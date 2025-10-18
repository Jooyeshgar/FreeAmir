<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'transaction_id',
        'quantity',
        'unit_price',
        'unit_discount',
        'vat',
        'amount',
        'description',
    ];

    public static function booted(): void
    {
        static::created(function (InvoiceItem $invoiceItem) {
            $invoiceItem->load('product', 'invoice');
            $invoice_type = $invoiceItem->invoice->invoice_type;
            if ($invoice_type->value == 'buy') {
                $invoiceItem->product->quantity += $invoiceItem->quantity;
            } elseif ($invoice_type->value == 'sell') {
                $invoiceItem->product->quantity -= $invoiceItem->quantity;
            }
            $invoiceItem->product->update();
        });
        static::updated(function (InvoiceItem $invoiceItem) {
            $invoiceItem->load('product', 'invoice');
            $invoice_type = $invoiceItem->invoice->invoice_type;
            if ($invoice_type->value == 'buy') {
                $invoiceItem->product->quantity += $invoiceItem->quantity;
            } elseif ($invoice_type->value == 'sell') {
                $invoiceItem->product->quantity -= $invoiceItem->quantity;
            }
            $invoiceItem->product->update();
        });
        // static::deleted(function (InvoiceItem $invoiceItem) {
        //     $invoiceItem->load('product', 'invoice');
        //     $invoice_type = $invoiceItem->invoice->invoice_type;
        //     dd($invoiceItem->product);
        //     if ($invoice_type->value == 'buy') {
        //         $invoiceItem->product->quantity -= $invoiceItem->quantity;
        //     } elseif ($invoice_type->value == 'sell') {
        //         $invoiceItem->product->quantity += $invoiceItem->quantity;
        //     }
        //     $invoiceItem->product->update();
        // });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id'); // Assuming invoices model is named InvoiceTable
    }
}
