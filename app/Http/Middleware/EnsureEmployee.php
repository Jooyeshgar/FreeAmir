<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployee
{
    /**
     * Allow only users that have the Employee role and an associated employee record.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasPermissionTo('employee-portal.dashboard') || ! $user->employee) {
            abort(403, __('Access denied. You must be a registered employee to access this area.'));
        }

        return $next($request);
    }
}
