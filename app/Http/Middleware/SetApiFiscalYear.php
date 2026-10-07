<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiFiscalYear
{
    public function handle(Request $request, Closure $next): Response
    {
        $fiscalYearId = $request->route('fiscal_year');

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

        if (! $request->user()->fiscalYears()->whereKey($fiscalYearId)->exists()) {
            return response()->json([
                'message' => __('You do not have access to this fiscal year.'),
            ], 403);
        }

        config(['active-fiscal-year-id' => (int) $fiscalYearId]);

        return $next($request);
    }
}
