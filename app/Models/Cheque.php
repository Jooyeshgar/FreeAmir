<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cheque extends Model
{
    protected $fillable = [
        'amount',
        'written_at',
        'due_date',
        'serial',
        'cheque_number',
        'sayad_number',
        'is_received',
        'desc',
        'customer_id',
        'transaction_id',
        'cheque_book_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'written_at' => 'date',
        'due_date' => 'date',
        'cheque_number' => 'string',
        'sayad_number' => 'string',
        'is_received' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function chequeBook(): BelongsTo
    {
        return $this->belongsTo(ChequeBook::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ChequeHistory::class);
    }

    public function latestHistory()
    {
        return $this->hasOne(ChequeHistory::class)->latestOfMany('action_at');
    }
}
