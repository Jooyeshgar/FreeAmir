<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\InvoiceDashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceDashboardController extends Controller
{
    public function __construct(private readonly InvoiceDashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'string', 'max:10'],
            'end_date' => ['nullable', 'string', 'max:10'],
        ]);

        foreach (['start_date', 'end_date'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = jalaliInputToGregorian($validated[$field], $field);
            }
        }

        $company = Company::withoutGlobalScopes()->findOrFail(getActiveCompany());
        [$fiscalStart, $fiscalEnd] = $company->fiscalYearRange();

        $start = isset($validated['start_date']) ? $validated['start_date'] : $fiscalStart->toDateString();
        $end = isset($validated['end_date']) ? $validated['end_date'] : $fiscalEnd->toDateString();

        if ($start < $fiscalStart->toDateString() || $start > $fiscalEnd->toDateString()) {
            throw ValidationException::withMessages([
                'start_date' => [__('The start date must be within the active fiscal year.')],
            ]);
        }

        if ($end < $fiscalStart->toDateString() || $end > $fiscalEnd->toDateString()) {
            throw ValidationException::withMessages([
                'end_date' => [__('The end date must be within the active fiscal year.')],
            ]);
        }

        if ($start > $end) {
            throw ValidationException::withMessages([
                'end_date' => [__('The end date must be on or after the start date.')],
            ]);
        }

        $validated['start_date'] = $start;
        $validated['end_date'] = $end;

        return view('invoices.dashboard', $this->dashboardService->dashboard($validated));
    }
}
