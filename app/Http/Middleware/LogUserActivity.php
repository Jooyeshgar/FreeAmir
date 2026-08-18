<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Buffer model events and persist the completed request audit record. */
class LogUserActivity
{
    public function __construct(private readonly ActivityLogService $activityLogService) {}

    /**
     * Start collection before the route runs and save one activity record for every successful authenticated write request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->activityLogService->beginRequest($request);
        $response = $next($request);
        $actor = $this->activityLogService->resolveActor($request->user());
        $hasValidationErrors = $request->hasSession() && $request->session()->has('errors');

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $response->getStatusCode() < 400 && ! $hasValidationErrors) {
            $this->activityLogService->recordRequest($request, $actor);
        }

        return $response;
    }
}
