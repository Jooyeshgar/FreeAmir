<?php

namespace App\Http\Middleware;

use App\Models\FiscalYear;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PreventClosedFiscalYearMutations
{
    private const ALLOWED_ROUTE_PREFIXES = [
        'companies.closing-wizard.',
        'companies.close-fiscal-year',
        'documents.sort-numbers.',
        'documents.approve-all',
        'fiscal-years.store',
        'fiscal-years.index',
        'fiscal-years.show',
        'fiscal-years.create',
        'companies.store',
        'companies.index',
        'companies.show',
        'companies.create',
        'change-company',
        'api-tokens.',
        'users.',
        'permissions.',
        'roles.',
        'report',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isMutation($request) || $this->isAllowedRoute($request)) {
            return $next($request);
        }

        $fiscalYear = $this->resolveFiscalYear($request);

        if (! $fiscalYear?->closed_at) {
            return $next($request);
        }

        throw ValidationException::withMessages([
            'fiscal_year' => __('This fiscal year is closed and cannot be modified.'),
        ]);
    }

    private function isMutation(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function isAllowedRoute(Request $request): bool
    {
        $name = (string) $request->route()?->getName();

        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if (str_ends_with($prefix, '.') ? str_starts_with($name, $prefix) : $name === $prefix) {
                return true;
            }
        }

        return false;
    }

    private function resolveFiscalYear(Request $request): ?FiscalYear
    {
        foreach (['fiscalYear', 'fiscal_year'] as $parameter) {
            $value = $request->route($parameter);

            if ($value instanceof FiscalYear) {
                return $value;
            }
        }

        $fiscalYearId = config('active-fiscal-year-id');

        return $fiscalYearId ? FiscalYear::find($fiscalYearId) : null;
    }
}
