<?php

namespace App\Http\Middleware;

use App\Models\FiscalYear;
use Closure;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DefaultCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'active-company-id' => null,
            'active-fiscal-year-id' => null,
            'active-company-name' => null,
            'active-company-fiscal-year' => null,
        ]);

        $yearId = $request->cookie('active-fiscal-year-id') ?? $request->cookie('active-company-id');

        if ($yearId !== null) {
            $company = FiscalYear::find($yearId);

            if (! $company or ! $company->users->contains(auth()->id())) {
                Cookie::queue(Cookie::forget('active-fiscal-year-id'));
                Cookie::queue(Cookie::forget('active-company-id'));

                config([
                    'active-company-id' => null,
                    'active-fiscal-year-id' => null,
                    'active-company-name' => null,
                    'active-company-fiscal-year' => null,
                ]);

                $this->setDefaultCompany();
            } else {
                Cookie::queue('active-fiscal-year-id', $company->id, 362 * 24 * 60);
                Cookie::queue(Cookie::forget('active-company-id'));
                config([
                    'active-company-id' => $company->company_id,
                    'active-fiscal-year-id' => $company->id,
                    'active-company-name' => $company->name,
                    'active-company-fiscal-year' => $company->fiscal_year,
                ]);
            }
        } else {
            $this->setDefaultCompany();
        }

        return $next($request);
    }

    private function setDefaultCompany(): void
    {
        if (Auth::check()) {
            $company = Auth::user()->companies()->where('fiscal_year', toEnglish(jdate('Y')))->first();
            if ($company) {
                Cookie::queue('active-fiscal-year-id', $company->id, 362 * 24 * 60);

                config([
                    'active-company-id' => $company->company_id,
                    'active-fiscal-year-id' => $company->id,
                    'active-company-name' => $company->name,
                    'active-company-fiscal-year' => $company->fiscal_year,
                ]);
            }
        }
    }
}
