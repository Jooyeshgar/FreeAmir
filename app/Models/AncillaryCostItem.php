<?php

namespace App\Models;

use App\Enums\AncillaryCostType;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AncillaryCostItem extends Model
{
    protected $fillable = [
        'ancillary_cost_id',
        'product_id',
        'type',
        'amount',
    ];

    protected $casts = [
        'type' => AncillaryCostType::class,
        'amount' => 'decimal:2',
    ];

    public function ancillaryCost(): BelongsTo
    {
        return $this->belongsTo(AncillaryCost::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
