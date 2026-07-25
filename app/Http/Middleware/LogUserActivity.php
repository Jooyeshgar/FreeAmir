<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authenticatedUser = $request->user();
        $actor = $this->activityLogService->resolveActor($authenticatedUser);
        $response = $next($request);
        $hasValidationErrors = $request->hasSession() && $request->session()->has('errors');

        if (! $request->isMethodSafe() && $response->getStatusCode() < 400 && ! $hasValidationErrors) {
            $this->activityLogService->recordRequest($request, $actor, $authenticatedUser);
        }

        return $response;
    }
}
