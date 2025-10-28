<?php

namespace App\Models;

use App\Enums\AncillaryCostType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AncillaryCostItem extends Model
{
    protected $fillable = [
        'ancillary_cost_id',
        'product_id',
        'type',
        'amount',
        'vat',
    ];

    protected $casts = [
        'type' => AncillaryCostType::class,
        'amount' => 'decimal:2',
        'vat' => 'decimal:2',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope());
    }

    public function ancillaryCost(): BelongsTo
    {
        return $this->belongsTo(AncillaryCost::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}