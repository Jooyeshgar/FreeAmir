<?php

namespace App\Models;

use App\Models\Concerns\HasFiscalYear;
use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    use HasFiscalYear;

    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
        'desc',
        'type',
        'category',
        'company_id',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }
}
