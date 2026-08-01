<?php

namespace App\Models;

use App\Enums\ChequeType;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChequeHistory extends Model
{
    protected $fillable = [
        'company_id',
        'cheque_id',
        'event',
        'from_status',
        'to_status',
        'actor_id',
        'document_id',
        'payment_id',
        'metadata',
        'occurred_at',
        'reverted_at',
        'reverted_by',
        'desc',
        // Legacy snapshot fields retained for imports.
        'amount',
        'write_date',
        'due_date',
        'serial',
        'status',
        'customer_id',
        'account_id',
        'transaction_id',
        'date',
    ];

    protected $casts = [
        'status' => ChequeType::class,
        'from_status' => ChequeType::class,
        'to_status' => ChequeType::class,
        'metadata' => 'array',
        'occurred_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function revertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }
}
