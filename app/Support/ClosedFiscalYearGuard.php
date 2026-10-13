<?php

namespace App\Support;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ClosedFiscalYearGuard
{
    /**
     * Relations used by records that inherit their fiscal year from a parent.
     * Keeping this list here gives every Eloquent mutation the same protection,
     * including mutations made by services and API endpoints.
     */
    private const PARENT_RELATIONS = [
        'document', 'invoice', 'ancillaryCost', 'cheque', 'payroll', 'salaryDecree',
        'workSite', 'workSites', 'warehouse', 'product', 'employee', 'monthlyAttendance',
    ];

    public static function assertMutable(Model $model): void
    {
        // The closing recalculation workflow deliberately reopens a year for
        // the duration of its transaction before closing it again.
        if ($model instanceof FiscalYear && $model->isDirty('closed_at') && $model->closed_at === null) {
            return;
        }

        if ($model instanceof FiscalYear && $model->closed_at !== null) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('This fiscal year is closed and cannot be modified.'),
            ]);
        }

        $fiscalYearId = self::fiscalYearId($model);

        if (! $fiscalYearId) {
            return;
        }

        $closed = FiscalYear::query()->whereKey($fiscalYearId)->whereNotNull('closed_at')->exists();

        if ($closed) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('This fiscal year is closed and cannot be modified.'),
            ]);
        }
    }

    private static function fiscalYearId(Model $model, int $depth = 0): ?int
    {
        if ($depth > 2) {
            return null;
        }

        if ($model->getAttribute('fiscal_year_id')) {
            return (int) $model->getAttribute('fiscal_year_id');
        }

        foreach (self::PARENT_RELATIONS as $relation) {
            if (method_exists($model, $relation) && ($parent = $model->{$relation})) {
                $id = self::fiscalYearId($parent, $depth + 1);
                if ($id) {
                    return $id;
                }
            }
        }

        return null;
    }
}
