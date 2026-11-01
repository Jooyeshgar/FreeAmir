<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InvalidateImpersonationSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sessions created before impersonation was retired must require a fresh login.
        if ($request->session()->exists('impersonated_by')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
