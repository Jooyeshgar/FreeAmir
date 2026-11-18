<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMcpAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() || ! $request->user()?->can('api.access')) {
            throw new AuthorizationException('The api.access permission is required.');
        }

        return $next($request);
    }
}
