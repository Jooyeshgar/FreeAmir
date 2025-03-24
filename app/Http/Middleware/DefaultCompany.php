<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
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
        if (session('active-company-id')) {
            $company = Company::find(session('active-company-id'));
            if (!$company or ! $company->users->contains(auth()->id())) {
                session()->forget('active-company-id');
                session()->forget('active-company-name');
                session()->forget('active-company-fiscal-year');
                $this->setDefaultCompany($request);
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
                session([
                    'active-company-id' => $company->id,
                    'active-company-name' => $company->name,
                    'active-company-fiscal-year' => $company->fiscal_year
                ]);
            }
        }
    }
}
