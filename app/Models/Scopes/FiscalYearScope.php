<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

class FiscalYearScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('company', function (Builder $query) {
            $query->whereHas('users', function (Builder $query) {
                $query->where('user_id', auth()->id())
                ->where('company_id', session('app.company_id'))
                ->where(DB::raw("YEAR(date)"), session('app.fiscal_year'));
            });
        });
    }
}
