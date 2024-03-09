<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FactorItem extends Model
{
    public $timestamps = true;
    protected $fillable = [
        'factor_id',
        'product_id',
        'transaction_id',
        'quantity',
        'unit_price',
        'unit_discount',
        'vat',
        'description',
    ];

    public function factor()
    {
        return $this->belongsTo(Factor::class, 'TransId');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'factor_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id'); // Assuming factors model is named FactorTable
    }
}

