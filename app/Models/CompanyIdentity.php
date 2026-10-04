<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyIdentity extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }
}
