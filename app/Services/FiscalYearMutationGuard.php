<?php

namespace App\Services;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the fiscal year affected by a mutation and enforces its open state.
 *
 * Child records can inherit their fiscal year through one of their owning records, while transfer operations can name a destination year explicitly.
 */
class FiscalYearMutationGuard
{
    /** Assert that the fiscal year represented by a model, ID, or active selection is open. A destination year is checked as well when supplied. */
    public function ensureOpen(Model|int|string|null $subject = null, int|string|null $destinationFiscalYearId = null): FiscalYear
    {
        $fiscalYear = $this->resolveFiscalYear($subject ?? getActiveFiscalYear());

        if ($fiscalYear === null) {
            $fiscalYear = $this->resolveFiscalYear($destinationFiscalYearId);
        }

        if ($fiscalYear === null) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('The fiscal year could not be resolved.'),
            ]);
        }

        if ($fiscalYear->isClosed()) {
            throw ValidationException::withMessages([
                'fiscal_year' => __('This fiscal year is already closed.'),
            ]);
        }

        if ($destinationFiscalYearId !== null && $destinationFiscalYearId !== '') {
            $destination = $this->resolveFiscalYear($destinationFiscalYearId);

            if ($destination === null) {
                throw ValidationException::withMessages([
                    'fiscal_year' => __('The fiscal year could not be resolved.'),
                ]);
            }

            if ($destination->isClosed()) {
                throw ValidationException::withMessages([
                    'fiscal_year' => __('This fiscal year is already closed.'),
                ]);
            }
        }

        return $fiscalYear;
    }

    /** Resolve a fiscal year from a directly-owned record or a nested parent.*/
    public function resolveFiscalYear(Model|int|string|null $subject): ?FiscalYear
    {
        if ($subject instanceof FiscalYear) {
            return $subject;
        }

        if (is_int($subject) || (is_string($subject) && ctype_digit($subject))) {
            return FiscalYear::withoutGlobalScopes()->find((int) $subject);
        }

        if (! $subject instanceof Model) {
            return null;
        }

        if ($subject->relationLoaded('fiscalYear') && $subject->fiscalYear instanceof FiscalYear) {
            return $subject->fiscalYear;
        }

        if ($subject->getAttribute('fiscal_year_id')) {
            return FiscalYear::withoutGlobalScopes()->find((int) $subject->getAttribute('fiscal_year_id'));
        }

        foreach (['fiscalYear', 'invoice', 'document', 'ancillaryCost', 'customer', 'payroll', 'employee', 'monthlyAttendance', 'organizationUnit', 'workSite', 'workSites', 'bankAccount', 'chequebook', 'cheque', 'payment', 'decree', 'element', 'warehouse', 'product', 'comment'] as $relation) {
            if (! method_exists($subject, $relation)) {
                continue;
            }

            $parent = $subject->getRelationValue($relation);
            if ($parent instanceof Model) {
                $fiscalYear = $this->resolveFiscalYear($parent);
                if ($fiscalYear !== null) {
                    return $fiscalYear;
                }
            }
        }

        return null;
    }
}
