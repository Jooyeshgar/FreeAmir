<?php

namespace App\Models;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceStatus;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AncillaryCost extends Model
{
    protected $fillable = [
        'number',
        'type',
        'amount',
        'vat',
        'date',
        'invoice_id',
        'status',
        'company_id',
        'document_id',
        'customer_id',
    ];

    protected $casts = [
        'type' => AncillaryCostType::class,
        'status' => InvoiceStatus::class,
        'date' => 'date',
        'number' => 'integer',
        'amount' => 'decimal:2',
        'vat' => 'decimal:2',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AncillaryCostItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
