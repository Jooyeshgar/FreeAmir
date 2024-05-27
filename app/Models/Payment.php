<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'due_date',
        'bank',
        'serial',
        'amount',
        'payer_id',
        'write_date',
        'description',
        'transaction_id',
        'bill_id',
        'track_code',
        'invoice_id',
        'payer_name',
    ];

    public function payer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
