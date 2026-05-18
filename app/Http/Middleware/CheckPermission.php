<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
     * @param  Request  $request  The incoming HTTP request.
     * @param  Closure  $next  The next middleware to be called.
     * @return Response The HTTP response after the request is processed.
     *
     * @throws UnauthorizedException If the user lacks the necessary permissions.
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $routeName = $permission ?? $request->route()->getName();
        $wildcardPermission = str_contains($routeName, '.')
            ? preg_replace('/\.[^.]+$/', '.*', $routeName)
            : "{$routeName}.*";
        $user = $request->user();

        if (
            ! $user->can($routeName) &&
            ! $user->can($wildcardPermission)
        ) {
            throw UnauthorizedException::forPermissions([$wildcardPermission]);
        }

        if ($user->currentAccessToken()) {
            $tokenAllowsRoute = collect($user->currentAccessToken()->abilities ?? [])
                ->contains(fn (string $ability) => Str::is($ability, $routeName));

            if (! $tokenAllowsRoute) {
                throw UnauthorizedException::forPermissions([$wildcardPermission]);
            }
        }

        return $next($request);
    }
}
