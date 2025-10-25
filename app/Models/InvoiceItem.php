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
        'cost_at_time_of_sale',
        'vat',
        'amount',
        'description',
    ];

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
