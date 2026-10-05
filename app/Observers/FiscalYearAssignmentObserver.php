<?php

namespace App\Observers;

use App\Models\Config;
use App\Models\Document;
use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class FiscalYearAssignmentObserver
{
    public function saving(Model $record): void
    {
        if ($record->exists && ! $record->isDirty('company_id') && ! $record->isDirty('fiscal_year_id') && $record->fiscal_year_id !== null) {
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

        $fiscalYearId = $record->fiscal_year_id ?: getActiveFiscalYear();
        $year = $fiscalYearId ? FiscalYear::query()->find($fiscalYearId) : null;

        if (! $year || ($record->company_id && (int) $record->company_id !== (int) $year->company_id)) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => [__('The selected fiscal year is invalid.')],
            ]);
        }

        $record->company_id = $year->company_id;
        $record->fiscal_year_id = $year->id;
    }
}
