<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxSlab extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'year',
        'slab_order',
        'income_from',
        'income_to',
        'tax_rate',
        'annual_exemption',
    ];

    protected $casts = [
        'year' => 'integer',
        'slab_order' => 'integer',
        'income_from' => 'decimal:2',
        'income_to' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'annual_exemption' => 'decimal:2',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }
}
