<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $request->header('X-Company-Id');

        if (! $companyId) {
            return response()->json([
                'message' => __('The X-Company-Id header is required.'),
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
