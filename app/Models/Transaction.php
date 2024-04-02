<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public $timestamps = true;

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

//    public function subject(): BelongsTo
//    {
//        return $this->belongsTo(Subject::class);
//    }
//
//    public function document(): BelongsTo
//    {
//        return $this->belongsTo(Document::class);
//    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

}
