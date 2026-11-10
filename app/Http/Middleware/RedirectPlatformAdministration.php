<?php

namespace App\Http\Middleware;

use App\Extensions\AdministrationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectPlatformAdministration
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('roles.*', 'permissions.*', 'users.*', 'companies.*', 'fiscal-years.*', 'activity-logs.*')) {
            if ($panel = app(AdministrationContext::class)->panel()) {
                return redirect()->route($panel->dashboardRoute());
            }
        }

        return $next($request);
    }
}
