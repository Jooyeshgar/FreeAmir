<?php

namespace App\Http\Middleware;

use App\Models\FiscalYear;
use Closure;
use Cookie;
use Illuminate\Http\Request;
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
            'active-company-id' => 0,
            'active-fiscal-year-id' => null, 'active-company-name' => null,
            'active-company-fiscal-year' => null,
        ]);

        if (! $request->user()) {
            return $next($request);
        }

        $selectedId = $request->cookie('active-fiscal-year-id');
        $year = $selectedId && ctype_digit((string) $selectedId) ? $request->user()->fiscalYears()->whereKey((int) $selectedId)->first() : null;

        if (! $year && $selectedId !== null) {
            return response('', 403)->withCookie(Cookie::forget('active-fiscal-year-id'));
        }

        if (! $year) {
            $year = $request->user()->fiscalYears()->where('year', toEnglish(jdate('Y')))->orderBy('fiscal_years.id')->first()
                ?? $request->user()->fiscalYears()->orderByDesc('year')->orderBy('fiscal_years.id')->first();
            if ($year) {
                Cookie::queue('active-fiscal-year-id', $year->id, 362 * 24 * 60);
            }
        }

        if ($year) {
            self::activate($year);
        }

        return $next($request);
    }

    public static function activate(FiscalYear $year): void
    {
        config([
            'active-company-id' => $year->company_id,
            'active-fiscal-year-id' => $year->id,
            'active-company-name' => $year->company->name,
            'active-company-fiscal-year' => $year->year,
        ]);
    }
}
