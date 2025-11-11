<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'itemable_id',
        'itemable_type',
        'invoice_id',
        'quantity',
        'unit_price',
        'unit_discount',
        'cog_after',
        'quantity_at',
        'vat',
        'amount',   // total amount after discount and after vat
        'description',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function itemable()
    {
        return $this->morphTo();
    }
}
