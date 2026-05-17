<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->route('company');

        if ($companyId === null || $companyId === '') {
            return response()->json([
                'message' => __('The company path parameter is required.'),
            ], 422);
        }

        if (filter_var($companyId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            return response()->json([
                'message' => __('The company path parameter must be a valid company ID.'),
            ], 422);
        }

        if (! $request->user()->companies()->whereKey($companyId)->exists()) {
            return response()->json([
                'message' => __('You do not have access to this company.'),
            ], 403);
        }

        config(['active-company-id' => (int) $companyId]);

        return $next($request);
    }
}
