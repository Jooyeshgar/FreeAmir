<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

class CompanyUserPivot extends Pivot
{
    public $incrementing = true;

    protected static function booted(): void
    {
        static::created(function (CompanyUserPivot $pivot): void {
            $yearId = FiscalYear::where('legacy_company_id', $pivot->company_id)->value('id');
            if ($yearId) {
                DB::table('fiscal_year_user')->updateOrInsert([
                    'fiscal_year_id' => $yearId,
                    'user_id' => $pivot->user_id,
                ]);
            }
        });

        static::deleted(function (CompanyUserPivot $pivot): void {
            $yearId = FiscalYear::where('legacy_company_id', $pivot->company_id)->value('id');
            if ($yearId) {
                DB::table('fiscal_year_user')->where('fiscal_year_id', $yearId)->where('user_id', $pivot->user_id)->delete();
            }
        });
    }
}
