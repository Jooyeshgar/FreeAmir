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
        'income_to',
        'tax_rate',
    ];

    protected $casts = [
        'income_to' => 'decimal:2',
        'tax_rate' => 'decimal:2',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }
}
