<?php

namespace App\Models;

use Carbon\Carbon;
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

    /**
     * Return the inclusive Gregorian boundaries of this Jalali fiscal year.
     */
    public function range(): array
    {
        $start = Carbon::parse(jalali_to_gregorian((int) $this->year, 1, 1, '/'))->startOfDay();
        $end = Carbon::parse(jalali_to_gregorian((int) $this->year + 1, 1, 1, '/'))->subDay()->endOfDay();

        return [$start, $end];
    }
}
