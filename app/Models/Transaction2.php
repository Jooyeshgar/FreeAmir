<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction2 extends Model
{
    protected $fillable = [
        'code',
        'date',
        'bill',
        'customer_id',
        'addition',
        'subtraction',
        'tax',
        'payable_amount',
        'cash_payment',
        'ship_date',
        'destination',
        'ship_via',
        'permanent',
        'description',
        'sell',
        'activated',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer');
    }
}
