<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $table = 'bankAccounts';

    public $timestamps = false;

    protected $fillable = [
        'name', 
        'number',
        'type',
        'owner',
        'bank_id',  // Foreign key to the Bank model
        'bank_branch',
        'bank_address',
        'bank_phone',
        'bank_web_page',
        'desc'
    ];

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
