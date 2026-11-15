<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceMcpJsonResponse
{
    /**
     * Ensure incoming MCP HTTP requests are always treated as JSON expectations.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
