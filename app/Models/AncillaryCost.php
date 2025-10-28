<?php

namespace App\Models;

use App\Enums\AncillaryCostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AncillaryCost extends Model
{
    protected $fillable = [
        'type',
        'amount',
        'vat',
        'date',
        'invoice_id',
    ];

    protected $casts = [
        'type' => AncillaryCostType::class,
        'date' => 'date',
        'amount' => 'decimal:2',
        'vat' => 'decimal:2',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope());
    }
    
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AncillaryCostItem::class);
    }
}