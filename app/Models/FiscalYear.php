<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FiscalYear extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function companyIdentity(): BelongsTo
    {
        return $this->belongsTo(CompanyIdentity::class);
    }

    public function legacyCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
