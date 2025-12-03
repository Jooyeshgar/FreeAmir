<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'sstid',
        'group',
        'location',
        'quantity',
        'quantity_warning',
        'oversell',
        'selling_price',
        'discount_formula',
        'description',
        'company_id',
        'sales_returns_subject_id',
        'income_subject_id',
        'cogs_subject_id',
        'inventory_subject_id',
        'vat',
        'average_cost',
        'income_subject_id',
        'cogs_subject_id',
        'inventory_subject_id',
        'sales_returns_subject_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($product) {
            $product->company_id ??= session('active-company-id');
        });

    }

    // Relationships
    public function productWebsites(): HasMany
    {
        return $this->hasMany(ProductWebsite::class, 'product_id');
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'group');
    }

    public function incomeSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'income_subject_id');
    }

    public function invoiceItems(): MorphMany
    {
        return $this->morphMany(InvoiceItem::class, 'itemable');
    }

    public function salesReturnsSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'sales_returns_subject_id');
    }

    public function cogsSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'cogs_subject_id');
    }

    public function inventorySubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'inventory_subject_id');
    }
}
