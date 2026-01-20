<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'group',
        'sstid',
        'subject_id',
        'selling_price',
        'vat',
        'description',
        'company_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($model) {
            $model->company_id ??= getActiveCompany();
        });
    }

    public function serviceGroup(): BelongsTo
    {
        return $this->belongsTo(ServiceGroup::class, 'group');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function invoiceItems(): MorphMany
    {
        return $this->morphMany(InvoiceItem::class, 'itemable');
    }
}
