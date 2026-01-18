<?php

namespace App\Http\Middleware;

use App\Models\Company;
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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasCookie('active-company-id')) {
            $company = Company::find($request->cookie('active-company-id'));

            if (! $company or ! $company->users->contains(auth()->id())) {
                Cookie::expire('active-company-id');

                config([
                    'active-company-name' => null,
                    'active-company-fiscal-year' => null,
                ]);

                $this->setDefaultCompany($request);
            } else {
                config([
                    'active-company-name' => $company->name,
                    'active-company-fiscal-year' => $company->fiscal_year,
                ]);
            }
        } else {
            $this->setDefaultCompany($request);
        }

        return $next($request);
    }

    private function setDefaultCompany(Request $request): void
    {
        if (Auth::check()) {
            $company = Auth::user()->companies()->first();
            if ($company) {
                Cookie::queue('active-company-id', $company->id, 24 * 60 * 30);

                config([
                    'active-company-name' => $company->name,
                    'active-company-fiscal-year' => $company->fiscal_year,
                ]);
            }
        }
    }
}
