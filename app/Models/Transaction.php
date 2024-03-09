<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'Code',
        'Date',
        'Bill',
        'Cust',
        'Addition',
        'Subtraction',
        'Tax',
        'PayableAmnt',
        'CashPayment',
        'ShipDate',
        'FOB',
        'ShipVia',
        'Permanent',
        'Desc',
        'Sell',
        'LastEdit',
        'Acivated',
    ];

    // Define relationships with other models (e.g., Customer)

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'transCust');
    }

    // Define any other methods as needed
}
