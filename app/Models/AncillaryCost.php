<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AncillaryCost extends Model
{
    protected $fillable = [
        'description',
        'amount',
        'date',
        'invoice_id',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
