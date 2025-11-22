<?php

namespace App\Models;

use App\Models\Scopes\FiscalYearScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_id',
    ];

    public static function booted(): void
    {
        static::addGlobalScope(new FiscalYearScope);

        static::creating(function ($bank) {
            $bank->company_id ??= session('active-company-id');
        });
    }
}
