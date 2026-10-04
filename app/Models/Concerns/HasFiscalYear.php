<?php

namespace App\Models\Concerns;

use App\Models\Config;
use App\Models\Document;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait HasFiscalYear
{
    protected static function bootHasFiscalYear(): void
    {
        static::saving(function (Model $record): void {
            if ($record->exists && ! $record->isDirty('company_id') && $record->fiscal_year_id !== null) {
                return;
            }

            if ($record instanceof Config && $record->company_id === null) {
                $record->fiscal_year_id = null;

                return;
            }

            if ($record instanceof Document && $record->company_id === null && getActiveFiscalYear() === null
                && ! FiscalYear::query()->exists()) {
                return;
            }

            $legacyCompanyId = (int) ($record->company_id ?: getActiveLegacyCompany());
            $year = FiscalYear::query()->where('legacy_company_id', $legacyCompanyId)->first();

            if (! $year) {
                throw ValidationException::withMessages([
                    'company_id' => [__('The selected fiscal year is invalid.')],
                ]);
            }

            $record->company_id = $legacyCompanyId;
            $record->fiscal_year_id = $year->id;
        });
    }
}
