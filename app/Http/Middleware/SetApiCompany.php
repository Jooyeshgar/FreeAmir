<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['active-company-id' => 0, 'active-fiscal-year-id' => null]);
        $fiscalYearId = $request->route('fiscalYear');

        if ($fiscalYearId === null || $fiscalYearId === '') {
            return response()->json([
                'message' => __('The fiscal year path parameter is required.'),
            ], 422);
        }

        if (filter_var($fiscalYearId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return response()->json([
                'message' => __('The fiscal year path parameter must be a valid fiscal year ID.'),
            ], 422);
        }

        $year = $request->user()->fiscalYears()->whereKey((int) $fiscalYearId)->first();
        if (! $year) {
            return response()->json([
                'message' => __('You do not have access to this fiscal year.'),
            ], 403);
        }

        DefaultCompany::activate($year);

        return $next($request);
    }
}
