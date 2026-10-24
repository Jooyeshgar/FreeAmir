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

        // Nested records can have a different owner from the first route parameter.
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof EloquentModel && $parameter !== $subject) {
                $this->fiscalYearMutationGuard->ensureOpen($parameter);
            }
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        if ($request->isMethod('GET') && $request->routeIs([
            'fiscal-years.edit',
            'subjects.edit',
            'product-groups.edit',
            'service-groups.edit',
            'comments.edit',
            'configs.edit',
            'documents.edit',
            'documents.files.edit',
            'products.edit',
            'warehouses.edit',
            'services.edit',
            'customers.edit',
            'customer-groups.edit',
            'bank-accounts.edit',
            'banks.edit',
            'chequebooks.edit',
            'cheques.edit',
            'invoices.edit',
            'invoices.ancillary-costs.edit',
            'hr.employees.edit',
            'hr.organization-units.edit',
            'hr.org-charts.edit',
            'hr.personnel-requests.edit',
            'attendance.attendance-logs.edit',
            'attendance.monthly-attendances.edit',
            'attendance.work-shifts.edit',
            'salary.payroll-elements.edit',
            'salary.salary-decrees.edit',
            'salary.public-holidays.edit',
            'salary.tax-slabs.edit',
            'salary.work-sites.edit',
            'salary.work-site-contracts.edit',
            'salary.payroll-items.edit',
            'employee-portal.personnel-requests.edit',
        ])) {
            return false;
        }

        if ($request->isMethod('GET')) {
            return ! $request->routeIs([
                'subjects.create',
                'products.create',
                'products.import',
                'product-groups.create',
                'services.create',
                'services.import',
                'service-groups.create',
                'customers.create',
                'customers.import',
                'customer-groups.create',
                'warehouses.create',
                'warehouses.transfer',
                'banks.create',
                'bank-accounts.create',
                'chequebooks.create',
                'cheques.create',
                'comments.create',
                'documents.create',
                'documents.files.create',
                'documents.import',
                'documents.duplicate',
                'invoices.create',
                'invoices.void-form',
                'invoices.ancillary-costs.create',
                'hr.employees.create',
                'hr.organization-units.create',
                'hr.org-charts.create',
                'hr.personnel-requests.create',
                'attendance.attendance-logs.create',
                'attendance.attendance-logs.bulk-create',
                'attendance.attendance-logs.import',
                'attendance.monthly-attendances.create',
                'attendance.monthly-attendances.bulk-create',
                'attendance.work-shifts.create',
                'salary.payroll-elements.create',
                'salary.salary-decrees.create',
                'salary.public-holidays.create',
                'salary.tax-slabs.create',
                'salary.work-sites.create',
                'salary.work-site-contracts.create',
                'employee-portal.change-employee-information',
                'employee-portal.personnel-requests.create',
                'attendance.attendance-logs.import.preview',
            ]);
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
            'reports.documents.export',
            'attendance.attendance-logs.import.preview',
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
