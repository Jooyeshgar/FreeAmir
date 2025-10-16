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
        static::created(function ($invoice_item) {
            $previous_quantity = $invoice_item->product->quantity;
            $previous_average_cost = $invoice_item->product->average_cost;

            $invoice_type = Invoice::select('invoice_type')->find($invoice_item->invoice_id)->invoice_type;

            $total_quantity = $invoice_type->value == 'buy' ? $previous_quantity + $invoice_item->quantity : $previous_quantity - $invoice_item->quantity;
            $total_value = $previous_quantity * $previous_average_cost + $invoice_item->quantity * $invoice_item->unit_price;

            $new_average_cost = $total_value / $total_quantity;

            $invoice_item->product->quantity = $total_quantity;
            $invoice_item->product->average_cost = $invoice_type->value == 'buy' ? $new_average_cost : $previous_average_cost;
            $invoice_item->product->update();
        });
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
