<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChequeHistory extends Model
{
    protected $fillable = [
        'cheque_id',
        'amount',
        'write_date',
        'due_date',
        'serial',
        'status',
        'customer_id',
        'account_id',
        'transaction_id',
        'desc',
        'date',
    ];

    public function cheque()
    {
        return $this->belongsTo(Cheque::class, 'cheque_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'account_id');
    }
}
