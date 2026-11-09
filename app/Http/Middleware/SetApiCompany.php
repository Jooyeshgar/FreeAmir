<?php

namespace App\Http\Middleware;

use App\Models\Config;
use App\Models\Scopes\FiscalYearScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['active-company-id' => 0, 'active-fiscal-year-id' => null, 'active-legacy-company-id' => 0]);
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

        $year = $request->user()->fiscalYears()->where('legacy_company_id', $companyId)->first();
        if (! $year) {
            return response()->json([
                'message' => __('You do not have access to this company.'),
            ], 403);
        }

        DefaultCompany::activate($year);

        $globals = Config::withoutGlobalScope(FiscalYearScope::class)->whereNull('company_id')->get();
        foreach ($globals->merge(Config::all()) as $config) {
            if ($config->value !== null) {
                config(['amir.'.$config->key => $config->value]);
            }
        }

        return $next($request);
    }
}