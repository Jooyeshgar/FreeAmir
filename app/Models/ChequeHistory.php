<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeHistory extends Model
{
    protected $fillable = [
        'cheque_id',
        'created_by',
        'action_type',
        'from_status',
        'to_status',
        'action_at',
        'amount',
        'desc',
    ];

    protected $casts = [
        'action_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
