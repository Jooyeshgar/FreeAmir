<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'crosses_midnight',
        'float_before',
        'float_after',
        'break',
        'is_active',
    ];

    protected $casts = [
        'crosses_midnight' => 'boolean',
        'is_active' => 'boolean',
        'float_before' => 'integer',
        'float_after' => 'integer',
        'break' => 'integer',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }
}
