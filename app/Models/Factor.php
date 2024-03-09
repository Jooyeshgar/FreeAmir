<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Factor extends Model
{

    public $timestamps = true;

    protected $fillable = [
        'code',
        'date',
        'bill_id',
        'customer_id',
        'addition',
        'subtraction',
        'tax',
        'cash_payment',
        'ship_date',
        'ship_via',
        'permanent',
        'description',
        'is_sell',
        'active',
        'vat',
        'amount',
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

}
