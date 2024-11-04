<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class Cheque extends Model
{
    protected $fillable = [
        'amount',
        'wrt_date',
        'due_date',
        'serial',
        'status',
        'customer_id',
        'bank_account_id',
        'transaction_id',
        'notebook_id',
        'desc',
        'history_id',
        'bill_id',
        'order',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope());
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function account()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function notebook()
    {
        return $this->belongsTo(Notebook::class);
    }

    public function history()
    {
        return $this->belongsTo(ChequeHistory::class, 'history_id');
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
