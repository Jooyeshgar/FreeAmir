<?php

namespace App\Models;

use App\Enums\ChequeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeHistory extends Model
{
    protected $fillable = [
        'cheque_id',
        'from_status',
        'to_status',
        'user_id',
        'document_id',
        'payment_id',
        'bank_service_fee',
        'desc',
    ];

    protected $casts = [
        'from_status' => ChequeType::class,
        'to_status' => ChequeType::class,
        'bank_service_fee' => 'decimal:2',
    ];

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
