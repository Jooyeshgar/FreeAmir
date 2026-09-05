<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseTransfer extends Model
{
    protected $fillable = [
        'company_id',
        'product_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'quantity',
        'unit_cost',
        'description',
        'transferred_by',
        'transferred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'transferred_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function (WarehouseTransfer $transfer) {
            $transfer->company_id ??= getActiveCompany();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function transferor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
