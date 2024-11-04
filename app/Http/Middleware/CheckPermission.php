<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{

    /**
     * Handle an incoming request by checking if the user has the required permission.
     *
     * This method retrieves the route name from the request and determines the
     * corresponding permission required. It allows for both specific and wildcard
     * permissions. If the user does not have either, an UnauthorizedException is thrown.
     *
     * @param Request $request The incoming HTTP request.
     * @param Closure $next The next middleware to be called.
     * @return Response The HTTP response after the request is processed.
     * @throws UnauthorizedException If the user lacks the necessary permissions.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()->getName();

        $permission = explode('.', $routeName)[0];
        $wildcardPermission = "{$permission}.*";
        
        // Check if the user has either the specific permission or the wildcard permission
        if (
            !$request->user()->can($routeName) &&
            !$request->user()->can($wildcardPermission)
        ) {
            throw UnauthorizedException::forPermissions([$permission]);
        }
        return $next($request);
    }
}
