<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use App\Models\Traits\QueryHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory, QueryHelper;

    protected $fillable = [
        'name',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);
    }
}
