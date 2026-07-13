<?php

namespace App\Models;

use App\Enums\PayrollElementCalcType;
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
        'calc_type',
    ];

    protected $casts = [
        'income_to' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'calc_type' => PayrollElementCalcType::class,
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }
}
