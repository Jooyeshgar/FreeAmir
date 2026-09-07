<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseProductStock extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_id',
        'quantity',
        'average_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'average_cost' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
