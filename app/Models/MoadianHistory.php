<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoadianHistory extends Model
{
    protected $fillable = [
        'data',
        'invoice_id',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
