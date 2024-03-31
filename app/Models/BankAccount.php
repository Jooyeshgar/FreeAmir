<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{

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

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
