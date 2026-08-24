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
        $shouldLog = ! $request->isMethodSafe();

        if ($shouldLog) {
            $this->activityLogService->beginRequest($request);
        }

        try {
            $response = $next($request);
            $hasValidationErrors = $request->hasSession() && $request->session()->has('errors');

            if ($shouldLog && $response->getStatusCode() < 400 && ! $hasValidationErrors) {
                $this->activityLogService->recordRequest($request, $actor, $authenticatedUser);
            }

            return $response;
        } finally {
            if ($shouldLog) {
                $this->activityLogService->endRequest();
            }
        }
    }
}
