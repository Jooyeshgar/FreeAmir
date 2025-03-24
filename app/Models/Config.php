<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    public $incrementing = false;

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
