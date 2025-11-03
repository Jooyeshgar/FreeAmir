<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

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
        'purchace_price',
        'selling_price',
        'discount_formula',
        'description',
        'company_id',
        'return_sales_subject_id',
        'income_subject_id',
        'cogs_subject_id',
        'inventory_subject_id',
        'vat',
        'average_cost',
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

    public function returnSalesSubject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'return_sales_subject_id');
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
