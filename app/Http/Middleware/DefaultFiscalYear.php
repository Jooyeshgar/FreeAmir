<?php

namespace App\Http\Middleware;

use App\Models\FiscalYear;
use Closure;
use Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class DefaultFiscalYear
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasCookie('active-fiscal-year-id')) {
            $fiscalYear = FiscalYear::find($request->cookie('active-fiscal-year-id'));

            if (! $fiscalYear or ! $fiscalYear->users->contains(auth()->id())) {
                Cookie::forget('active-fiscal-year-id');

                config([
                    'active-fiscal-year-id' => null,
                    'active-company-name' => null,
                    'active-company-fiscal-year' => null,
                ]);

                $this->setDefaultCompany();
            } else {
                config([
                    'active-fiscal-year-id' => $fiscalYear->id,
                    'active-company-name' => $fiscalYear->company?->name,
                    'active-company-fiscal-year' => $fiscalYear->year,
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
            $fiscalYear = Auth::user()->fiscalYears()->where('year', toEnglish(jdate('Y')))->first();
            if ($fiscalYear) {
                Cookie::queue('active-fiscal-year-id', $fiscalYear->id, 362 * 24 * 60);

                config([
                    'active-fiscal-year-id' => $fiscalYear->id,
                    'active-company-name' => $fiscalYear->company?->name,
                    'active-company-fiscal-year' => $fiscalYear->year,
                ]);
            }
        }
    }
}
