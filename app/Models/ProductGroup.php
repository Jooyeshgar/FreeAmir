<?php

namespace App\Models;

use App\Enums\ConfigTitle;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'sstid',
        'buyId',
        'sellId',
        'vat',
        'company_id',
        'return_sales_subject_id',
        'income_subject_id',
        'cogs_subject_id',
        'inventory_subject_id',
    ];

    protected $attributes = [
        'vat' => 0,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($model) {
            $model->company_id ??= session('active-company-id');
        });

        // subject lifecycle handled via ProductGroupSubjectService outside the model
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'group', 'id');
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