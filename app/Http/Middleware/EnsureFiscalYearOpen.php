<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\FiscalYearMutationGuard;
use Closure;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFiscalYearOpen
{
    public function __construct(private readonly FiscalYearMutationGuard $fiscalYearMutationGuard) {}

    /**
     * Reject mutations against closed fiscal-year data.
     *
     * Read-only requests remain available, and the fiscal-year creation and closing workflows have their own explicit state transitions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExempt($request)) {
            return $next($request);
        }

        $subject = $this->routeSubject($request);
        $destination = $request->input('target_fiscal_year_id') ?? $request->input('fiscal_year_id') ?? $request->route('target_fiscal_year_id');

        $this->fiscalYearMutationGuard->ensureOpen($subject, $destination);

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        if ($request->isMethod('GET') && ! $request->routeIs('fiscal-years.edit') && ! $request->routeIs('documents.duplicate')) {
            return true;
        }

        return $request->routeIs([
            'fiscal-years.store',
            'companies.store',
            'companies.update',
            'companies.test-moadian-connection',
            'api-tokens.store',
            'api-tokens.destroy',
            'users.verify',
            'users.impersonate',
            'users.store',
            'users.update',
            'users.destroy',
            'permissions.*',
            'roles.*',
            'report',
            'registered-user.company.store',
            'backups.export',
            'backups.import',
            'documents.export.download',
            'invoices.export',
            'commercial-ledgers.store',
            'commercial-ledgers.destroy',
            'companies.close-fiscal-year',
            'companies.closing-wizard',
            'companies.closing-wizard.step1',
            'companies.closing-wizard.step3',
            'companies.closing-wizard.recalculate',
            'companies.closing-wizard.recreate-opening',
        ]);
    }

    private function routeSubject(Request $request): EloquentModel|int|null
    {
        if ($request->routeIs('users.create-employee')) {
            return null;
        }

        if ($request->routeIs('configs.*')) {
            return getActiveFiscalYear();
        }

        foreach ($request->route()?->parameters() ?? [] as $key => $parameter) {
            if ($parameter instanceof EloquentModel) {
                if ($parameter instanceof Company && $request->routeIs('companies.update')) {
                    continue;
                }

                return $parameter;
            }

            if (in_array($key, ['fiscal_year', 'fiscalYear'], true) && (is_int($parameter) || (is_string($parameter) && ctype_digit($parameter)))) {
                return $parameter;
            }
        }

        return null;
    }
}
